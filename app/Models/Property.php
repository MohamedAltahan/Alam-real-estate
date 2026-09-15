<?php

namespace App\Models;

use App\Concerns\InteractsWithWebImages;
use App\Observers\ActivityObserver;
use App\Observers\PropertyObserver;
use App\Support\SiteFlags;
use App\Support\Video;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Translatable\HasTranslations;

#[ObservedBy([PropertyObserver::class, ActivityObserver::class])]
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
        'city_id', 'map_url', 'building_name', 'owner_commission_rate',
        'is_furnished',
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
        'is_furnished' => 'boolean',
        'owner_commission_rate' => 'decimal:2',
        // تاريخ البيع الفعلي — يضبطه PropertyObserver عند الانتقال إلى «مباع» (ليس fillable)
        'sold_at' => 'datetime',
    ];

    // ===== العلاقات =====

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** المحافظة */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
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

    /** معاينات العملاء لهذا العقار */
    public function viewings(): HasMany
    {
        return $this->hasMany(ClientViewing::class);
    }

    /** المسؤولون عن العقار (رقم + صفته + اسمه) — إليهم تُرسل رسائل المعاينة */
    public function contacts(): HasMany
    {
        return $this->hasMany(PropertyContact::class)->orderBy('sort_order')->orderBy('id');
    }

    /** قنوات النشر التي نُشر عليها العقار (مع رابط الإعلان) */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(PublishingChannel::class, 'property_channel', 'property_id', 'channel_id')
            ->withPivot('url')
            ->withTimestamps()
            ->orderBy('publishing_channels.sort_order');
    }

    public function websites(): BelongsToMany
    {
        return $this->channels()->where('publishing_channels.kind', PublishingChannel::KIND_WEBSITE);
    }

    public function socialChannels(): BelongsToMany
    {
        return $this->channels()->where('publishing_channels.kind', PublishingChannel::KIND_SOCIAL);
    }

    // ===== Accessors =====

    /** معرّف فيديو يوتيوب (للتشغيل داخل الموقع بدل التحويل لليوتيوب) */
    public function getVideoIdAttribute(): ?string
    {
        return Video::youtubeId($this->video_url);
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

    /** «مبنى: برج السالمية» — يُعرض بجانب الرقم المرجعي أينما ظهر العقار */
    public function buildingLabel(): ?string
    {
        return filled($this->building_name) ? 'مبنى: '.$this->building_name : null;
    }

    /** «12 — مبنى: برج السالمية» (نص عادي للقوائم والبحث) */
    public function codeLabel(): string
    {
        return trim(($this->reference_code ?: '#'.$this->id).($this->buildingLabel() ? ' — '.$this->buildingLabel() : ''));
    }

    public function isSold(): bool
    {
        return $this->status?->key === 'sold';
    }

    /**
     * شارة الموقع العام: «مباع» — لا تظهر إلا عندما يكون الإعداد العام مفعّلاً.
     *
     * @return array{key:string, ar:string, en:string}|null
     */
    public function publicBadge(): ?array
    {
        if (! SiteFlags::busyBadgeEnabled()) {
            return null;
        }

        return $this->isSold() ? ['key' => 'sold', 'ar' => 'مباع', 'en' => 'Sold'] : null;
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
