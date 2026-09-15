<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * يكتب سجل النشاط العام لكل موديل مسجَّل عليه (عقار · مالك · عميل · معاينة · مهمة).
 * الحذف يُلتقط قبل تنفيذه (deleting) لأن كل الحذف في النظام نهائي.
 */
class ActivityObserver
{
    public function __construct(private ActivityLogger $activity) {}

    public function created(Model $model): void
    {
        $this->activity->created($model);
    }

    public function updated(Model $model): void
    {
        $this->activity->updated($model);
    }

    public function deleting(Model $model): void
    {
        $this->activity->deleted($model);
    }
}
