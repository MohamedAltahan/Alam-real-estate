<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * رسالة واتساب أُرسلت (أو فشلت) عبر البوابة — سجل دائم لكل محاولة،
 * مع حالة التسليم كما في واتساب: قيد الإرسال → أُرسلت → وصلت → قُرئت (أو فشلت).
 */
class WhatsappMessage extends Model
{
    public const QUEUED = 'queued';

    public const SENDING = 'sending';

    public const SENT = 'sent';

    public const DELIVERED = 'delivered';

    public const READ = 'read';

    public const FAILED = 'failed';

    public const STATUSES = [
        self::QUEUED => 'قيد الإرسال',
        self::SENDING => 'جارٍ الإرسال',
        self::SENT => 'أُرسلت',
        self::DELIVERED => 'وصلت',
        self::READ => 'قُرئت',
        self::FAILED => 'فشلت',
    ];

    /** ترتيب التقدّم — حدث متأخر من البوابة لا يُرجع الحالة للخلف، والفشل نهائي لا يُنقض */
    public const ORDER = [
        self::QUEUED => 0,
        self::SENDING => 1,
        self::SENT => 2,
        self::DELIVERED => 3,
        self::READ => 4,
        self::FAILED => 5,
    ];

    /** حالات نهائية لا تُسأل البوابة عنها مجدداً */
    public const FINAL = [self::READ, self::FAILED];

    /** عمود الوقت الذي يُضبط عند بلوغ كل حالة */
    public const STAMPS = [
        self::SENT => 'sent_at',
        self::DELIVERED => 'delivered_at',
        self::READ => 'read_at',
    ];

    protected $fillable = [
        'kind', 'viewing_id', 'client_id', 'to_phone', 'to_label',
        'body', 'status', 'provider_id', 'wa_message_id', 'error', 'sent_by',
        'sent_at', 'delivered_at', 'read_at', 'status_checked_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'status_checked_at' => 'datetime',
    ];

    public function viewing(): BelongsTo
    {
        return $this->belongsTo(ClientViewing::class, 'viewing_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** قبلتها البوابة (أي حالة غير الفشل) */
    public function succeeded(): bool
    {
        return $this->status !== self::FAILED;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** آخر وقت معروف للحالة الحالية (يُعرض تحت الشارة) */
    public function statusAt(): ?Carbon
    {
        return $this->read_at ?? $this->delivered_at ?? $this->sent_at;
    }
}
