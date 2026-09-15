<?php

namespace App\Services;

use App\Models\ActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** شاشة سجل النشاط: القائمة بالفلاتر وعدّادات أزرار العملية */
class ActivityLogService
{
    public const FILTER_KEYS = ['from', 'to', 'user_id', 'module', 'event', 'search'];

    public function paginate(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * عدّادات أزرار العملية فوق الجدول: كل الفلاتر الحالية عدا العملية نفسها.
     *
     * @return array{total:int, events:array<string,int>}
     */
    public function eventCounts(array $filters = []): array
    {
        $base = $this->filtered(array_diff_key($filters, ['event' => null]));

        return [
            'total' => (clone $base)->count(),
            'events' => (clone $base)
                ->selectRaw('event, COUNT(*) as aggregate')
                ->groupBy('event')
                ->pluck('aggregate', 'event')
                ->map(fn ($n) => (int) $n)
                ->all(),
        ];
    }

    private function filtered(array $filters = []): Builder
    {
        return ActivityLog::query()
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '>=', CarbonImmutable::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '<=', CarbonImmutable::parse($v)->endOfDay()))
            ->when($filters['user_id'] ?? null, fn (Builder $q, $v) => $q->where('user_id', (int) $v))
            ->when($filters['module'] ?? null, fn (Builder $q, $v) => $q->where('module', $v))
            ->when($filters['event'] ?? null, fn (Builder $q, $v) => $q->where('event', $v))
            ->when($filters['search'] ?? null, function (Builder $q, $search) {
                $term = '%'.mb_strtolower(trim((string) $search)).'%';

                $q->whereRaw('LOWER(subject_label) LIKE ?', [$term]);
            });
    }
}
