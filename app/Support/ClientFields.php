<?php

namespace App\Support;

use App\Models\Area;
use App\Models\City;
use App\Models\ClientStage;
use App\Models\ClientType;
use App\Models\MarketingSource;
use App\Models\Property;
use App\Models\UnitType;
use App\Models\User;

/**
 * المصدر الوحيد لتسميات حقول العميل وقيمها الثابتة — يُستخدم في الفورم والعرض
 * وسجل التعديلات ونص المشاركة، بدل تكرار الخرائط في كل شاشة.
 */
final class ClientFields
{
    public const CONTACT_METHODS = [
        'whatsapp' => 'واتساب',
        'call' => 'اتصال',
        'email' => 'بريد إلكتروني',
    ];

    public const SOCIAL_STATUSES = [
        'single' => 'أعزب',
        'married' => 'متزوج',
        'family' => 'عائلة',
        'company' => 'شركات',
    ];

    public const INTERACTION_TYPES = [
        'call' => 'مكالمة',
        'meeting' => 'مقابلة',
        'whatsapp' => 'واتساب',
        'email' => 'بريد إلكتروني',
        'note' => 'ملاحظة',
    ];

    public const RELATIONS = [
        'interested' => 'مهتم',
        'viewed' => 'تمت المعاينة',
    ];

    public const OUTCOMES = [
        'pending' => 'قيد الانتظار',
        'chosen' => 'اختار العقار',
        'rejected' => 'لم يختر',
    ];

    /** ألوان شارة النتيجة (Tailwind) */
    public const OUTCOME_TONES = [
        'pending' => 'bg-warning-soft text-warning',
        'chosen' => 'bg-success-soft text-success',
        'rejected' => 'bg-danger/10 text-danger',
    ];

    /** تسمية كل عمود (عميل / احتياج / معاينة / تواصل) لسجل التعديلات والفورم */
    public const LABELS = [
        'name' => 'الاسم',
        'phone_code' => 'مفتاح الدولة',
        'phone' => 'رقم الهاتف',
        'email' => 'البريد الإلكتروني',
        'preferred_contact' => 'طريقة التواصل',
        'nationality' => 'الجنسية',
        'social_status' => 'الحالة الاجتماعية',
        'household_size' => 'عدد الأفراد',
        'workplace' => 'مكان العمل',
        'stage_id' => 'الحالة',
        'agent_id' => 'مندوب المبيعات',
        'type_id' => 'نوع العميل',
        'source_id' => 'مصدر التسويق',
        'rating' => 'التقييم',
        'notes' => 'الملاحظات',
        'recorded_by' => 'سجّل البيانات',
        'unit_type_id' => 'نوع الوحدة',
        'city_id' => 'المحافظة',
        'area_id' => 'المنطقة',
        'property_id' => 'العقار',
        'scheduled_at' => 'موعد المعاينة',
        'in_person' => 'حضوري',
        'outcome' => 'النتيجة',
        'outcome_at' => 'تاريخ النتيجة',
        'relation' => 'نوع الارتباط',
        'type' => 'نوع التواصل',
        'occurred_at' => 'التاريخ',
        'user_id' => 'الموظف',
        'file' => 'الملف',
    ];

    /** الأعمدة المرجعية → الموديل الذي يُقرأ منه الاسم عند العرض */
    public const FOREIGN = [
        'stage_id' => ClientStage::class,
        'agent_id' => User::class,
        'recorded_by' => User::class,
        'user_id' => User::class,
        'type_id' => ClientType::class,
        'source_id' => MarketingSource::class,
        'unit_type_id' => UnitType::class,
        'city_id' => City::class,
        'area_id' => Area::class,
        'property_id' => Property::class,
    ];

    public const ENUMS = [
        'preferred_contact' => self::CONTACT_METHODS,
        'social_status' => self::SOCIAL_STATUSES,
        'outcome' => self::OUTCOMES,
        'relation' => self::RELATIONS,
        'type' => self::INTERACTION_TYPES,
    ];

    public const BOOLEANS = ['in_person'];

    public const AUDIT_ACTIONS = [
        'created' => 'إنشاء العميل',
        'updated' => 'تعديل البيانات',
        'deleted' => 'حذف العميل',
        'need_added' => 'إضافة احتياج عقار',
        'need_updated' => 'تعديل احتياج عقار',
        'need_removed' => 'حذف احتياج عقار',
        'viewing_added' => 'إضافة معاينة',
        'viewing_updated' => 'تعديل معاينة',
        'viewing_removed' => 'حذف معاينة',
        'outcome_updated' => 'تحديث نتيجة المعاينة',
        'property_attached' => 'ربط عقار',
        'property_detached' => 'إلغاء ربط عقار',
        'property_reserved' => 'حجز عقار',
        'reservation_released' => 'إلغاء حجز عقار',
        'interaction_logged' => 'تسجيل تواصل',
        'file_added' => 'إضافة ملف',
        'file_removed' => 'حذف ملف',
    ];

    public static function label(string $field): string
    {
        return self::LABELS[$field] ?? $field;
    }

    /** تسمية قيمة ثابتة (مثل preferred_contact=whatsapp → واتساب) */
    public static function enumLabel(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::ENUMS[$field][$value] ?? (string) $value;
    }

    public static function outcomeLabel(?string $outcome): string
    {
        return self::OUTCOMES[$outcome] ?? (string) $outcome;
    }

    public static function outcomeTone(?string $outcome): string
    {
        return self::OUTCOME_TONES[$outcome] ?? 'bg-gray-100 text-gray-500';
    }

    public static function actionLabel(string $action): string
    {
        return self::AUDIT_ACTIONS[$action] ?? $action;
    }

    public static function isForeign(string $field): bool
    {
        return isset(self::FOREIGN[$field]);
    }
}
