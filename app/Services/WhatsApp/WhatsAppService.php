<?php

namespace App\Services\WhatsApp;

use App\Models\ClientViewing;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\ClientAuditLogger;
use App\Support\PhoneNumber;
use App\Support\WhatsAppTemplates;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
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

    // ===== حالة التسليم (أُرسلت · وصلت · قُرئت) =====

    /** الرسائل المعلّقة تُسأل البوابة عنها لهذه المدة فقط */
    public const STATUS_WINDOW_DAYS = 7;

    /** أقصى عدد رسائل يُستعلم عنها في الدورة الواحدة */
    public const STATUS_BATCH = 15;

    /** لا نعيد سؤال البوابة عن الرسالة نفسها قبل هذه الثواني */
    public const STATUS_RECHECK_SECONDS = 45;

    /** حالات البوابة (وأسماء واتساب الشائعة) → حالاتنا */
    public const MESSAGE_STATUS_MAP = [
        'queued' => WhatsappMessage::QUEUED,
        'pending' => WhatsappMessage::QUEUED,
        'sending' => WhatsappMessage::SENDING,
        'sent' => WhatsappMessage::SENT,
        'server_ack' => WhatsappMessage::SENT,
        'delivered' => WhatsappMessage::DELIVERED,
        'device_ack' => WhatsappMessage::DELIVERED,
        'read' => WhatsappMessage::READ,
        'played' => WhatsappMessage::READ,
        'failed' => WhatsappMessage::FAILED,
        'error' => WhatsappMessage::FAILED,
    ];

    /**
     * ويب هوك البوابة: {event: "message.status", data: {message_id, status, error, wa_message_id}, sent_at}.
     * يُعاد true عندما تخص الرسالة سجلاً عندنا.
     */
    public function handleWebhook(array $payload): bool
    {
        if (($payload['event'] ?? null) !== 'message.status') {
            return false;
        }

        $data = (array) ($payload['data'] ?? []);
        $providerId = $data['message_id'] ?? $data['id'] ?? null;

        if ($providerId === null || $providerId === '') {
            return false;
        }

        $message = WhatsappMessage::query()->where('provider_id', (string) $providerId)->first();

        if (! $message) {
            return false;
        }

        $at = filled($payload['sent_at'] ?? null) ? $this->gatewayTime($payload['sent_at']) : now();
        $this->applyMessageStatus($message, $data, $at);

        return true;
    }

    /**
     * استعلام البوابة عن الرسائل التي لم تبلغ حالتها النهائية (من الاستطلاع الدوري، الأمر المجدول،
     * أو زر «تحديث الحالات»). يُعاد عدد الرسائل التي تغيّرت حالتها.
     */
    public function refreshMessageStatuses(bool $force = false): int
    {
        if (! $this->client->isConfigured()) {
            return 0;
        }

        // الاستطلاع يأتي من كل متصفح مفتوح — دورة واحدة في الوقت نفسه تكفي
        if (! $force) {
            $lock = Cache::lock('whatsapp.status-refresh', self::STATUS_RECHECK_SECONDS);

            if (! $lock->get()) {
                return 0;
            }
        }

        $pending = WhatsappMessage::query()
            ->whereNotNull('provider_id')
            ->whereNotIn('status', WhatsappMessage::FINAL)
            ->where('created_at', '>=', now()->subDays(self::STATUS_WINDOW_DAYS))
            ->when(! $force, fn ($q) => $q->where(fn ($q) => $q
                ->whereNull('status_checked_at')
                ->orWhere('status_checked_at', '<', now()->subSeconds(self::STATUS_RECHECK_SECONDS))))
            ->orderByRaw('CASE WHEN '.WhatsappMessage::query()->getGrammar()->wrap('status_checked_at').' IS NULL THEN 0 ELSE 1 END')
            ->orderBy('status_checked_at')
            ->orderBy('id')
            ->limit(self::STATUS_BATCH)
            ->get();

        $updated = 0;

        foreach ($pending as $message) {
            try {
                if ($this->applyMessageStatus($message, $this->client->messageStatus((string) $message->provider_id))) {
                    $updated++;
                }
            } catch (ConnectionException $e) {
                // البوابة نفسها غير متاحة: نتوقف ونحاول في الدورة التالية
                Log::warning('whatsapp message status: '.$e->getMessage());
                break;
            } catch (\Throwable $e) {
                Log::warning('whatsapp message status #'.$message->provider_id.': '.KhabeerSoftClient::errorMessage($e));
                $message->forceFill(['status_checked_at' => now()])->save();
            }
        }

        return $updated;
    }

    /**
     * تطبيق حالة من البوابة على الرسالة: لا نرجع للخلف، ونضبط أزمنة المراحل (من البوابة أو وقت الحدث).
     * يُعاد true عندما تتغيّر الحالة.
     */
    public function applyMessageStatus(WhatsappMessage $message, array $data, ?CarbonInterface $at = null): bool
    {
        $at ??= now();
        $incoming = self::MESSAGE_STATUS_MAP[strtolower((string) ($data['status'] ?? ''))] ?? null;
        $changes = ['status_checked_at' => now()];

        if (filled($data['wa_message_id'] ?? null) && ! $message->wa_message_id) {
            $changes['wa_message_id'] = mb_substr((string) $data['wa_message_id'], 0, 100);
        }

        // الأزمنة كما ترسلها البوابة
        foreach (WhatsappMessage::STAMPS as $column) {
            if (filled($data[$column] ?? null) && ! $message->{$column}) {
                $changes[$column] = $this->gatewayTime($data[$column]);
            }
        }

        $changed = false;
        $becameFailed = false;

        if ($incoming === WhatsappMessage::FAILED) {
            $changed = $becameFailed = $message->status !== WhatsappMessage::FAILED;
            $changes['status'] = WhatsappMessage::FAILED;
            $changes['error'] = mb_substr((string) (($data['error'] ?? null) ?: 'فشل الإرسال من البوابة'), 0, 500);
        } elseif ($incoming !== null && (WhatsappMessage::ORDER[$incoming] ?? -1) > (WhatsappMessage::ORDER[$message->status] ?? -1)) {
            $changed = true;
            $changes['status'] = $incoming;
            $changes['error'] = null;

            // بلوغ «قُرئت» يعني أنها أُرسلت ووصلت أيضاً — نملأ الأزمنة الناقصة بوقت الحدث
            foreach (WhatsappMessage::STAMPS as $status => $column) {
                if (WhatsappMessage::ORDER[$status] <= WhatsappMessage::ORDER[$incoming] && ! $message->{$column} && ! isset($changes[$column])) {
                    $changes[$column] = $at;
                }
            }
        }

        $message->forceFill($changes)->save();

        if ($becameFailed) {
            $this->releaseViewingMark($message);
        }

        return $changed;
    }

    /**
     * فشل رسالة معاينة بعد قبولها (ويب هوك/استطلاع): تُمسح علامة المعاينة التي ضُبطت عند الإرسال،
     * ما لم توجد رسالة أخرى غير فاشلة من النوع نفسه فتأخذ العلامة وقتها. ويُكتب سطر في سجل العميل.
     */
    private function releaseViewingMark(WhatsappMessage $message): void
    {
        $mark = WhatsAppTemplates::MARKS[$message->kind] ?? null;
        $viewing = $mark && $message->viewing_id ? $message->viewing : null;

        if (! $viewing) {
            return;
        }

        $other = WhatsappMessage::query()
            ->where('viewing_id', $viewing->id)
            ->where('kind', $message->kind)
            ->whereKeyNot($message->id)
            ->where('status', '!=', WhatsappMessage::FAILED)
            ->latest('id')
            ->first();

        $viewing->forceFill([$mark => $other?->created_at])->save();

        $this->audit->record($viewing->client, 'whatsapp_failed', $viewing, [
            'whatsapp_kind' => ['old' => null, 'new' => WhatsAppTemplates::kindLabel($message->kind)],
            'whatsapp_to' => ['old' => null, 'new' => $message->to_label],
            'whatsapp_error' => ['old' => null, 'new' => $message->error],
        ]);
    }

    /** أوقات البوابة (ISO بتوقيت UTC) → توقيت التطبيق */
    private function gatewayTime(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }

    // ===== رصيد الباقة (المُرسل والمتبقي) =====

    public const USAGE_CACHE = 'whatsapp.usage';

    /** الرصيد يُسأل مرة كل دقيقتين على الأكثر مهما تكرر فتح الصفحة */
    public const USAGE_TTL = 120;

    /** فترات الاستهلاك التي تعرضها البوابة */
    public const USAGE_PERIODS = [
        'monthly' => 'هذا الشهر',
        'daily' => 'اليوم',
    ];

    /**
     * رصيد رسائل الباقة من البوابة (للحساب كله لا للرقم الواحد).
     *
     * @return array{available:bool, error:?string, periods:array<int, array{key:string, label:string, used:int, limit:?int, remaining:?int, percentage:int}>}
     */
    public function usage(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::USAGE_CACHE);
        }

        return Cache::remember(self::USAGE_CACHE, self::USAGE_TTL, function () {
            if (! $this->client->isConfigured()) {
                return ['available' => false, 'error' => null, 'periods' => []];
            }

            try {
                $periods = $this->normalizeUsage($this->client->usage());
            } catch (\Throwable $e) {
                $error = $e instanceof ConnectionException ? $e->getMessage() : KhabeerSoftClient::errorMessage($e);
                Log::warning('whatsapp usage: '.$error);

                return ['available' => false, 'error' => $error, 'periods' => []];
            }

            return ['available' => (bool) $periods, 'error' => null, 'periods' => $periods];
        });
    }

    /** رد البوابة → أسطر جاهزة للعرض (المتبقي والنسبة يُحسبان إن لم تُرسلهما) */
    private function normalizeUsage(array $data): array
    {
        $periods = [];

        foreach (self::USAGE_PERIODS as $key => $label) {
            $row = (array) ($data[$key] ?? []);

            if (! $row) {
                continue;
            }

            $used = (int) ($row['used'] ?? 0);
            $limit = isset($row['limit']) && $row['limit'] !== null ? (int) $row['limit'] : null;

            $remaining = isset($row['remaining']) && $row['remaining'] !== null
                ? (int) $row['remaining']
                : ($limit !== null ? max(0, $limit - $used) : null);

            $percentage = isset($row['percentage']) && $row['percentage'] !== null
                ? (int) round((float) $row['percentage'])
                : ($limit > 0 ? (int) round($used / $limit * 100) : 0);

            $periods[] = [
                'key' => $key,
                'label' => $label,
                'used' => $used,
                'limit' => $limit,
                'remaining' => $remaining,
                'percentage' => max(0, min(100, $percentage)),
            ];
        }

        return $periods;
    }
}
