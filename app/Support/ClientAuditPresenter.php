<?php

namespace App\Support;

use App\Models\ClientAuditLog;
use App\Models\ClientInteraction;
use App\Models\ClientPropertyNeed;
use App\Models\ClientViewing;
use App\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * تحويل سطور سجل التعديلات إلى نصوص عربية مقروءة مع استبدال المعرّفات بالأسماء
 * (استعلام واحد لكل نوع سجل بدل استعلام لكل سطر).
 */
final class ClientAuditPresenter
{
    /**
     * @param  Collection<int, ClientAuditLog>  $logs
     * @return Collection<int, array{id:int, at:\Carbon\Carbon|null, user:?string, action:string, label:string, subject:?string, lines:array}>
     */
    public static function present(Collection $logs): Collection
    {
        $lookup = self::lookups($logs);

        return $logs->map(fn (ClientAuditLog $log) => [
            'id' => $log->id,
            'at' => $log->created_at,
            'user' => $log->user?->name,
            'action' => $log->action,
            'label' => ClientFields::actionLabel($log->action),
            'subject' => self::subjectLabel($log, $lookup),
            'lines' => self::lines($log, $lookup),
        ]);
    }

    /** @return array<class-string, Collection<int, Model>> */
    private static function lookups(Collection $logs): array
    {
        $ids = [];

        foreach ($logs as $log) {
            foreach ((array) $log->changes as $field => $change) {
                if (! ClientFields::isForeign($field)) {
                    continue;
                }

                foreach (['old', 'new'] as $side) {
                    $value = $change[$side] ?? null;

                    if ($value !== null && $value !== '') {
                        $ids[ClientFields::FOREIGN[$field]][] = (int) $value;
                    }
                }
            }

            if ($log->subject_type === Property::class && $log->subject_id) {
                $ids[Property::class][] = (int) $log->subject_id;
            }
        }

        $lookup = [];

        foreach ($ids as $class => $list) {
            /** @var class-string<Model> $class */
            $lookup[$class] = $class::query()->whereIn('id', array_values(array_unique($list)))->get()->keyBy('id');
        }

        return $lookup;
    }

    private static function subjectLabel(ClientAuditLog $log, array $lookup): ?string
    {
        return match ($log->subject_type) {
            Property::class => self::name(Property::class, $log->subject_id, $lookup, 'عقار'),
            ClientViewing::class => self::name(Property::class, data_get($log->changes, 'property_id.new') ?? data_get($log->changes, 'property_id.old'), $lookup, 'معاينة'),
            ClientPropertyNeed::class => 'احتياج عقار',
            ClientInteraction::class => 'تواصل',
            default => null,
        };
    }

    /** @return array<int, array{field:string, old:?string, new:?string}> */
    private static function lines(ClientAuditLog $log, array $lookup): array
    {
        $lines = [];

        foreach ((array) $log->changes as $field => $change) {
            $lines[] = [
                'field' => ClientFields::label($field),
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

        if (ClientFields::isForeign($field)) {
            return self::name(ClientFields::FOREIGN[$field], $value, $lookup, '#'.$value);
        }

        if (in_array($field, ClientFields::BOOLEANS, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'نعم' : 'لا';
        }

        if (isset(ClientFields::ENUMS[$field])) {
            return ClientFields::enumLabel($field, $value);
        }

        return (string) $value;
    }

    private static function name(string $class, mixed $id, array $lookup, string $fallback): string
    {
        $model = $lookup[$class][(int) $id] ?? null;

        if (! $model) {
            return $fallback;
        }

        if ($model instanceof Property) {
            return trim(($model->reference_code ?: '').' '.($model->title ? '— '.$model->title : ''));
        }

        return (string) ($model->name ?? $fallback);
    }
}
