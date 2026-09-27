<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/** أعلام الموقع العام (إعدادات عامة لكل الزوّار، تُدار من تبويب «التفضيلات») */
final class SiteFlags
{
    public const GROUP = 'site';

    /** شارة «مباع» الحمراء على كروت العقارات وصفحة العقار */
    public const BUSY_BADGE = 'busy_badge';

    /** وضع الصيانة: القيمة {since, by} أثناء الإيقاف، وnull والموقع يعمل */
    public const MAINTENANCE = 'maintenance';

    /** بعدها يُنبَّه المدير: التوقف الطويل قد يُسقط صفحات من فهرس جوجل */
    public const MAINTENANCE_WARN_HOURS = 48;

    /** تُقرأ من جدول الإعدادات مرة واحدة في الطلب (الكروت كثيرة في الصفحة الواحدة) */
    public static function busyBadgeEnabled(): bool
    {
        return once(fn () => (bool) Setting::get(self::GROUP, self::BUSY_BADGE, false));
    }

    /**
     * حالة وضع الصيانة الحالية — null إن كان الموقع يعمل.
     * بلا once(): الزر يغيّرها ثم يُقرأ الموقع في الطلب نفسه أثناء الاختبارات.
     *
     * @return array{since: Carbon, by: ?string}|null
     */
    public static function maintenance(): ?array
    {
        $value = Setting::get(self::GROUP, self::MAINTENANCE);

        if (! is_array($value) || blank($value['since'] ?? null)) {
            return null;
        }

        return ['since' => Carbon::parse($value['since']), 'by' => $value['by'] ?? null];
    }
}
