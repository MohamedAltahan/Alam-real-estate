<?php

namespace App\Services;

use App\Models\ClientViewing;
use App\Support\Months;
use App\Support\ReportRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * صفحة المعاينات وتقرير تحول المعاينات.
 * التجميع الشهري وحسب مندوب المبيعات يتم في PHP حتى يعمل على Oracle وMariaDB وSQLite بلا دوال تواريخ خاصة.
 */
class ViewingService
{
    public const FILTER_KEYS = ['from', 'to', 'agent_id', 'outcome', 'search', 'city_id', 'area_id', 'unit_type_id', 'purpose', 'in_person', 'wa_state'];

    /** أقصى طول للفترة — الرسم الشهري يبني عموداً لكل شهر */
    public const MAX_RANGE_MONTHS = ReportRange::MAX_MONTHS;

    /** فلاتر تقرير واتساب المعاينات */
    public const WA_STATES = [
        'complete' => 'مكتملة (أُبلغ المسؤول وأُرسلت النتيجة)',
        'missing_owner' => 'لم يُبلَّغ المسؤول',
        'missing_client' => 'لم تُرسل النتيجة',
    ];

    public function __construct(private ClientAuditLogger $audit, private ViewingOutcomeSync $sync) {}

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with([
                'client:id,name,agent_id,phone_code,phone',
                'client.agent:id,name,phone',
                'property:id,reference_code,title,agent_id,area_id,owner_id,building_name,purpose',
                'property.agent:id,name,phone',
                'property.area:id,name',
                'property.contacts',
            ])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * عدّادات أزرار النتيجة فوق الجدول: كل الفلاتر الحالية عدا النتيجة نفسها.
     *
     * @return array{total:int, outcomes:array<string,int>}
     */
    public function outcomeCounts(array $filters = []): array
    {
        $base = $this->filtered(array_diff_key($filters, ['outcome' => null]));

        return [
            'total' => (clone $base)->count(),
            'outcomes' => (clone $base)
                ->selectRaw('outcome, COUNT(*) as aggregate')
                ->groupBy('outcome')
                ->pluck('aggregate', 'outcome')
                ->map(fn ($n) => (int) $n)
                ->all(),
        ];
    }

