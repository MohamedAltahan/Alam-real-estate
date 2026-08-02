<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientStage;
use App\Models\ContactRequest;
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
        $email = $request->email;

        // بدون هاتف ولا بريد لا يوجد ما نطابق عليه — وإلا أعاد أول عميل في الجدول
        if (! $phone && ! $email) {
            return null;
        }

        return Client::query()
            ->where(function ($q) use ($phone, $email) {
                if ($phone) {
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ?", [$phone]);
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
            $client = ! empty($data['existing_client_id'])
                ? Client::findOrFail($data['existing_client_id'])
                : Client::create([
                    'name' => $request->name,
                    'phone' => $request->phone,
                    'email' => $request->email,
                    // منطقة العقار المطلوب إن وُجد
                    'area_id' => $data['area_id'] ?? $request->property?->area_id,
                    'type_id' => $data['type_id'] ?? null,
                    'stage_id' => $data['stage_id'] ?? $this->defaultStageId(),
                    'agent_id' => $data['agent_id'] ?? null,
                    'source_id' => $data['source_id'] ?? null,
                    'notes' => $data['notes'] ?? $request->message,
                ]);

            // العقار محل الاستفسار يُربط بسجل العميل
            if ($request->property_id) {
                $client->properties()->syncWithoutDetaching([
                    $request->property_id => ['relation' => 'استفسار'],
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
