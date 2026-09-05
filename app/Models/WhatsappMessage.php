<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** رسالة واتساب أُرسلت (أو فشلت) عبر البوابة — سجل دائم لكل محاولة */
class WhatsappMessage extends Model
{
    public const QUEUED = 'queued';

    public const FAILED = 'failed';

    public const STATUSES = [
        self::QUEUED => 'أُرسلت',
        self::FAILED => 'فشلت',
    ];

    protected $fillable = [
        'kind', 'viewing_id', 'client_id', 'to_phone', 'to_label',
        'body', 'status', 'provider_id', 'error', 'sent_by',
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

    public function succeeded(): bool
    {
        return $this->status === self::QUEUED;
    }
}
