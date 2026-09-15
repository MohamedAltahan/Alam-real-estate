<?php

namespace App\Models;

use App\Models\Concerns\HasAttachedFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;

/** مهمة على لوحة المهام — رقمها هو id (#12) */
class Task extends Model implements HasMedia
{
    use HasAttachedFiles;

    public const STATUSES = [
        'new' => 'جديد',
        'received' => 'تم الاستلام',
        'in_progress' => 'قيد العمل',
        'done' => 'تمت المهمة',
    ];

    /** لون نقطة العمود على اللوحة */
    public const STATUS_TONES = [
        'new' => 'bg-gray-400',
        'received' => 'bg-info',
        'in_progress' => 'bg-warning',
        'done' => 'bg-success',
    ];

    public const PRIORITIES = [
        'low' => 'منخفضة',
        'medium' => 'متوسطة',
        'high' => 'عالية',
        'urgent' => 'عاجلة',
    ];

    public const PRIORITY_TONES = [
        'low' => 'bg-gray-100 text-gray-600',
        'medium' => 'bg-info-soft text-info',
        'high' => 'bg-warning-soft text-warning',
        'urgent' => 'bg-danger/10 text-danger',
    ];

    /** مجموعة المرفقات (صور · PDF · Word · Excel) */
    public const FILES = 'attachments';

    /** المهام المكتملة تظهر على اللوحة لهذه المدة ما لم يُطلب عرض الكل */
    public const DONE_VISIBLE_DAYS = 30;

    protected $fillable = [
        'title', 'description', 'status', 'priority', 'due_date',
        'assignee_id', 'created_by', 'property_id', 'position', 'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(TaskAuditLog::class)->with('user')->latest('created_at')->latest('id');
    }

    public function scopeMine(Builder $query, int $userId): Builder
    {
        return $query->where('assignee_id', $userId);
    }

    /** المهام التي أسندها المستخدم لغيره (أو لم يُسندها لأحد بعد) */
    public function scopeDelegatedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId)
            ->where(fn (Builder $w) => $w->whereNull('assignee_id')->orWhere('assignee_id', '!=', $userId));
    }

    /** ما يراه المستخدم: مدير النظام يرى الكل، وغيره المسندة له أو التي أسندها هو */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(fn (Builder $w) => $w->where('assignee_id', $user->id)->orWhere('created_by', $user->id));
    }

    /** متأخرة: فات موعدها ولم تكتمل */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', '!=', 'done')->whereDate('due_date', '<', now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_date !== null && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function priorityTone(): string
    {
        return self::PRIORITY_TONES[$this->priority] ?? 'bg-gray-100 text-gray-500';
    }
}
