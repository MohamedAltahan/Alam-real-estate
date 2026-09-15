<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * تحويل سطور سجل النشاط إلى صفوف عربية مقروءة: المعرّفات المرجعية تُستبدل بالأسماء
 * (استعلام واحد لكل موديل)، ويُبنى رابط السجل فقط لو كان ما زال موجوداً.
 */
final class ActivityPresenter
{
    /**
     * @param  Collection<int, ActivityLog>  $logs
     * @return Collection<int, array{id:int, at:Carbon|null, user:?string, event:string, event_label:string, tone:string, module:string, module_label:string, subject:?string, url:?string, lines:array}>
     */
    public static function present(Collection $logs): Collection
    {
        $lookup = self::lookups($logs);

        return $logs->map(function (ActivityLog $log) use ($lookup) {
            $subject = $lookup[$log->subject_type][(int) $log->subject_id] ?? null;

            return [
                'id' => $log->id,
                'at' => $log->created_at,
                'user' => $log->user?->name,
                'event' => $log->event,
                'event_label' => ActivitySubjects::eventLabel($log->event),
                'tone' => ActivitySubjects::eventTone($log->event),
                'module' => $log->module,
                'module_label' => ActivitySubjects::moduleLabel($log->module),
                'subject' => $log->subject_label,
                'url' => $subject ? ActivitySubjects::url($subject) : null,
                'lines' => self::lines($log, $lookup),
            ];
        });
    }

    /** @return array<class-string, Collection<int, Model>> */
    private static function lookups(Collection $logs): array
    {
        $ids = [];

        foreach ($logs as $log) {
            if ($log->subject_id) {
                $ids[$log->subject_type][] = (int) $log->subject_id;
            }

            $foreign = ActivitySubjects::for($log->subject_type)['foreign'] ?? [];

            foreach ((array) $log->changes as $field => $change) {
                if (! isset($foreign[$field])) {
                    continue;
                }

                foreach (['old', 'new'] as $side) {
                    $value = $change[$side] ?? null;

                    if ($value !== null && $value !== '') {
                        $ids[$foreign[$field]][] = (int) $value;
                    }
                }
            }
        }

        $lookup = [];

        foreach ($ids as $class => $list) {
            if (! class_exists($class)) {
                continue;
            }

            /** @var class-string<Model> $class */
            $lookup[$class] = $class::query()->whereIn('id', array_values(array_unique($list)))->get()->keyBy(fn (Model $m) => (int) $m->getKey());
        }

        return $lookup;
    }

    /** @return array<int, array{field:string, old:?string, new:?string}> */
    private static function lines(ActivityLog $log, array $lookup): array
    {
        $config = ActivitySubjects::for($log->subject_type);
        $lines = [];

        foreach ((array) $log->changes as $field => $change) {
            $lines[] = [
                'field' => $config['fields'][$field] ?? $field,
                'old' => self::display($config, $field, $change['old'] ?? null, $lookup),
                'new' => self::display($config, $field, $change['new'] ?? null, $lookup),
            ];
        }

        return $lines;
    }

    private static function display(?array $config, string $field, mixed $value, array $lookup): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($class = $config['foreign'][$field] ?? null) {
            return self::name($class, $value, $lookup);
        }

        if (in_array($field, $config['booleans'] ?? [], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'نعم' : 'لا';
        }

        if (isset($config['enums'][$field])) {
            return $config['enums'][$field][$value] ?? (string) $value;
        }

        // «1000.000» ← «1200» من فرق الأعمدة العشرية — نوحّد الشكل
        if (is_string($value) && is_numeric($value) && str_contains($value, '.')) {
            return rtrim(rtrim($value, '0'), '.');
        }

        return (string) $value;
    }

    private static function name(string $class, mixed $id, array $lookup): string
    {
        $model = $lookup[$class][(int) $id] ?? null;

        if (! $model) {
            return '#'.$id;
        }

        if ($model instanceof Property) {
            return $model->codeLabel();
        }

        return (string) ($model->name ?? '#'.$id);
    }
}
