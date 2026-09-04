<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\TaskEvent;
use App\Support\AuditDiff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * منطق لوحة المهام: الفلاتر، الأعمدة، النقل بالسحب، الإسناد، التعليقات، المرفقات، الإشعارات.
 */
class TaskService
{
    public const FILTER_KEYS = ['search', 'assignee_id', 'created_by', 'priority', 'due', 'mine', 'all_done'];

    public function __construct(private TaskAuditLogger $audit) {}

    private function filtered(array $filters, User $me): Builder
    {
        return Task::query()
            ->when($filters['search'] ?? null, function (Builder $q, $search) {
                $search = trim((string) $search);
                $term = '%'.mb_strtolower($search).'%';
                $number = ltrim($search, '#');

                $q->where(function (Builder $w) use ($term, $number) {
                    $w->whereRaw('LOWER(title) LIKE ?', [$term]);

                    if ($number !== '' && ctype_digit($number)) {
                        $w->orWhere('id', (int) $number);
                    }
                });
            })
            ->when($filters['assignee_id'] ?? null, fn (Builder $q, $v) => $q->where('assignee_id', $v))
            ->when($filters['created_by'] ?? null, fn (Builder $q, $v) => $q->where('created_by', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->where('priority', $v))
            ->when(! empty($filters['mine']), fn (Builder $q) => $q->mine($me->id))
            ->when($filters['due'] ?? null, fn (Builder $q, $v) => $this->applyDue($q, (string) $v));
    }

    private function applyDue(Builder $query, string $due): void
    {
        $today = now()->toDateString();

        match ($due) {
            'overdue' => $query->overdue(),
            'today' => $query->whereDate('due_date', $today),
            'week' => $query->whereDate('due_date', '>=', $today)->whereDate('due_date', '<=', now()->addDays(7)->toDateString()),
            'none' => $query->whereNull('due_date'),
            default => null,
        };
    }

    /**
     * أعمدة اللوحة: كل حالة → مهامها مرتبة بالموضع. المكتملة تُحصر في آخر 30 يوماً ما لم يُطلب عرض الكل.
     *
     * @return array<string, Collection<int, Task>>
     */
    public function board(array $filters, User $me): array
    {
        $tasks = $this->filtered($filters, $me)
            ->when(empty($filters['all_done']), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('status', '!=', 'done')
                ->orWhere('completed_at', '>=', now()->subDays(Task::DONE_VISIBLE_DAYS))
            ))
            ->with(['assignee', 'property', 'media'])
            ->withCount('comments')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $columns = [];

        foreach (array_keys(Task::STATUSES) as $status) {
            $columns[$status] = $tasks->where('status', $status)->values();
        }

        return $columns;
    }

    public function create(array $data, Request $request, User $me): Task
    {
        return DB::transaction(function () use ($data, $request, $me) {
            $attributes = $this->attributes($data);
            $attributes['created_by'] = $me->id;
            $attributes['status'] = 'new';
            $attributes['position'] = ((int) Task::where('status', 'new')->max('position')) + 1;

            $task = Task::create($attributes);

            $this->audit->record($task, 'created', AuditDiff::snapshot($task, ['title', 'priority', 'due_date', 'assignee_id', 'property_id']));
            $this->syncFiles($request, $task);

            if ($task->assignee_id && (int) $task->assignee_id !== (int) $me->id) {
                $task->assignee?->notify(new TaskEvent($task, TaskEvent::ASSIGNED));
            }

            return $task;
        });
    }

    public function update(Task $task, array $data, Request $request, User $me): Task
    {
        return DB::transaction(function () use ($task, $data, $request, $me) {
            $attributes = $this->attributes($data);

            if (array_key_exists('status', $attributes) && $attributes['status'] !== $task->status) {
                $attributes['completed_at'] = $attributes['status'] === 'done' ? now() : null;
                $attributes['position'] = ((int) Task::where('status', $attributes['status'])->max('position')) + 1;
            }

            $changes = AuditDiff::changes($task, $attributes, ['created_at', 'updated_at', 'completed_at', 'position']);
            $assigneeChanged = isset($changes['assignee_id']);
            $statusChanged = isset($changes['status']);
            $previousAssignee = $task->assignee_id;

            unset($changes['assignee_id']);

            $task->update($attributes);

            if ($changes) {
                $this->audit->record($task, 'updated', $changes);
            }

            if ($assigneeChanged) {
                $this->recordAssignment($task, $previousAssignee, $me);
            }

            $this->syncFiles($request, $task);

            if ($statusChanged) {
                $this->notify($task, $me, TaskEvent::MOVED, $assigneeChanged ? [$task->assignee_id] : []);
            }

            return $task;
        });
    }

