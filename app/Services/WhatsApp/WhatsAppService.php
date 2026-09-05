<?php

namespace App\Services\WhatsApp;

use App\Models\ClientViewing;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\ClientAuditLogger;
use App\Support\PhoneNumber;
use App\Support\WhatsAppTemplates;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * واتساب المكتب: حالة الرقم المربوط (للأيقونة)، ربط الرقم بالـ QR،
 * وإرسال رسائل المعاينات (تفاصيل العميل للمالك · المتابعة للعميل) مع علامتي المعاينة.
 */
class WhatsAppService
{
    public const STATUS_CACHE = 'whatsapp.status';

    /** الأيقونة تُستطلع كل دقيقة من كل متصفح — نسأل البوابة مرة كل 50 ثانية على الأكثر */
    public const STATUS_TTL = 50;

    public function __construct(private KhabeerSoftClient $client, private ClientAuditLogger $audit) {}

    public function instance(): ?WhatsappInstance
    {
        return WhatsappInstance::query()->latest('id')->first();
    }

    // ===== الحالة =====

    /** حالة الأيقونة بدون الاتصال بالبوابة: من الكاش أو من السجل المحلي (لعرض الصفحة) */
    public function status(): array
    {
        return Cache::get(self::STATUS_CACHE) ?? $this->describe($this->instance());
    }

    /** للاستطلاع الدوري: تُحدَّث من البوابة عند انتهاء الكاش */
    public function pollStatus(): array
    {
        return Cache::get(self::STATUS_CACHE) ?? $this->refresh();
    }

    /** سؤال البوابة عن حالة الجلسة وتخزين النتيجة */
    public function refresh(): array
    {
        $instance = $this->instance();

        if ($instance && $this->client->isConfigured()) {
            try {
                $this->apply($instance, $this->client->instance($instance->external_id));
            } catch (ConnectionException $e) {
                // البوابة نفسها غير متاحة: نُبقي آخر حالة معروفة ونحاول في الدورة التالية
                Log::warning('whatsapp status: '.$e->getMessage());
                $instance->forceFill(['checked_at' => now()])->save();
            } catch (\Throwable $e) {
                // رد خطأ من البوابة (جلسة محذوفة / مفتاح غير صالح…) = غير متصل
                Log::warning('whatsapp status: '.KhabeerSoftClient::errorMessage($e));
                $instance->forceFill(['status' => 'disconnected', 'connected_at' => null, 'checked_at' => now()])->save();
            }
        }

        $state = $this->describe($instance);
        Cache::put(self::STATUS_CACHE, $state, self::STATUS_TTL);

        return $state;
    }

    /** @return array{configured:bool, status:string, connected:bool, phone:?string, label:string, tone:string, checked_at:?string} */
    private function describe(?WhatsappInstance $instance): array
    {
        $configured = $this->client->isConfigured();
        $status = ! $configured ? 'unconfigured' : ($instance?->status ?? 'none');

        [$label, $tone] = match ($status) {
            'connected' => ['واتساب متصل', 'success'],
            'unconfigured' => ['واتساب غير مُعدّ (المفتاح ناقص)', 'muted'],
            'none' => ['لم يُربط رقم واتساب بعد', 'muted'],
            'qr', 'created' => ['واتساب بانتظار ربط الرقم', 'warning'],
            default => ['واتساب غير متصل', 'danger'],
        };

        return [
            'configured' => $configured,
            'status' => $status,
            'connected' => $status === 'connected',
            'phone' => $instance?->phone,
            'label' => $label,
            'tone' => $tone,
            'checked_at' => $instance?->checked_at?->toIso8601String(),
        ];
    }

    // ===== الربط =====

