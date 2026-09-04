<?php

namespace App\Services;

use App\Models\ClientViewing;
use App\Support\Months;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * صفحة المعاينات وتقرير معدل التحول.
 * التجميع الشهري وحسب المسؤول يتم في PHP حتى يعمل على Oracle وMariaDB وSQLite بلا دوال تواريخ خاصة.
 */
class ViewingService
{
    public const FILTER_KEYS = ['from', 'to', 'agent_id', 'outcome', 'search'];

    public function __construct(private ClientAuditLogger $audit) {}

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return ClientViewing::query()
            ->with([
                'client:id,name,agent_id,phone_code,phone',
                'client.agent:id,name',
                'property:id,reference_code,title,agent_id,area_id',
                'property.agent:id,name',
                'property.area:id,name',
            ])
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->where('scheduled_at', '>=', CarbonImmutable::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->where('scheduled_at', '<=', CarbonImmutable::parse($v)->endOfDay()))
            ->when($filters['agent_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('client', fn (Builder $c) => $c->where('agent_id', $v)))
            ->when($filters['outcome'] ?? null, fn (Builder $q, $v) => $q->where('outcome', $v))
            ->when($filters['search'] ?? null, function (Builder $q, $search) {
                $term = '%'.mb_strtolower(trim((string) $search)).'%';

                $q->where(function (Builder $q) use ($term) {
                    $q->whereHas('client', fn (Builder $c) => $c->whereRaw('LOWER(name) LIKE ?', [$term]))
                        ->orWhereHas('property', fn (Builder $p) => $p->whereRaw('LOWER(reference_code) LIKE ?', [$term]));
                });
            })
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** تحديث نتيجة المعاينة من صفحة المعاينات (اختار / لم يختر / قيد الانتظار) */
    public function updateOutcome(ClientViewing $viewing, string $outcome, ?string $notes = null): ClientViewing
    {
        $viewing->fill(['outcome' => $outcome]);

        if ($notes !== null) {
            $viewing->notes = $notes;
        }

        if ($viewing->isDirty('outcome')) {
            $viewing->outcome_at = $outcome === ClientViewing::OUTCOME_PENDING ? null : now();
        }

        $changes = $this->audit->changes($viewing, $viewing->getDirty(), ['outcome_at', 'created_at', 'updated_at']);

        if ($viewing->isDirty()) {
            $viewing->save();
        }

        if ($changes) {
            $this->audit->record($viewing->client, 'outcome_updated', $viewing, $changes);
        }

        return $viewing;
    }

    /**
     * تقرير معدل التحول: نسبة المعاينات التي انتهت باختيار العقار.
     * المعدل = اختار ÷ (اختار + لم يختر)، والمعاينات قيد الانتظار خارج المقام.
     *
     * @return array{from:CarbonImmutable, to:CarbonImmutable, kpis:array, byAgent:Collection, monthly:array}
     */
    public function conversionReport(array $filters = []): array
    {
        $now = CarbonImmutable::now();
        $from = filled($filters['from'] ?? null) ? CarbonImmutable::parse($filters['from'])->startOfDay() : $now->subMonths(5)->startOfMonth();
        $to = filled($filters['to'] ?? null) ? CarbonImmutable::parse($filters['to'])->endOfDay() : $now->endOfMonth();

        if ($to->lt($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        // حد أقصى سنتان حتى لا نحمّل كل الجدول
        if ($from->diffInMonths($to) > 24) {
            $from = $to->subMonths(24)->startOfMonth();
        }

        $agentFilter = filled($filters['agent_id'] ?? null) ? (int) $filters['agent_id'] : null;

        $viewings = ClientViewing::query()
            ->with(['client:id,agent_id', 'client.agent:id,name', 'property:id,agent_id', 'property.agent:id,name'])
            ->whereBetween('scheduled_at', [$from, $to])
            ->get(['id', 'client_id', 'property_id', 'outcome', 'scheduled_at'])
            ->map(function (ClientViewing $viewing) {
                $agent = $viewing->client?->agent ?? $viewing->property?->agent;

                return [
                    'outcome' => $viewing->outcome,
                    'month' => $viewing->scheduled_at?->format('Y-m'),
                    'agent_id' => $agent?->id,
                    'agent' => $agent?->name ?? 'بدون مسؤول',
                ];
            })
            ->when($agentFilter, fn (Collection $rows) => $rows->where('agent_id', $agentFilter))
            ->values();

        $months = Months::between($from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'kpis' => $this->kpis($viewings),
            'byAgent' => $viewings
                ->groupBy(fn (array $row) => (string) ($row['agent_id'] ?? 0))
                ->map(fn (Collection $rows, $agentId) => ['agent_id' => (int) $agentId ?: null, 'name' => $rows->first()['agent']] + $this->kpis($rows))
                ->sortByDesc('total')
                ->values(),
            'monthly' => [
                'labels' => $months->pluck('label')->all(),
                'bars' => $months->map(fn (array $m) => $viewings->where('month', $m['key'])->count())->all(),
                'line' => $months->map(fn (array $m) => $this->kpis($viewings->where('month', $m['key']))['rate'])->all(),
            ],
        ];
    }

    /** @return array{total:int, chosen:int, rejected:int, pending:int, decided:int, rate:int} */
    private function kpis(Collection $rows): array
    {
        $chosen = $rows->where('outcome', ClientViewing::OUTCOME_CHOSEN)->count();
        $rejected = $rows->where('outcome', ClientViewing::OUTCOME_REJECTED)->count();
        $decided = $chosen + $rejected;

        return [
            'total' => $rows->count(),
            'chosen' => $chosen,
            'rejected' => $rejected,
            'pending' => $rows->count() - $decided,
            'decided' => $decided,
            'rate' => $decided ? (int) round($chosen / $decided * 100) : 0,
        ];
    }
}
