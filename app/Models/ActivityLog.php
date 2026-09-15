<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * سطر في سجل النشاط العام: من، متى، إضافة/تعديل/حذف، على أي سجل، وما الذي تغيّر.
 * الوحدات المشمولة معرّفة في App\Support\ActivitySubjects.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'event', 'module', 'subject_type', 'subject_id',
        'subject_label', 'changes', 'ip', 'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
