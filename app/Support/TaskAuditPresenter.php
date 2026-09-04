<?php

namespace App\Support;

use App\Models\Property;
use App\Models\TaskAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * تحويل سطور سجل المهمة إلى نصوص عربية مقروءة مع استبدال المعرّفات بالأسماء
 * (استعلام واحد لكل نوع سجل) — نفس شكل ClientAuditPresenter ليعمل مع partials.audit.
 */
final class TaskAuditPresenter
{
    /**
     * @param  Collection<int, TaskAuditLog>  $logs
     * @return Collection<int, array{id:int, at:\Carbon\Carbon|null, user:?string, action:string, label:string, subject:?string, lines:array}>
     */
    public static function present(Collection $logs): Collection
    {
        $lookup = self::lookups($logs);

        return $logs->map(fn (TaskAuditLog $log) => [
            'id' => $log->id,
            'at' => $log->created_at,
            'user' => $log->user?->name,
            'action' => $log->action,
            'label' => TaskFields::actionLabel($log->action),
            'subject' => null,
            'lines' => self::lines($log, $lookup),
        ]);
    }

    /** @return array<class-string, Collection<int, Model>> */
    private static function lookups(Collection $logs): array
    {
        $ids = [];

        foreach ($logs as $log) {
            foreach ((array) $log->changes as $field => $change) {
                if (! TaskFields::isForeign($field)) {
                    continue;
                }

                foreach (['old', 'new'] as $side) {
                    $value = $change[$side] ?? null;

                    if ($value !== null && $value !== '') {
                        $ids[TaskFields::FOREIGN[$field]][] = (int) $value;
                    }
                }
            }
        }

        $lookup = [];

        foreach ($ids as $class => $list) {
            /** @var class-string<Model> $class */
            $lookup[$class] = $class::query()->whereIn('id', array_values(array_unique($list)))->get()->keyBy('id');
        }

        return $lookup;
    }

    /** @return array<int, array{field:string, old:?string, new:?string}> */
    private static function lines(TaskAuditLog $log, array $lookup): array
    {
        $lines = [];

        foreach ((array) $log->changes as $field => $change) {
            $lines[] = [
                'field' => TaskFields::label($field),
                'old' => self::display($field, $change['old'] ?? null, $lookup),
                'new' => self::display($field, $change['new'] ?? null, $lookup),
            ];
        }

        return $lines;
    }

    private static function display(string $field, mixed $value, array $lookup): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (TaskFields::isForeign($field)) {
            $model = $lookup[TaskFields::FOREIGN[$field]][(int) $value] ?? null;

            if ($model instanceof Property) {
                return ClientFormData::propertyLabel($model);
            }

            return (string) ($model?->name ?? '#'.$value);
        }

        if (isset(TaskFields::ENUMS[$field])) {
            return TaskFields::enumLabel($field, $value);
        }

        // تاريخ الاستحقاق يوم بلا وقت
        if ($field === 'due_date') {
            return substr((string) $value, 0, 10);
        }

        return (string) $value;
    }
}
