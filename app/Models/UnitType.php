<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class UnitType extends Model
{
    use HasTranslations;

    public const RESIDENTIAL = 'residential';

    public const COMMERCIAL = 'commercial';

    /** التصنيف: يحدد أنواع الوحدات المتاحة حسب تصنيف العقار */
    public const CATEGORIES = [
        self::RESIDENTIAL => 'سكني',
        self::COMMERCIAL => 'تجاري',
    ];

    protected $fillable = ['name', 'category', 'sort_order', 'is_active'];

    public array $translatable = ['name'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeCategory($query, ?string $category)
    {
        return $category ? $query->where('category', $category) : $query;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ($this->category ?? '');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class, 'unit_type_id');
    }

    /** احتياجات العملاء التي تطلب هذا النوع */
    public function needs(): HasMany
    {
        return $this->hasMany(ClientPropertyNeed::class, 'unit_type_id');
    }
}
