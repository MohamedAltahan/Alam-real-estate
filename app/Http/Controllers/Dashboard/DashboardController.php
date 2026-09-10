<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientStage;
use App\Models\ContactRequest;
use App\Models\Property;
use App\Models\PropertyStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /** أسماء الشهور بالعربي (index 1..12) */
    private const MONTHS = [
        1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];

    public function __invoke()
    {
        $user = auth()->user();
        $canDashboard = $user->can('dashboard.view');
        $canClients = $canDashboard && $user->can('clients.view');
        $canProperties = $canDashboard && $user->can('properties.view');
        $canRequests = $canDashboard && $user->can('contact_requests.view');

        $now = CarbonImmutable::now();
        $currency = $user->currencySymbol();
        $monthStart = $now->startOfMonth();
        $prevStart = $monthStart->subMonth();

        $soldId = $canProperties ? PropertyStatus::where('key', 'sold')->value('id') : null;
        $newId = $canClients ? ClientStage::where('key', 'new')->value('id') : null;
        $viewingId = $canClients ? ClientStage::where('key', 'viewing')->value('id') : null;
        $wonId = $canClients ? ClientStage::where('key', 'closed_won')->value('id') : null;

        // ===== الصفقات المغلقة + الإيرادات (عقارات مباعة) =====
        // تاريخ الصفقة = sold_at (يضبطه PropertyObserver عند البيع) لا updated_at الذي يتحرك مع أي تعديل
        $sold = $soldId
            ? Property::where('status_id', $soldId)->get(['price', 'sold_at', 'created_at'])
                ->map(fn (Property $p) => ['price' => (float) $p->price, 'sold_at' => $p->sold_at ?? $p->created_at])
            : collect();

        $soldThisMonth = $sold->where('sold_at', '>=', $monthStart);
        $soldPrevMonth = $sold->whereBetween('sold_at', [$prevStart, $monthStart]);

        // ===== بطاقات المؤشرات =====
        $properties = $canProperties ? Property::get(['id', 'created_at']) : collect();
        $clients = $canClients ? Client::get(['id', 'created_at', 'won_at', 'stage_id']) : collect();
        $wonClients = $wonId
            ? $clients->where('stage_id', $wonId)->map(fn (Client $c) => ['won_at' => $c->won_at ?? $c->created_at])
            : collect();
        $wonThisMonth = $wonClients->where('won_at', '>=', $monthStart);
        $wonPrevMonth = $wonClients->whereBetween('won_at', [$prevStart, $monthStart]);

        $stats = [
            [
                'key' => 'properties',
                'label' => 'اجمالي العقارات',
                'value' => number_format($properties->count()),
                'sub_value' => number_format($properties->where('created_at', '>=', $monthStart)->count()),
                'sub_label' => 'عدد العقارات الاضافية هذا الشهر',
                'trend' => $this->trend(
                    $properties->where('created_at', '>=', $monthStart)->count(),
                    $properties->whereBetween('created_at', [$prevStart, $monthStart])->count(),
                ),
                'tone' => 'info',
                'icon' => 'building',
            ],
            [
                'key' => 'clients',
                'label' => 'اجمالي العملاء',
                'value' => number_format($clients->count()),
                'sub_value' => number_format($clients->where('created_at', '>=', $monthStart)->count()),
                'sub_label' => 'عدد العملاء الجدد هذا الشهر',
                'trend' => $this->trend(
                    $clients->where('created_at', '>=', $monthStart)->count(),
                    $clients->whereBetween('created_at', [$prevStart, $monthStart])->count(),
                ),
                'tone' => 'accent',
                'icon' => 'users',
            ],
            [
                'key' => 'revenue',
                'label' => 'اجمالي الايرادات',
                'value' => number_format((float) $sold->sum('price')).' <span class="text-base font-bold">'.$currency.'</span>',
                'sub_value' => number_format((float) $soldThisMonth->sum('price')),
                'sub_label' => 'ايرادات هذا الشهر',
                'trend' => $this->trend((float) $soldThisMonth->sum('price'), (float) $soldPrevMonth->sum('price')),
                'tone' => 'primary',
                'icon' => 'money',
            ],
            [
                'key' => 'deals',
                'label' => 'اجمالي الصفقات المغلقة',
                'value' => number_format($wonClients->count()),
                'sub_value' => number_format($wonThisMonth->count()),
                'sub_label' => 'صفقات الشهر الحالي',
                'trend' => $this->trend($wonThisMonth->count(), $wonPrevMonth->count()),
                'tone' => 'success',
                'icon' => 'check',
            ],
        ];

        // ===== الرسوم البيانية =====
        $revenueMonths = $this->lastMonths($now, 8);
        $leadMonths = $this->lastMonths($now, 9);
        $convMonths = $this->lastMonths($now, 6);

        $charts = [
            // الإيراد الشهري — بالآلاف
            'revenue' => [
                'labels' => $revenueMonths->pluck('label'),
                'currency' => $currency,
                'data' => $revenueMonths->map(fn ($m) => round(
                    (float) $sold->whereBetween('sold_at', [$m['from'], $m['to']])->sum('price') / 1000, 1
                )),
            ],
            // العملاء (الطلبات) حسب الشهر
            'leads' => [
                'labels' => $leadMonths->pluck('label'),
                'data' => $leadMonths->map(fn ($m) => $clients->whereBetween('created_at', [$m['from'], $m['to']])->count()),
            ],
            // معدل التحويل — أعمدة (عملاء) + خط (نسبة الربح)
            'conversion' => [
                'labels' => $convMonths->pluck('label'),
                'bars' => $convMonths->map(fn ($m) => $clients->whereBetween('created_at', [$m['from'], $m['to']])->count()),
                'line' => $convMonths->map(function ($m) use ($clients, $wonId) {
                    $monthly = $clients->whereBetween('created_at', [$m['from'], $m['to']]);
                    $total = $monthly->count();

                    return $total
                        ? round($monthly->where('stage_id', $wonId)->count() / $total * 100)
                        : 0;
                }),
            ],
        ];

        // ===== قوائم أسفل الصفحة =====
        $latestRequests = $canRequests
            ? ContactRequest::with('requestType')->withReadBy($user)->latest()->take(5)->get()
            : collect();
        $latestProperties = $canProperties
            ? Property::with(['area', 'status'])->latest()->take(5)->get()
            : collect();

        return view('dashboard.index', [
            'stats' => $stats,
            'charts' => $charts,
            'latestRequests' => $latestRequests,
            'latestProperties' => $latestProperties,
            'today' => $this->arabicDate($now),
            'currency' => $currency,
            'openRequests' => $newId ? $clients->where('stage_id', $newId)->count() : 0,
            'openFollowUps' => $viewingId ? $clients->where('stage_id', $viewingId)->count() : 0,
        ]);
    }

    /** نسبة التغيّر بين الشهر الحالي والماضي */
    private function trend(float $current, float $previous): array
    {
        if ($previous <= 0) {
            return ['pct' => $current > 0 ? '+100%' : '0%', 'up' => $current > 0];
        }

        $pct = ($current - $previous) / $previous * 100;

        return ['pct' => ($pct >= 0 ? '+' : '').round($pct).'%', 'up' => $pct >= 0];
    }

    /** آخر N شهر مع حدود كل شهر واسمه بالعربي */
    private function lastMonths(CarbonImmutable $now, int $count): Collection
    {
        return collect(range($count - 1, 0))->map(function ($back) use ($now) {
            $from = $now->startOfMonth()->subMonths($back);

            return [
                'label' => self::MONTHS[(int) $from->format('n')],
                'from' => $from,
                'to' => $from->addMonth(),
            ];
        })->values();
    }

    /** الأربعاء، ٢٢ يوليو ٢٠٢٦ */
    private function arabicDate(CarbonImmutable $date): string
    {
        $days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

        return $days[(int) $date->format('w')].'، '
            .$date->format('j').' '.self::MONTHS[(int) $date->format('n')].' '.$date->format('Y');
    }
}
