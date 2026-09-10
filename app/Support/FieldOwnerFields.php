<?php

namespace App\Support;

/**
 * المصدر الوحيد لقيم شاشة «ميداني» الثابتة: طرق التواصل، المراحل، وألوان الشارات.
 */
final class FieldOwnerFields
{
    public const CONTACT_METHODS = [
        'call' => 'اتصال',
        'visit' => 'زيارة',
        'referral' => 'ترشيح',
    ];

    public const DEFAULT_METHOD = 'visit';

    public const STAGES = [
        'new' => 'مالك جديد',
        'potential' => 'محتمل',
        'meeting' => 'اجتماع',
        'won' => 'ربح',
        'lost' => 'خسارة',
    ];

    public const DEFAULT_STAGE = 'new';

    /** ألوان شارة المرحلة (Tailwind) */
    public const STAGE_TONES = [
        'new' => 'bg-info-soft text-info',
        'potential' => 'bg-warning-soft text-warning',
        'meeting' => 'bg-primary-50 text-primary-700',
        'won' => 'bg-success-soft text-success',
        'lost' => 'bg-danger/10 text-danger',
    ];

    public static function stageLabel(?string $stage): string
    {
        return self::STAGES[$stage] ?? ($stage ?: '—');
    }

    public static function stageTone(?string $stage): string
    {
        return self::STAGE_TONES[$stage] ?? 'bg-gray-100 text-gray-500';
    }

    public static function methodLabel(?string $method): string
    {
        return self::CONTACT_METHODS[$method] ?? ($method ?: '—');
    }
}
