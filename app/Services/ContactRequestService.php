<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Client;
use App\Models\ClientStage;
use App\Models\ClientType;
use App\Models\ContactRequest;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * تحويل طلب التواصل إلى عميل في الـ CRM.
 */
class ContactRequestService
{
    /**
     * هل يوجد عميل مسجَّل بنفس رقم الهاتف (أو البريد)؟
     * يُستخدم لتحذير المستخدم قبل التحويل بدل إنشاء تكرار.
     */
    public function findDuplicate(ContactRequest $request): ?Client
    {
        $phone = $this->normalizePhone($request->phone);
        $national = PhoneNumber::split($request->phone)['national'];
        $email = $request->email;

        // بدون هاتف ولا بريد لا يوجد ما نطابق عليه — وإلا أعاد أول عميل في الجدول
        if (! $phone && ! $email) {
            return null;
        }

        return Client::query()
            ->where(function ($q) use ($phone, $national, $email) {
                if ($phone) {
                    // الرقم المحلي (بعد فصل المفتاح) أو الرقم الكامل كما كان يُخزَّن قديماً
                    $q->orWhere('phone', $national ?: $phone)
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ?", [$phone]);
                }
                if ($email) {
                    $q->orWhere('email', $email);
                }
            })
            ->first();
    }

    /**
     * ينشئ عميلاً من الطلب (أو يربطه بعميل قائم)، ثم:
     * يربط العقار المطلوب · يسجّل تفاعلاً بمصدر العميل · يقفل الطلب.
     *
     * @param  array{agent_id?:int|null, source_id?:int|null, stage_id?:int|null, type_id?:int|null, area_id?:int|null, notes?:string|null, existing_client_id?:int|null}  $data
     */
    public function convertToClient(ContactRequest $request, array $data, int $userId): Client
    {
        return DB::transaction(function () use ($request, $data, $userId) {
            $isNew = empty($data['existing_client_id']);
            ['code' => $phoneCode, 'national' => $phone] = PhoneNumber::split($request->phone);

            $client = ! $isNew
                ? Client::findOrFail($data['existing_client_id'])
                : Client::create([
                    'name' => $request->name,
                    'phone_code' => $phoneCode,
                    'phone' => $phone !== '' ? $phone : (string) $request->phone,
                    'email' => $request->email,
                    'type_id' => $data['type_id'] ?? ClientType::where('key', 'tenant')->value('id'),
                    'stage_id' => $data['stage_id'] ?? $this->defaultStageId(),
                    'agent_id' => $data['agent_id'] ?? null,
                    'source_id' => $data['source_id'] ?? null,
                    'notes' => $data['notes'] ?? $request->message,
                    'recorded_by' => $userId,
                ]);

            // احتياج العقار من العقار محل الاستفسار (منطقة + مدينة + نوع الوحدة)
            $areaId = $data['area_id'] ?? $request->property?->area_id;
            if ($isNew && ($areaId || $request->property?->unit_type_id)) {
                $client->needs()->create([
                    'area_id' => $areaId,
                    'city_id' => $areaId ? Area::whereKey($areaId)->value('city_id') : null,
                    'unit_type_id' => $request->property?->unit_type_id,
                ]);
            }

            // أثر واضح في سجل العميل يوضّح من أين جاء
            $client->interactions()->create([
                'user_id' => $userId,
                'type' => 'note',
                'notes' => $this->interactionNote($request),
                'stage_id' => $client->stage_id,
                'occurred_at' => now(),
            ]);

            $request->converted_client_id = $client->id;
            $request->markContacted($userId);

            return $client;
        });
    }

    private function interactionNote(ContactRequest $request): string
    {
        return collect([
            'محوَّل من طلب تواصل رقم #'.$request->id,
            $request->requestType?->name ? 'النوع: '.$request->requestType->name : null,
            $request->subject ? 'الموضوع: '.$request->subject : null,
            $request->property?->reference_code ? 'العقار: '.$request->property->reference_code : null,
            $request->message ? 'الرسالة: '.$request->message : null,
        ])->filter()->implode("\n");
    }

    private function defaultStageId(): ?int
    {
        return ClientStage::where('key', 'new')->value('id')
            ?? ClientStage::orderBy('sort_order')->value('id');
    }

    private function normalizePhone(?string $phone): ?string
    {
        return $phone ? preg_replace('/[^0-9]/', '', $phone) : null;
    }
}
