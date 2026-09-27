<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** مسؤول لدى مالك العقار: الرقم + صفته (المالك/الوكيل/المدير…) + اسمه — يُختار منهم مسؤولو كل عقار */
class PropertyOwnerContact extends Model
{
    public const DEFAULT_ROLE = 'مسؤول العقار';

    protected $fillable = ['owner_id', 'phone_code', 'phone', 'role', 'name', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class, 'owner_id');
    }

    /** العقارات التي اختير مسؤولاً عنها */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_responsibles', 'contact_id', 'property_id');
    }

    public function getFullPhoneAttribute(): string
    {
        return PhoneNumber::format($this->phone_code, $this->phone);
    }

    public function getWhatsappNumberAttribute(): string
    {
        return PhoneNumber::digits($this->phone_code, $this->phone);
    }

    /** رقم بلا اسم ولا صفة يطابق رقم المالك الأساسي = المالك نفسه */
    public function isOwnerSelf(?PropertyOwner $owner): bool
    {
        return $owner !== null && ! $this->role && ! $this->name && (string) $this->phone === (string) $owner->phone;
    }

    /** «الوكيل · سالم» — ورقم المالك نفسه «المالك · اسمه» عند تمرير المالك */
    public function label(?PropertyOwner $owner = null): string
    {
        if ($this->isOwnerSelf($owner)) {
            return 'المالك · '.$owner->name;
        }

        return collect([$this->role ?: self::DEFAULT_ROLE, $this->name])->filter()->implode(' · ');
    }
}
