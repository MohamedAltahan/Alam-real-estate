<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * قناة نشر: موقع إلكتروني (OLX…) أو قناة سوشال ميديا (فيسبوك، إنستجرام…).
 * يُربط بها العقار مع رابط الإعلان لمعرفة أين نُشر.
 */
class PublishingChannel extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const KIND_WEBSITE = 'website';

    public const KIND_SOCIAL = 'social';

    public const KINDS = [
        self::KIND_WEBSITE => 'المواقع الإلكترونية',
        self::KIND_SOCIAL => 'السوشال ميديا',
    ];

    /** الشعار: صورة أو SVG بلا تحويلات (المكتبة لا تعالج SVG) */
    public const ICON_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

    public const ICON_MAX_KB = 2048;

    protected $fillable = ['kind', 'name', 'url', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')->singleFile();
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_channel', 'channel_id', 'property_id')
            ->withPivot('url')
            ->withTimestamps();
    }

    public function scopeKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getIconUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('icon') ?: null;
    }

    public static function kindLabel(string $kind): string
    {
        return self::KINDS[$kind] ?? $kind;
    }
}
