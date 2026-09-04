<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** رقم تواصل لمالك عقار: الرقم + صفة صاحبه (المالك/الوكيل…) + اسمه */
class PropertyOwnerContact extends Model
{
    protected $fillable = ['owner_id', 'phone_code', 'phone', 'role', 'name', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class, 'owner_id');
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
