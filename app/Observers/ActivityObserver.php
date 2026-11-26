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
        // الاسترجاع من الأرشيف يحفظ deleted_at = null — يُسجَّل «استرجاع» في restored لا «تعديل»
        if ($model->wasChanged('deleted_at')) {
            return;
        }

        $this->activity->updated($model);
    }

    /** الموديلات ذات soft delete (العقارات) تُسجَّل «أرشفة»؛ وغيرها «حذف» نهائي */
    public function deleting(Model $model): void
    {
        if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
            $this->activity->record('archived', $model);

            return;
        }

        $this->activity->deleted($model);
    }

    /** استرجاع عقار من الأرشيف */
    public function restored(Model $model): void
    {
        $this->activity->record('restored', $model);
    }
}
