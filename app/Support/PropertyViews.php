<?php

namespace App\Support;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

/**
 * عدّاد مشاهدات صفحة العقار على الموقع: الزائر يُحسب مرة واحدة في اليوم لكل عقار.
 * لا يُحسب موظفو الشركة (أي مستخدم مسجّل الدخول) ولا البوتات ومعاينات الروابط (واتساب وغيره).
 */
final class PropertyViews
{
    /** كوكي اليوم: «2026-10-19|12,15,33» — أرقام العقارات التي شاهدها الزائر اليوم */
    public const COOKIE = 'viewed_properties';

    /** أقصى عدد عقارات تُحفظ في الكوكي حتى يبقى صغيراً */
    private const MAX_IDS = 150;

    private const BOT_PATTERN = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|skype|headless|lighthouse|curl|wget|python|httpclient|java\//i';

    public static function record(Request $request, Property $property): void
    {
        if ($request->user() || self::isBot($request->userAgent())) {
            return;
        }

        $today = now()->toDateString();
        $ids = self::viewedToday($request, $today);
        $id = (int) $property->getKey();

        if (in_array($id, $ids, true)) {
            return;
        }

        // استعلام مباشر: لا يحرّك updated_at ولا يمرّ على مراقبي النموذج (سجل النشاط)
        DB::table($property->getTable())->where('id', $id)->increment('views_count');

        $ids = array_slice([...$ids, $id], -self::MAX_IDS);
        $minutes = max(1, (int) ceil(now()->diffInMinutes(now()->endOfDay())));
        Cookie::queue(self::COOKIE, $today.'|'.implode(',', $ids), $minutes);
    }

    /** @return array<int, int> */
    private static function viewedToday(Request $request, string $today): array
    {
        [$date, $list] = array_pad(explode('|', (string) $request->cookie(self::COOKIE), 2), 2, '');

        if ($date !== $today || $list === '') {
            return [];
        }

        return array_map('intval', explode(',', $list));
    }

    private static function isBot(?string $userAgent): bool
    {
        return blank($userAgent) || preg_match(self::BOT_PATTERN, $userAgent) === 1;
    }
}
