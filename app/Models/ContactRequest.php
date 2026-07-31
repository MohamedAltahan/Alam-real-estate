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

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
