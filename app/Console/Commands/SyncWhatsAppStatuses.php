<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

/**
 * يسأل البوابة عن حالة رسائل واتساب المعلّقة (أُرسلت · وصلت · قُرئت) — بديل الويب هوك
 * عند تعذّر وصوله. يُجدول كل دقيقة، ونقطة الاستطلاع في الداشبورد تقوم بنفس المهمة بدون cron.
 */
class SyncWhatsAppStatuses extends Command
{
    protected $signature = 'whatsapp:sync-statuses {--all : كل الرسائل المعلّقة الآن بلا انتظار فاصل إعادة الفحص}';

    protected $description = 'تحديث حالات تسليم رسائل واتساب المعلّقة من البوابة';

    public function handle(WhatsAppService $whatsapp): int
    {
        $updated = $whatsapp->refreshMessageStatuses(force: (bool) $this->option('all'));

        $this->info("تم تحديث حالة {$updated} رسالة.");

        return self::SUCCESS;
    }
}
