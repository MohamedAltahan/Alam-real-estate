<?php

namespace App\Models;

use App\Concerns\InteractsWithWebImages;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;

/**
 * زيارة ميدانية لمالك عقار جديد (شاشة «ميداني»):
 * المسؤولون + المتابعة (طريقة التواصل/المرحلة) + بيانات العقار وموقعه + صوره،
 * وروابط التحويل إلى مالك حقيقي وعقار حقيقي.
 */
class FieldOwner extends Model implements HasMedia
{
    use InteractsWithWebImages;

    /** مجموعة صور العقار المرفوعة من الميدان */
    public const PHOTOS = 'photos';

    protected $fillable = [
        'name', 'phone_code', 'phone', 'contact_method', 'stage', 'notes',
        'property_number', 'city_id', 'area_id', 'address', 'latitude', 'longitude',
        'created_by', 'converted_owner_id', 'converted_property_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /** المسؤولون (الاسم + صفته + الهاتف) — أكثر من سطر */
    public function contacts(): HasMany
    {
        return $this->hasMany(FieldOwnerContact::class)->orderBy('sort_order')->orderBy('id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** المندوب الميداني الذي سجّل الزيارة */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function convertedOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class, 'converted_owner_id');
    }

    public function convertedProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'converted_property_id');
    }

    public function isOwnerConverted(): bool
    {
        return ! empty($this->converted_owner_id);
    }

    public function isPropertyConverted(): bool
    {
        return ! empty($this->converted_property_id);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** رابط الموقع على خرائط جوجل من الإحداثيات */
    public function mapsUrl(): ?string
    {
        if (! $this->hasLocation()) {
            return null;
        }

        return 'https://www.google.com/maps?q='.(float) $this->latitude.','.(float) $this->longitude;
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
}
