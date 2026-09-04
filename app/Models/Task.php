<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** مهمة على لوحة المهام — رقمها هو id (#12) */
class Task extends Model implements HasMedia
{
    use InteractsWithMedia;

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
    public const ATTACHMENTS = 'attachments';

    public const FILE_EXTENSIONS = PropertyOwner::FILE_EXTENSIONS;

    public const MAX_FILE_KB = PropertyOwner::MAX_FILE_KB;

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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENTS);
    }

    public function scopeMine(Builder $query, int $userId): Builder
    {
        return $query->where('assignee_id', $userId);
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

    /** المرفقات بصيغة جاهزة للواجهة (Alpine) */
    public function filePayload(): array
    {
        return $this->getMedia(self::ATTACHMENTS)->map(fn (Media $media) => [
            'id' => $media->id,
            'name' => $media->file_name,
            'size' => (int) $media->size,
            'url' => $media->getUrl(),
            'ext' => strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION)),
        ])->values()->all();
    }
}
