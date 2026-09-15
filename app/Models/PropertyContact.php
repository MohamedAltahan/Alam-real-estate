<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** مسؤول عن العقار: الرقم + صفته (الحارس/الوكيل/المدير…) + اسمه — إليه تُرسل رسائل المعاينة */
class PropertyContact extends Model
{
    public const DEFAULT_ROLE = 'مسؤول العقار';

    protected $fillable = ['property_id', 'phone_code', 'phone', 'role', 'name', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function getFullPhoneAttribute(): string
    {
        return PhoneNumber::format($this->phone_code, $this->phone);
    }

    public function getWhatsappNumberAttribute(): string
    {
        return PhoneNumber::digits($this->phone_code, $this->phone);
    }

    /** «الحارس · أبو خالد» */
    public function label(): string
    {
        return collect([$this->role ?: self::DEFAULT_ROLE, $this->name])->filter()->implode(' · ');
    }
}
