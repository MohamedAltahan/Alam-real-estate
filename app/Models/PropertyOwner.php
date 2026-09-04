<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PropertyOwner extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** مجموعة ملفات المالك (صور · PDF · Word · Excel) */
    public const FILES = 'files';

    public const FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];

    public const MAX_FILE_KB = 15360;

    protected $fillable = [
        'name', 'phone_code', 'phone', 'email', 'area_id',
        'registered_address', 'notes',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** أرقام التواصل (رقم + صفته + اسمه) — أكثر من سطر */
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::FILES);
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

    /** ملفات المالك بصيغة جاهزة للواجهة (Alpine) */
    public function filePayload(): array
    {
        return $this->getMedia(self::FILES)->map(fn (Media $media) => [
            'id' => $media->id,
            'name' => $media->file_name,
            'size' => (int) $media->size,
            'url' => $media->getUrl(),
            'ext' => strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)),
        ])->values()->all();
    }
}