    /** استعلام المعاينات بعد تطبيق الفلاتر — يخدم القائمة وعدّادات النتائج معاً */
    private function filtered(array $filters = []): Builder
    {
        return ClientViewing::query()
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->where('scheduled_at', '>=', CarbonImmutable::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->where('scheduled_at', '<=', CarbonImmutable::parse($v)->endOfDay()))
            ->when($filters['agent_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('client', fn (Builder $c) => $c->where('agent_id', $v)))
            ->when($filters['outcome'] ?? null, fn (Builder $q, $v) => $q->where('outcome', $v))
            ->when($filters['city_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('property', fn (Builder $p) => $p->where('city_id', $v)))
            ->when($filters['area_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('property', fn (Builder $p) => $p->where('area_id', $v)))
            ->when($filters['unit_type_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('property', fn (Builder $p) => $p->where('unit_type_id', $v)))
            ->when($filters['purpose'] ?? null, fn (Builder $q, $v) => $q->whereHas('property', fn (Builder $p) => $p->where('purpose', $v)))
            // «0» قيمة صالحة (غير حضوري) فلا تُمرَّر إلى when مباشرة
            ->when(isset($filters['in_person']) && $filters['in_person'] !== '', fn (Builder $q) => $q->where('in_person', (bool) (int) $filters['in_person']))
            ->when($filters['wa_state'] ?? null, fn (Builder $q, $v) => $this->applyWaState($q, (string) $v))
            ->when($filters['search'] ?? null, function (Builder $q, $search) {
                $term = '%'.mb_strtolower(trim((string) $search)).'%';

                $q->where(function (Builder $q) use ($term) {
                    $q->whereHas('client', fn (Builder $c) => $c->whereRaw('LOWER(name) LIKE ?', [$term]))
                        ->orWhereHas('property', fn (Builder $p) => $p->whereRaw('LOWER(reference_code) LIKE ?', [$term]));
                });
            });
    }

    /** حالة واتساب: مكتملة (العلامتان) · لم يُبلَّغ المسؤول · لم تُرسل النتيجة */
    private function applyWaState(Builder $query, string $state): void
    {
        match ($state) {
            'complete' => $query->whereNotNull('owner_notified_at')->whereNotNull('client_followed_up_at'),
            'missing_owner' => $query->whereNull('owner_notified_at'),
            'missing_client' => $query->whereNull('client_followed_up_at'),
            default => null,
        };
    }

    /** تعديل ملاحظة المعاينة من الخارج (الجداول) مع تسجيلها في سجل العميل */
    public function updateNotes(ClientViewing $viewing, ?string $notes): ClientViewing
    {
        $notes = trim((string) $notes) === '' ? null : trim((string) $notes);
        $viewing->fill(['notes' => $notes]);

        $changes = $this->audit->changes($viewing, $viewing->getDirty(), ['created_at', 'updated_at']);

        if ($viewing->isDirty()) {
            $viewing->save();
        }

        if ($changes) {
            $this->audit->record($viewing->client, 'viewing_updated', $viewing, $changes);
        }

        return $viewing;
    }

    /**
     * تحديث نتيجة المعاينة من الجداول (قيد الانتظار / قيد الدراسة / مهتم / غير مهتم / إلغاء الموعد).
     * يتبعه أثر النتيجة على العميل (ViewingOutcomeSync) في المعاملة نفسها.
     */
    public function updateOutcome(ClientViewing $viewing, string $outcome, ?string $notes = null): ClientViewing
    {
        return DB::transaction(function () use ($viewing, $outcome, $notes) {
            $previous = $viewing->outcome;
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

            $this->sync->apply($viewing, $previous);

            return $viewing;
        });
    }

    /**
     * تقرير تحول المعاينات: نسبة المعاينات التي انتهت باهتمام العميل.
     * المعدل = مهتم ÷ (مهتم + غير مهتم)، وباقي النتائج (قيد الانتظار/الدراسة/الإلغاء) خارج المقام.
     *
     * @return array{from:CarbonImmutable, to:CarbonImmutable, shortened:bool, kpis:array, byAgent:Collection, monthly:array}
     */
    public function conversionReport(array $filters = []): array
    {
        [$from, $to, $shortened] = $this->range($filters);

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
                    'agent' => $agent?->name ?? 'بدون مندوب',
                ];
            })
            ->when($agentFilter, fn (Collection $rows) => $rows->where('agent_id', $agentFilter))
            ->values();

        $months = Months::between($from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'shortened' => $shortened,
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

    /**
     * تقرير واتساب المعاينات: لكل معاينة علامتان — إبلاغ مسؤول العقار ببيانات العميل، وإرسال نتيجة المعاينة له.
     * «مكتملة» = العلامتان معاً.
     *
     * @return array{from:CarbonImmutable, to:CarbonImmutable, shortened:bool, kpis:array<string,int>, rows:Collection<int, ClientViewing>}
     */
    public function whatsappReport(array $filters = []): array
    {
        [$from, $to, $shortened] = $this->range($filters);
        $agentFilter = filled($filters['agent_id'] ?? null) ? (int) $filters['agent_id'] : null;

        $all = ClientViewing::query()
            ->with([
                'client:id,name,agent_id,phone_code,phone', 'client.agent:id,name',
                'property:id,reference_code,title,agent_id,area_id', 'property.agent:id,name', 'property.area:id,name',
            ])
            ->whereBetween('scheduled_at', [$from, $to])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get()
            ->when($agentFilter, fn (Collection $rows) => $rows->filter(
                fn (ClientViewing $v) => (int) (($v->client?->agent ?? $v->property?->agent)?->id ?? 0) === $agentFilter
            ))
            ->values();

        $complete = fn (ClientViewing $v) => $v->owner_notified_at !== null && $v->client_followed_up_at !== null;

        $rows = match ($filters['state'] ?? null) {
            'complete' => $all->filter($complete),
            'missing_owner' => $all->filter(fn (ClientViewing $v) => $v->owner_notified_at === null),
            'missing_client' => $all->filter(fn (ClientViewing $v) => $v->client_followed_up_at === null),
            default => $all,
        };

        return [
            'from' => $from,
            'to' => $to,
            'shortened' => $shortened,
            'kpis' => [
                'total' => $all->count(),
                'complete' => $all->filter($complete)->count(),
                'owner_sent' => $all->filter(fn (ClientViewing $v) => $v->owner_notified_at !== null)->count(),
                'client_sent' => $all->filter(fn (ClientViewing $v) => $v->client_followed_up_at !== null)->count(),
                'missing_owner' => $all->filter(fn (ClientViewing $v) => $v->owner_notified_at === null)->count(),
                'missing_client' => $all->filter(fn (ClientViewing $v) => $v->client_followed_up_at === null)->count(),
            ],
            'rows' => $rows->values(),
        ];
    }

    /**
     * نطاق التقرير: الافتراضي آخر 6 أشهر، والحد الأقصى سنتان حتى لا نحمّل كل الجدول.
     *
     * الفترة الأطول من الحد تُقصَّر من تاريخ البداية الذي اختاره المستخدم (لا من النهاية)،
     * فتبقى النافذة عند بداية ما طلبه بدل أن تقفز إلى نهايةٍ قد تكون في المستقبل بلا بيانات.
     * العنصر الثالث يخبر الواجهة أن التقصير حدث لتعرض تنبيهاً بدل تغيير الفترة بصمت.
     *
     * @return array{0:CarbonImmutable, 1:CarbonImmutable, 2:bool}
     */
    private function range(array $filters): array
    {
        return ReportRange::resolve($filters, self::MAX_RANGE_MONTHS);
    }

    /**
     * @return array{total:int, interested:int, not_interested:int, undecided:int, decided:int, rate:int}
     */
    private function kpis(Collection $rows): array
    {
        $interested = $rows->where('outcome', ClientViewing::OUTCOME_INTERESTED)->count();
        $notInterested = $rows->where('outcome', ClientViewing::OUTCOME_NOT_INTERESTED)->count();
        $decided = $interested + $notInterested;

        return [
            'total' => $rows->count(),
            'interested' => $interested,
            'not_interested' => $notInterested,
            'undecided' => $rows->count() - $decided,
            'decided' => $decided,
            'rate' => $decided ? (int) round($interested / $decided * 100) : 0,
        ];
    }
}
