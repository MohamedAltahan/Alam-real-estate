<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactRequest extends Model
{
    /**
     * حقول الإدخال العام فقط — status / is_read / handled_by تُدار من الداشبورد،
     * مش من فورم الموقع (حماية من mass-assignment).
     */
    protected $fillable = [
        'name', 'phone', 'email', 'request_type_id', 'subject', 'message', 'property_id',
    ];

    protected $casts = ['is_read' => 'boolean'];

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** العميل الذي أُنشئ من هذا الطلب (بعد التحويل إلى الـ CRM) */
    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function isConverted(): bool
    {
        return $this->converted_client_id !== null;
    }

    /**
     * تعليم الطلب كمتواصَل معه.
     * ملاحظة: status / is_read / handled_by ليست في $fillable عمداً (حماية من
     * mass-assignment عبر فورم الموقع)، لذلك تُضبط هنا مباشرة لا عبر update().
     */
    public function markContacted(?int $userId = null): void
    {
        $this->status = 'contacted';
        $this->is_read = true;
        $this->handled_by = $userId ?? $this->handled_by;
        $this->save();
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