    /** ربط رقم: نتبنّى جلسة موجودة في حساب البوابة إن وُجدت، وإلا ننشئ جلسة جديدة ثم يُمسح الـ QR */
    public function connect(string $name): WhatsappInstance
    {
        foreach ($this->client->listInstances() as $row) {
            $externalId = (string) ($row['id'] ?? $row['instance_id'] ?? '');

            if ($externalId === '') {
                continue;
            }

            $instance = WhatsappInstance::firstOrNew(['external_id' => $externalId]);
            $instance->name = $instance->name ?: (string) ($row['name'] ?? $name);
            $this->apply($instance, $row);

            return $instance;
        }

        $data = $this->client->createInstance($name);
        $data = $data['data'] ?? $data;

        $instance = new WhatsappInstance([
            'external_id' => (string) ($data['id'] ?? $data['instance_id'] ?? ''),
            'name' => $name,
        ]);
        $this->apply($instance, $data);

        return $instance;
    }

    /** حالة الربط للوحة الـ QR: {status, qr, phone, label, message} */
    public function qrState(): array
    {
        $instance = $this->instance();

        if (! $instance) {
            return ['status' => 'none', 'qr' => null, 'phone' => null, 'label' => 'لم يُربط رقم بعد', 'message' => null];
        }

        $data = $this->client->qr($instance->external_id);
        $this->apply($instance, $data);

        $qr = $data['qr_base64'] ?? null;

        if (is_string($qr) && $qr !== '' && ! str_starts_with($qr, 'data:')) {
            $qr = 'data:image/png;base64,'.$qr;
        }

        return [
            'status' => $instance->status,
            'qr' => $instance->isConnected() ? null : $qr,
            'phone' => $instance->phone,
            'label' => $instance->statusLabel(),
            'message' => $data['message'] ?? null,
        ];
    }

    /** فصل الرقم وحذف الجلسة من البوابة ومحلياً */
    public function disconnect(): void
    {
        $instance = $this->instance();

        if (! $instance) {
            return;
        }

        try {
            $this->client->deleteInstance($instance->external_id);
        } catch (\Throwable $e) {
            Log::warning('whatsapp disconnect: '.KhabeerSoftClient::errorMessage($e));
        }

        $instance->delete();
        Cache::forget(self::STATUS_CACHE);
    }

    private function apply(WhatsappInstance $instance, array $data): void
    {
        $status = $this->mapStatus((string) ($data['status'] ?? $instance->status));

        $instance->forceFill([
            'status' => $status,
            'phone' => $data['phone'] ?? $data['phone_number'] ?? $instance->phone,
            'connected_at' => $status === 'connected' ? ($instance->connected_at ?? now()) : null,
            'checked_at' => now(),
            'last_event' => $data,
        ])->save();

        Cache::forget(self::STATUS_CACHE);
    }

    private function mapStatus(string $raw): string
    {
        $raw = strtolower($raw);

        return match (true) {
            in_array($raw, ['connected', 'ready', 'open', 'authenticated'], true) => 'connected',
            in_array($raw, ['waiting_scan', 'qr', 'qr_code', 'scan', 'connecting'], true) => 'qr',
            in_array($raw, ['disconnected', 'closed', 'logged_out', 'error', 'failed'], true) => 'disconnected',
            default => 'created',
        };
    }

    // ===== رسائل المعاينات =====

    /**
     * المستلمون المتاحون لنوع الرسالة: أرقام المالك بصفاتهم (تفاصيل المعاينة) أو رقم العميل (المتابعة).
     *
     * @return array<int, array{phone:string, name:string, label:string, display:string}>
     */
    public function recipients(ClientViewing $viewing, string $kind): array
    {
        if ($kind === WhatsAppTemplates::KIND_CLIENT) {
            $client = $viewing->client;

            return $client && filled($client->phone) ? [[
                'phone' => PhoneNumber::digits($client->phone_code, $client->phone),
                'name' => (string) $client->name,
                'label' => 'العميل · '.$client->name,
                'display' => $client->full_phone,
            ]] : [];
        }

        $owner = $viewing->property?->owner;

        if (! $owner) {
            return [];
        }

        $rows = $owner->contacts->map(fn ($contact) => [
            'phone' => $contact->whatsapp_number,
            'name' => (string) ($contact->name ?: $owner->name),
            'label' => collect([$contact->role ?: 'المالك', $contact->name])->filter()->implode(' · '),
            'display' => $contact->full_phone,
        ]);

        if ($rows->isEmpty() && filled($owner->phone)) {
            $rows->push([
                'phone' => $owner->whatsapp_number,
                'name' => (string) $owner->name,
                'label' => 'المالك · '.$owner->name,
                'display' => $owner->full_phone,
            ]);
        }

        return $rows->filter(fn (array $row) => $row['phone'] !== '')->values()->all();
    }

