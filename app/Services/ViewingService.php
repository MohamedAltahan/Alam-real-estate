<?php

namespace App\Services;

use App\Models\ClientViewing;
use App\Support\Months;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * صفحة المعاينات وتقرير معدل التحول.
 * التجميع الشهري وحسب المسؤول يتم في PHP حتى يعمل على Oracle وMariaDB وSQLite بلا دوال تواريخ خاصة.
 */
class ViewingService
{
    public const FILTER_KEYS = ['from', 'to', 'agent_id', 'outcome', 'search'];

    /** أقصى طول للفترة — الرسم الشهري يبني عموداً لكل شهر */
    public const MAX_RANGE_MONTHS = 24;

    /** فلاتر تقرير واتساب المعاينات */
    public const WA_STATES = [
        'complete' => 'مكتملة (أُبلغ المالك وأُرسلت المتابعة)',
        'missing_owner' => 'لم يُبلَّغ المالك',
        'missing_client' => 'لم تُرسل المتابعة',
    ];

    public function __construct(private ClientAuditLogger $audit, private ViewingOutcomeSync $sync) {}

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return ClientViewing::query()
            ->with([
                'client:id,name,agent_id,phone_code,phone',
                'client.agent:id,name,phone',
                'property:id,reference_code,title,agent_id,area_id,owner_id,building_name,purpose',
                'property.agent:id,name,phone',
                'property.area:id,name',
                'property.owner.contacts',
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

    /**
     * تحديث نتيجة المعاينة من الجداول (تم اختيار العقار / لم يختر / إخلاء / قيد الانتظار).
     * يتبعه أثر النتيجة على العميل والعقار (ViewingOutcomeSync) في المعاملة نفسها.
     */
    public function updateOutcome(ClientViewing $viewing, string $outcome, ?string $notes = null, ?string $contractEndsAt = null): ClientViewing
    {
        return DB::transaction(function () use ($viewing, $outcome, $notes, $contractEndsAt) {
            $previous = $viewing->outcome;
            $viewing->fill(['outcome' => $outcome]);

            if ($outcome === ClientViewing::OUTCOME_CHOSEN && $contractEndsAt !== null) {
                $viewing->contract_ends_at = $contractEndsAt;
            } elseif (in_array($outcome, [ClientViewing::OUTCOME_PENDING, ClientViewing::OUTCOME_REJECTED], true)) {
                $viewing->contract_ends_at = null; // «إخلاء العقار» يحتفظ بالتاريخ كسجل
            }

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
     * الفحص اليومي: «تم اختيار العقار» الذي انتهى عقده يصبح «إخلاء العقار» فيتحرر العقار لعملاء آخرين.
     * يُستدعى من الأمر viewings:vacate-expired ومن نقطة الاستطلاع مرة كل يوم.
     */
    public function vacateExpired(): int
    {
        $expired = ClientViewing::query()
            ->with(['client', 'property.status'])
            ->where('outcome', ClientViewing::OUTCOME_CHOSEN)
            ->whereNotNull('contract_ends_at')
            ->where('contract_ends_at', '<', today())
            ->get();

        foreach ($expired as $viewing) {
            $viewing->fill(['outcome' => ClientViewing::OUTCOME_VACATED]);
            $viewing->outcome_at = now();

            $changes = $this->audit->changes($viewing, $viewing->getDirty(), ['outcome_at', 'created_at', 'updated_at']);
            $viewing->save();
            $this->audit->record($viewing->client, 'outcome_updated', $viewing, $changes);

            $this->sync->apply($viewing, ClientViewing::OUTCOME_CHOSEN);
        }

        return $expired->count();
    }

    /**
     * تقرير معدل التحول: نسبة المعاينات التي انتهت باختيار العقار.
     * المعدل = اختار ÷ (اختار + لم يختر)، والمعاينات قيد الانتظار خارج المقام.
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
                    'agent' => $agent?->name ?? 'بدون مسؤول',
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
     * تقرير واتساب المعاينات: لكل معاينة علامتان — إبلاغ المالك ببيانات العميل، وإرسال المتابعة للعميل.
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
        $now = CarbonImmutable::now();
        $from = filled($filters['from'] ?? null) ? CarbonImmutable::parse($filters['from'])->startOfDay() : $now->subMonths(5)->startOfMonth();
        $to = filled($filters['to'] ?? null) ? CarbonImmutable::parse($filters['to'])->endOfDay() : $now->endOfMonth();

        if ($to->lt($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        $shortened = $from->diffInMonths($to) > self::MAX_RANGE_MONTHS;

        if ($shortened) {
            $to = $from->addMonths(self::MAX_RANGE_MONTHS)->endOfMonth();
        }

        return [$from, $to, $shortened];
    }

    /**
     * «تم اختيار العقار» يشمل من أخلى العقار لاحقاً — الاختيار حصل فعلاً.
     *
     * @return array{total:int, chosen:int, rejected:int, pending:int, decided:int, rate:int}
     */
    private function kpis(Collection $rows): array
    {
        $chosen = $rows->whereIn('outcome', [ClientViewing::OUTCOME_CHOSEN, ClientViewing::OUTCOME_VACATED])->count();
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
