<?php

namespace App\Support;

use App\Models\Setting;

/** أعلام الموقع العام (إعدادات عامة لكل الزوّار، تُدار من تبويب «التفضيلات») */
final class SiteFlags
{
    public const GROUP = 'site';

    /** شارة «مشغول / مباع» الحمراء على كروت العقارات وصفحة العقار */
    public const BUSY_BADGE = 'busy_badge';

    /** تُقرأ من جدول الإعدادات مرة واحدة في الطلب (الكروت كثيرة في الصفحة الواحدة) */
    public static function busyBadgeEnabled(): bool
    {
        return once(fn () => (bool) Setting::get(self::GROUP, self::BUSY_BADGE, false));
    }
}
