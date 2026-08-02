<?php

namespace App\Models;

use App\Concerns\InteractsWithWebImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Translatable\HasTranslations;

class Property extends Model implements HasMedia
{
    use HasTranslations;
    use InteractsWithWebImages;

    protected $table = 'properties';

    protected $fillable = [
        'reference_code', 'title', 'short_description', 'description', 'specifications',
        'area_id', 'category_id', 'unit_type_id', 'purpose', 'price', 'price_period',
        'status_id', 'owner_id', 'agent_id', 'bedrooms', 'bathrooms', 'area_size',
        'block', 'street', 'building', 'latitude', 'longitude',
        'video_url', 'is_featured', 'rating', 'reviews_count',
    ];

    /** حقول قابلة للترجمة AR/EN */
    public array $translatable = ['title', 'short_description', 'description', 'specifications'];

    protected $casts = [
        'price' => 'decimal:3',
        'area_size' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'rating' => 'decimal:2',
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
        'reviews_count' => 'integer',
        'is_featured' => 'boolean',
    ];

    // ===== العلاقات =====

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'category_id');
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(PropertyStatus::class, 'status_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class, 'owner_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class, 'property_amenity');
    }

    /** تقييمات العقار (polymorphic) */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /** العملاء المرتبطين بهذا العقار */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_property')
            ->withPivot('relation', 'notes')
            ->withTimestamps();
    }

    // ===== Accessors =====

    /** معرّف فيديو يوتيوب (للتشغيل داخل الموقع بدل التحويل لليوتيوب) */
    public function getVideoIdAttribute(): ?string
    {
        return \App\Support\Video::youtubeId($this->video_url);
    }

    /** غلاف العقار (media library) */
    public function getCoverUrlAttribute(): ?string
    {
        return $this->imageUrl('cover');
    }

    /** كل صور العقار: الغلاف أولاً ثم المعرض */
    public function getGalleryUrlsAttribute(): array
    {
        $conv = self::WEB_CONVERSION;
        $url = fn ($m) => $m->hasGeneratedConversion($conv) ? $m->getUrl($conv) : $m->getUrl();

        return $this->getMedia('cover')->merge($this->getMedia('gallery'))->map($url)->values()->all();
    }

    /** صورة الفيديو من يوتيوب — أدقّ من صورة غلاف العقار في قسم الفيديوهات */
    public function getVideoThumbAttribute(): ?string
    {
        return $this->video_id ? "https://img.youtube.com/vi/{$this->video_id}/hqdefault.jpg" : null;
    }

    /** العقارات التي لها فيديو يوتيوب صالح */
    public function scopeWithVideo($query)
    {
        // ملاحظة أوراكل: النص الفارغ = NULL، لذا whereNotNull وحده يكفي
        return $query->whereNotNull('video_url');
    }

    // ===== Scopes =====

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