    /**
     * نقل بالسحب: الحالة الجديدة + ترتيب بطاقات العمود الهدف كما يراها المستخدم.
     *
     * @param  array<int, int|string>  $orderedIds
     * @return array<string, int> عدد المهام في كل عمود بعد النقل
     */
    public function move(Task $task, string $status, array $orderedIds, User $me): array
    {
        DB::transaction(function () use ($task, $status, $orderedIds, $me) {
            $from = $task->status;

            if ($from !== $status) {
                $task->update([
                    'status' => $status,
                    'completed_at' => $status === 'done' ? now() : null,
                ]);
            }

            // نعيد ترقيم العمود الهدف بحسب الترتيب المرسل — ما ليس فيه أصلاً يُتجاهل
            $ids = array_values(array_unique(array_map('intval', $orderedIds)));
            $inColumn = Task::where('status', $status)->whereIn('id', $ids)->pluck('id')->flip();
            $position = 0;

            foreach ($ids as $id) {
                if ($inColumn->has($id)) {
                    Task::whereKey($id)->update(['position' => $position++]);
                }
            }

            if ($from !== $status) {
                $this->audit->record($task, 'moved', ['status' => ['old' => $from, 'new' => $status]]);
                $this->notify($task, $me, TaskEvent::MOVED);
            }
        });

        return Task::query()->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)->all();
    }

    /** إعادة إسناد المهمة لموظف آخر (أو رفع الإسناد) من نافذة التفاصيل */
    public function assign(Task $task, ?int $assigneeId, User $me): Task
    {
        return DB::transaction(function () use ($task, $assigneeId, $me) {
            $previous = $task->assignee_id !== null ? (int) $task->assignee_id : null;

            if ($previous === $assigneeId) {
                return $task;
            }

            $task->update(['assignee_id' => $assigneeId]);
            $task->unsetRelation('assignee');
            $this->recordAssignment($task, $previous, $me);

            return $task;
        });
    }

    /** سطر «إسناد» في السجل + إشعار المسند إليه الجديد (إلا إن كان هو من أسندها لنفسه) */
    private function recordAssignment(Task $task, mixed $previous, User $me): void
    {
        $this->audit->record($task, 'assigned', ['assignee_id' => [
            'old' => $previous,
            'new' => $task->assignee_id,
        ]]);

        if ($task->assignee_id && (int) $task->assignee_id !== (int) $me->id) {
            $task->assignee?->notify(new TaskEvent($task, TaskEvent::ASSIGNED));
        }
    }

    public function comment(Task $task, string $body, User $me): TaskComment
    {
        return DB::transaction(function () use ($task, $body, $me) {
            $comment = $task->comments()->create(['user_id' => $me->id, 'body' => trim($body)]);

            $this->audit->record($task, 'comment_added');
            $this->notify($task, $me, TaskEvent::COMMENTED);

            return $comment;
        });
    }

    public function delete(Task $task): void
    {
        DB::transaction(function () use ($task) {
            $task->clearMediaCollection(Task::FILES);
            $task->delete();
        });
    }

    /** يحق للمسند إليه التحريك والتعليق على مهمته دون صلاحية التعديل */
    public function canTouch(Task $task, User $user): bool
    {
        return $user->can('tasks.edit') || ($task->assignee_id !== null && (int) $task->assignee_id === (int) $user->id);
    }

    private function attributes(array $data): array
    {
        unset($data['files'], $data['files_removed']);

        foreach (['description', 'due_date', 'assignee_id', 'property_id'] as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        foreach (['assignee_id', 'property_id'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }

        return $data;
    }

    /** المرفقات: حذف المحدد للحذف ثم إضافة الجديد — مع سطر في السجل لكل ملف */
    private function syncFiles(Request $request, Task $task): void
    {
        foreach ((array) $request->input('files_removed', []) as $id) {
            $media = $task->media()->where('id', (int) $id)->first();

            if ($media) {
                $this->audit->record($task, 'attachment_removed', ['file' => ['old' => $media->file_name, 'new' => null]]);
                $media->delete();
            }
        }

        foreach ((array) $request->file('files', []) as $file) {
            if ($file) {
                $media = $task->addMedia($file)->toMediaCollection(Task::FILES);
                $this->audit->record($task, 'attachment_added', ['file' => ['old' => null, 'new' => $media->file_name]]);
            }
        }
    }

    /** المستلمون: المسند إليه + المنشئ، بدون من قام بالفعل ومن أُبلغ للتو بالإسناد (المعرّفات كأعداد — Oracle يعيدها نصوصاً) */
    private function notify(Task $task, User $actor, string $event, array $except = []): void
    {
        $except = array_map('intval', $except);

        $ids = collect([$task->assignee_id, $task->created_by])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === (int) $actor->id || in_array($id, $except, true));

        if ($ids->isEmpty()) {
            return;
        }

        User::whereIn('id', $ids)->get()->each(fn (User $user) => $user->notify(new TaskEvent($task, $event)));
    }
}
