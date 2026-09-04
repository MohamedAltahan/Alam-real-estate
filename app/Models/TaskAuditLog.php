<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سطر في سجل تعديلات المهمة: من عدّل، متى، ماذا (القيمة القديمة والجديدة) */
class TaskAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['task_id', 'user_id', 'action', 'changes', 'created_at'];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
