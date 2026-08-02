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
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $prevStart = $monthStart->subMonth();

        $soldId = PropertyStatus::where('key', 'sold')->value('id');
        $wonId = ClientStage::where('key', 'closed_won')->value('id');

        // ===== الصفقات المغلقة + الإيرادات (عقارات مباعة) =====
        $sold = $soldId
            ? Property::where('status_id', $soldId)->get(['price', 'updated_at'])
            : collect();

        $soldThisMonth = $sold->where('updated_at', '>=', $monthStart);
        $soldPrevMonth = $sold->whereBetween('updated_at', [$prevStart, $monthStart]);

        // ===== بطاقات المؤشرات =====
        $properties = Property::get(['id', 'created_at']);
        $clients = Client::get(['id', 'created_at', 'stage_id']);

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
                'value' => number_format((float) $sold->sum('price')).' <span class="text-base font-bold">دينار</span>',
                'sub_value' => number_format((float) $soldThisMonth->sum('price')),
                'sub_label' => 'ايرادات هذا الشهر',
                'trend' => $this->trend((float) $soldThisMonth->sum('price'), (float) $soldPrevMonth->sum('price')),
                'tone' => 'primary',
                'icon' => 'money',
            ],
            [
                'key' => 'deals',
                'label' => 'اجمالي الصفقات المغلقة',
                'value' => number_format($sold->count()),
                'sub_value' => number_format($soldThisMonth->count()),
                'sub_label' => 'صفقات الشهر الحالي',
                'trend' => $this->trend($soldThisMonth->count(), $soldPrevMonth->count()),
                'tone' => 'success',
                'icon' => 'check',
            ],
        ];

        // ===== الرسوم البيانية =====
        $revenueMonths = $this->lastMonths($now, 8);
        $leadMonths = $this->lastMonths($now, 9);
        $convMonths = $this->lastMonths($now, 6);

        $requests = ContactRequest::get(['id', 'created_at', 'status']);

        $charts = [
            // الإيراد الشهري — بالآلاف
            'revenue' => [
                'labels' => $revenueMonths->pluck('label'),
                'data' => $revenueMonths->map(fn ($m) => round(
                    (float) $sold->whereBetween('updated_at', [$m['from'], $m['to']])->sum('price') / 1000, 1
                )),
            ],
            // الـ Leads حسب الشهر
            'leads' => [
                'labels' => $leadMonths->pluck('label'),
                'data' => $leadMonths->map(fn ($m) => $requests->whereBetween('created_at', [$m['from'], $m['to']])->count()),
            ],
            // معدل التحويل — أعمدة (طلبات) + خط (نسبة الإغلاق)
            'conversion' => [
                'labels' => $convMonths->pluck('label'),
                'bars' => $convMonths->map(fn ($m) => $requests->whereBetween('created_at', [$m['from'], $m['to']])->count()),
                'line' => $convMonths->map(function ($m) use ($requests) {
                    $total = $requests->whereBetween('created_at', [$m['from'], $m['to']])->count();

                    return $total
                        ? round($requests->whereBetween('created_at', [$m['from'], $m['to']])->where('status', 'contacted')->count() / $total * 100)
                        : 0;
                }),
            ],
        ];

        // ===== قوائم أسفل الصفحة =====
        $latestRequests = ContactRequest::with('requestType')->latest()->take(5)->get();
        $latestProperties = Property::with(['area', 'status'])->latest()->take(5)->get();

        return view('dashboard.index', [
            'stats' => $stats,
            'charts' => $charts,
            'latestRequests' => $latestRequests,
            'latestProperties' => $latestProperties,
            'today' => $this->arabicDate($now),
            'openRequests' => $requests->where('status', 'pending')->count(),
            'openFollowUps' => $wonId
                ? $clients->where('stage_id', '!=', $wonId)->count()
                : $clients->count(),
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
