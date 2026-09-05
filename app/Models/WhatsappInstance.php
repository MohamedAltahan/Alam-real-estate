<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** الرقم المربوط ببوابة واتساب (جلسة واحدة للمكتب) */
class WhatsappInstance extends Model
{
    public const CONNECTED = 'connected';

    public const STATUSES = [
        'created' => 'جارٍ التجهيز',
        'qr' => 'بانتظار المسح',
        'connected' => 'متصل',
        'disconnected' => 'غير متصل',
    ];

    protected $fillable = ['external_id', 'name', 'phone', 'status', 'connected_at', 'checked_at', 'last_event'];

    protected $casts = [
        'connected_at' => 'datetime',
        'checked_at' => 'datetime',
        'last_event' => 'array',
    ];

    public function isConnected(): bool
    {
        return $this->status === self::CONNECTED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
