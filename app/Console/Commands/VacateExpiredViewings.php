<?php

namespace App\Console\Commands;

use App\Services\ViewingService;
use Illuminate\Console\Command;

/** يُشغَّل يومياً من الجدولة (ونقطة الاستطلاع في الداشبورد تقوم بالمهمة نفسها مرة كل يوم بدون cron). */
class VacateExpiredViewings extends Command
{
    protected $signature = 'viewings:vacate-expired';

    protected $description = 'تحويل معاينات «تم اختيار العقار» المنتهي عقدها إلى «إخلاء العقار»';

    public function handle(ViewingService $viewings): int
    {
        $count = $viewings->vacateExpired();

        $this->info("تم إخلاء {$count} عقار.");

        return self::SUCCESS;
    }
}
