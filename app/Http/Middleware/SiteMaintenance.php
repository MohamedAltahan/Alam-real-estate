<?php

namespace App\Http\Middleware;

use App\Support\SiteFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * وضع الصيانة للموقع العام فقط (لوحة التحكم وتسجيل الدخول لا تمر من هنا).
 * الزوار ومحركات البحث: 503 + Retry-After — جوجل يفهمه كتوقف مؤقت فلا يحذف الصفحات ولا يغيّر ترتيبها.
 * بلا noindex ولا تحويل: الاثنان يضرّان الفهرسة أكثر من التوقف نفسه.
 * فريق العمل المسجَّل دخوله يتصفح الموقع كالمعتاد مع شريط تنبيه (الخاصية site_maintenance).
 */
class SiteMaintenance
{
    /** متى يعيد الزاحف المحاولة (ثوانٍ) — التوقف المتوقَّع قصير */
    public const RETRY_AFTER = 3600;

    public function handle(Request $request, Closure $next): Response
    {
        $maintenance = SiteFlags::maintenance();

        if (! $maintenance) {
            return $next($request);
        }

        if ($request->user()) {
            $request->attributes->set('site_maintenance', $maintenance);

            return $next($request);
        }

        return response()->view('site.maintenance', ['preview' => false], 503, [
            'Retry-After' => (string) self::RETRY_AFTER,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
