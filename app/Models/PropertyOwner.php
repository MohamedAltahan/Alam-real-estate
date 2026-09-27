<?php

namespace App\Models;

use App\Models\Concerns\HasAttachedFiles;
use App\Observers\ActivityObserver;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy([ActivityObserver::class])]
class PropertyOwner extends Model implements HasMedia
{
    use HasAttachedFiles;

    /** مجموعة ملفات المالك (صور · PDF · Word · Excel) */
    public const FILES = 'files';

    protected $fillable = [
        'name', 'phone_code', 'phone', 'email', 'area_id',
        'registered_address', 'notes', 'mobile_code', 'mobile',
    ];

    /** رقم موبايل المالك للعرض في صفحته فقط — لا يدخل في أي تحويل JSON/مصفوفة */
    protected $hidden = ['mobile_code', 'mobile'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** المسؤولون (رقم + صفته + اسمه) — أولهم رقم المالك الأساسي، ومنهم يُختار مسؤولو كل عقار */
    public function contacts(): HasMany
    {
        return $this->hasMany(PropertyOwnerContact::class, 'owner_id')->orderBy('sort_order')->orderBy('id');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'owner_id');
    }

    /** أحدث عقار للمالك — منه نعرف مندوب المبيعات في القائمة */
    public function latestProperty(): HasOne
    {
        return $this->hasOne(Property::class, 'owner_id')->latestOfMany();
    }

    // ===== Accessors =====

    /** "+965 55112233" */
    public function getFullPhoneAttribute(): string
    {
        return PhoneNumber::format($this->phone_code, $this->phone);
    }

    /** أرقام فقط لرابط واتساب */
    public function getWhatsappNumberAttribute(): string
    {
        return PhoneNumber::digits($this->phone_code, $this->phone);
    }

    /** موبايل المالك نفسه «+965 99112233» — للعرض في صفحة المالك فقط */
    public function getFullMobileAttribute(): string
    {
        return filled($this->mobile) ? PhoneNumber::format($this->mobile_code, $this->mobile) : '';
    }
}
