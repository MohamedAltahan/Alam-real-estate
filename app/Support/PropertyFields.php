<?php

namespace App\Support;

use App\Models\Area;
use App\Models\City;
use App\Models\PropertyCategory;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;

/** تسميات حقول العقار وقيمه الثابتة — لسجل النشاط وأي عرض نصي للتغييرات */
final class PropertyFields
{
    public const PURPOSES = [
        'sale' => 'بيع',
        'rent' => 'إيجار',
    ];

    public const PRICE_PERIODS = [
        'monthly' => 'شهري',
        'yearly' => 'سنوي',
    ];

    public const LABELS = [
        'reference_code' => 'الرقم المرجعي',
        'title' => 'اسم العقار',
        'short_description' => 'الوصف المختصر',
        'description' => 'الوصف',
        'specifications' => 'المواصفات',
        'city_id' => 'المحافظة',
        'area_id' => 'المنطقة',
        'category_id' => 'التصنيف',
        'unit_type_id' => 'نوع الوحدة',
        'purpose' => 'الغرض',
        'price' => 'السعر',
        'price_period' => 'فترة السعر',
        'status_id' => 'الحالة',
        'owner_id' => 'المالك',
        'agent_id' => 'مندوب المبيعات',
        'bedrooms' => 'غرف النوم',
        'bathrooms' => 'الحمّامات',
        'area_size' => 'المساحة (م²)',
        'block' => 'القطعة',
        'street' => 'الشارع',
        'building' => 'رقم المبنى',
        'building_name' => 'اسم المبنى',
        'latitude' => 'خط العرض',
        'longitude' => 'خط الطول',
        'map_url' => 'رابط الخريطة',
        'video_url' => 'رابط الفيديو',
        'is_featured' => 'مميز',
        'is_furnished' => 'مفروش',
        'owner_commission_rate' => 'عمولة المالك (%)',
        'rating' => 'التقييم',
        'reviews_count' => 'عدد التقييمات',
    ];

    public const FOREIGN = [
        'city_id' => City::class,
        'area_id' => Area::class,
        'category_id' => PropertyCategory::class,
        'unit_type_id' => UnitType::class,
        'status_id' => PropertyStatus::class,
        'owner_id' => PropertyOwner::class,
        'agent_id' => User::class,
    ];

    public const ENUMS = [
        'purpose' => self::PURPOSES,
        'price_period' => self::PRICE_PERIODS,
    ];

    public const BOOLEANS = ['is_featured', 'is_furnished'];

    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? $field;
    }
}
