<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Notifications\Notification;

/** إشعار داخلي بحدث على مهمة (إسناد · تغيير حالة · تعليق) — يُخزَّن في جدول notifications ويظهر في الجرس */
class TaskEvent extends Notification
{
    public const KIND = 'task';

    public const ASSIGNED = 'assigned';

    public const MOVED = 'moved';

    public const COMMENTED = 'commented';

    public function __construct(public Task $task, public string $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => self::KIND,
            'event' => $this->event,
            'task_id' => $this->task->id,
            'title' => $this->title(),
            // مسار نسبي: الرابط المطلق يبقى على مضيف اللحظة التي أُنشئ فيها
            'url' => route('dashboard.tasks.index', ['task' => $this->task->id], absolute: false),
        ];
    }

    private function title(): string
    {
        $ref = '#'.$this->task->id;

        return match ($this->event) {
            self::ASSIGNED => 'أُسندت إليك المهمة '.$ref.': '.$this->task->title,
            self::MOVED => 'تغيّرت حالة المهمة '.$ref.' إلى «'.$this->task->statusLabel().'»: '.$this->task->title,
            self::COMMENTED => 'تعليق جديد على المهمة '.$ref.': '.$this->task->title,
            default => 'تحديث على المهمة '.$ref,
        };
    }
}
