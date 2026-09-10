<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** مسؤول في زيارة ميدانية: الاسم + صفته (المالك/الوكيل…) + الهاتف */
class FieldOwnerContact extends Model
{
    protected $fillable = ['field_owner_id', 'name', 'role', 'phone_code', 'phone', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function fieldOwner(): BelongsTo
    {
        return $this->belongsTo(FieldOwner::class);
    }

    public function getFullPhoneAttribute(): string
    {
        return PhoneNumber::format($this->phone_code, $this->phone);
    }

    public function getWhatsappNumberAttribute(): string
    {
        return PhoneNumber::digits($this->phone_code, $this->phone);
    }
}
