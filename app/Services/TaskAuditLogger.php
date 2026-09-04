<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAuditLog;

/** تسجيل كل ما يحدث للمهمة: من، متى، القيمة القديمة والجديدة */
class TaskAuditLogger
{
    /**
     * @param  array<string, array{old:mixed,new:mixed}>  $changes
     */
    public function record(Task $task, string $action, array $changes = []): TaskAuditLog
    {
        return TaskAuditLog::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes ?: null,
            'created_at' => now(),
        ]);
    }
}
