<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// تذكيرات المعاينات — اختياري: نقطة الاستطلاع في الداشبورد تقوم بنفس المهمة بدون cron
Schedule::command('viewings:remind')->everyMinute();
