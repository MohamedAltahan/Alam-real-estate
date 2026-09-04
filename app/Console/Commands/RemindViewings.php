<?php

namespace App\Console\Commands;

use App\Services\ViewingReminderService;
use Illuminate\Console\Command;

/** يُشغَّل كل دقيقة من الجدولة (اختياري — نقطة الاستطلاع تقوم بالمهمة نفسها بدون cron). */
class RemindViewings extends Command
{
    protected $signature = 'viewings:remind';

    protected $description = 'إرسال تذكيرات المعاينات المستحقة للمسؤولين';

    public function handle(ViewingReminderService $reminders): int
    {
        $sent = $reminders->dispatchDue();

        $this->info("تم إرسال {$sent} تذكير.");

        return self::SUCCESS;
    }
}
