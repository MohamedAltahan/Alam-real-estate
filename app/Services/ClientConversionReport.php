<?php

namespace App\Services;

use App\Models\Client;
use App\Support\Months;
use App\Support\ReportRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * تقرير تحول العملاء: نسبة العملاء الذين وصلوا إلى مرحلة «ربح» من كل العملاء المسجّلين في الفترة،
 * مجمّعة بمندوب المبيعات — حتى نعرف كم عميلاً ربح كل مندوب.
 * التجميع في PHP حتى يعمل على Oracle وMariaDB وSQLite بلا دوال تواريخ خاصة.
 */
class ClientConversionReport
{
    public const WON = 'closed_won';

    public const LOST = 'closed_lost';

    /**
     * @return array{from:CarbonImmutable, to:CarbonImmutable, shortened:bool, kpis:array, byAgent:Collection, monthly:array}
     */
    public function build(array $filters = []): array
    {
        [$from, $to, $shortened] = ReportRange::resolve($filters);

        $agentFilter = filled($filters['agent_id'] ?? null) ? (int) $filters['agent_id'] : null;

        $clients = Client::query()
            ->with(['agent:id,name', 'stage:id,key'])
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'agent_id', 'stage_id', 'created_at'])
            ->map(fn (Client $client) => [
                'stage' => (string) ($client->stage?->key ?? ''),
                'month' => $client->created_at?->format('Y-m'),
                'agent_id' => $client->agent?->id,
                'agent' => $client->agent?->name ?? 'بدون مندوب',
            ])
            ->when($agentFilter, fn (Collection $rows) => $rows->where('agent_id', $agentFilter))
            ->values();

        $months = Months::between($from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'shortened' => $shortened,
            'kpis' => $this->kpis($clients),
            'byAgent' => $clients
                ->groupBy(fn (array $row) => (string) ($row['agent_id'] ?? 0))
                ->map(fn (Collection $rows, $agentId) => ['agent_id' => (int) $agentId ?: null, 'name' => $rows->first()['agent']] + $this->kpis($rows))
                ->sortByDesc('total')
                ->values(),
            'monthly' => [
                'labels' => $months->pluck('label')->all(),
                'bars' => $months->map(fn (array $m) => $clients->where('month', $m['key'])->count())->all(),
                'line' => $months->map(fn (array $m) => $this->kpis($clients->where('month', $m['key']))['rate'])->all(),
            ],
        ];
    }

    /**
     * المعدل = ربح ÷ كل العملاء (لا ربح ÷ المحسوم فقط).
     *
     * @return array{total:int, won:int, lost:int, open:int, rate:int}
     */
    private function kpis(Collection $rows): array
    {
        $total = $rows->count();
        $won = $rows->where('stage', self::WON)->count();
        $lost = $rows->where('stage', self::LOST)->count();

        return [
            'total' => $total,
            'won' => $won,
            'lost' => $lost,
            'open' => $total - $won - $lost,
            'rate' => $total ? (int) round($won / $total * 100) : 0,
        ];
    }
}