    /** حمولة زر الإرسال (نافذة الاختيار): المستلمون + نص القالب معبّأً لكل مستلم */
    public function sendPayload(ClientViewing $viewing, string $kind): array
    {
        $recipients = $this->recipients($viewing, $kind);
        $template = WhatsAppTemplates::body($kind);
        $bodies = [];

        foreach ($recipients as $recipient) {
            $bodies[$recipient['phone']] = WhatsAppTemplates::render($template, $viewing, $recipient['name']);
        }

        return [
            'action' => route('dashboard.viewings.whatsapp', $viewing),
            'kind' => $kind,
            'title' => WhatsAppTemplates::kindLabel($kind),
            'reference' => trim(($viewing->property?->reference_code ? $viewing->property->reference_code.' — ' : '').($viewing->property?->title ?? '')),
            'client' => (string) ($viewing->client?->name ?? ''),
            'recipients' => $recipients,
            'bodies' => $bodies,
            'connected' => $this->status()['connected'],
        ];
    }

    /**
     * إرسال رسالة معاينة عبر البوابة وتسجيلها. عند القبول (queued) تُضبط علامة المعاينة
     * ويُكتب سطر في سجل العميل؛ عند الفشل تبقى العلامة كما هي والخطأ في السجل.
     */
    public function send(ClientViewing $viewing, string $kind, string $to, string $body, User $by): WhatsappMessage
    {
        abort_unless(isset(WhatsAppTemplates::KINDS[$kind]), 404);

        // المتابعة تُرسل بعد تسجيل نتيجة المعاينة فقط
        if ($kind === WhatsAppTemplates::KIND_CLIENT && $viewing->outcome === ClientViewing::OUTCOME_PENDING) {
            throw ValidationException::withMessages(['kind' => 'سجّل نتيجة المعاينة أولاً قبل إرسال المتابعة للعميل.']);
        }

        $recipient = collect($this->recipients($viewing, $kind))
            ->firstWhere('phone', preg_replace('/\D+/', '', $to) ?? '');

        if (! $recipient) {
            throw ValidationException::withMessages(['to' => 'اختر رقماً من قائمة الأرقام المتاحة.']);
        }

        $message = new WhatsappMessage([
            'kind' => $kind,
            'viewing_id' => $viewing->id,
            'client_id' => $viewing->client_id,
            'to_phone' => $recipient['phone'],
            'to_label' => mb_substr($recipient['label'].' — '.$recipient['display'], 0, 150),
            'body' => $body,
            'status' => WhatsappMessage::FAILED,
            'sent_by' => $by->id,
        ]);

        try {
            $instance = $this->instance();

            if (! $instance || ! $instance->isConnected()) {
                throw new \RuntimeException('واتساب غير متصل — اربط الرقم من شاشة واتساب أولاً.');
            }

            $result = $this->client->sendText((int) $instance->external_id, $recipient['phone'], $body);

            $message->status = WhatsappMessage::QUEUED;
            $message->provider_id = isset($result['id']) ? (string) $result['id'] : null;
        } catch (\Throwable $e) {
            $message->error = mb_substr(KhabeerSoftClient::errorMessage($e), 0, 500);
        }

        $message->save();

        if ($message->succeeded()) {
            $viewing->forceFill([WhatsAppTemplates::MARKS[$kind] => now()])->save();

            $this->audit->record($viewing->client, 'whatsapp_sent', $viewing, [
                'whatsapp_kind' => ['old' => null, 'new' => WhatsAppTemplates::kindLabel($kind)],
                'whatsapp_to' => ['old' => null, 'new' => $message->to_label],
            ]);
        }

        return $message;
    }
}
