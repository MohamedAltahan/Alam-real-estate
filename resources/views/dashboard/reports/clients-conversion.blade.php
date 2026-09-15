@extends('layouts.dashboard')

@section('title', 'تقرير تحول العملاء')
@section('page-title', 'تقرير تحول العملاء')

@php
    $kpis = $report['kpis'];
    $cards = [
        ['label' => 'إجمالي العملاء', 'value' => $kpis['total'], 'tone' => 'bg-info-soft text-info', 'icon' => '<circle cx="9" cy="8" r="4"/><path d="M17 21a8 8 0 0 0-16 0"/><path d="M19 8v6M16 11h6"/>'],
        ['label' => 'ربح', 'value' => $kpis['won'], 'tone' => 'bg-success-soft text-success', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>'],
        ['label' => 'خسارة', 'value' => $kpis['lost'], 'tone' => 'bg-danger/10 text-danger', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>'],
        ['label' => 'قيد المتابعة', 'value' => $kpis['open'], 'tone' => 'bg-warning-soft text-warning', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
    ];
@endphp

@section('content')
<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">تحول العملاء</h2>
            <p class="text-sm text-gray-500">نسبة العملاء الذين وصلوا إلى مرحلة «ربح» من كل العملاء المسجّلين في الفترة — لكل مندوب مبيعات</p>
        </div>
        @include('dashboard.reports._tabs', ['tab' => 'clients'])
    </div>

    <x-filter-bar id="clients-conversion-filters" cols="xl:grid-cols-4" :reset="array_filter($filters) ? route('dashboard.reports.clients-conversion') : null">
        <x-filter-input label="مسجّل من تاريخ" name="from" :value="$filters['from'] ?? ''" datepicker placeholder="من" />
        <x-filter-input label="إلى تاريخ" name="to" :value="$filters['to'] ?? ''" datepicker placeholder="إلى" />
        <x-filter-select label="مندوب المبيعات" name="agent_id" placeholder="كل مندوبي المبيعات"
                         :options="$agents->pluck('name', 'id')" :selected="$filters['agent_id'] ?? null" />
    </x-filter-bar>

    <div data-results>
    <p class="text-xs text-gray-400 mb-3">الفترة من <bdi dir="ltr">{{ $report['from']->format('Y-m-d') }}</bdi> إلى <bdi dir="ltr">{{ $report['to']->format('Y-m-d') }}</bdi> (بتاريخ تسجيل العميل)</p>
        @if ($report['shortened'])
            <p class="mb-3 rounded-field bg-warning-soft text-warning text-xs px-4 py-2.5">
                الفترة المطلوبة أطول من {{ \App\Support\ReportRange::MAX_MONTHS }} شهراً، فعُرضت أول {{ \App\Support\ReportRange::MAX_MONTHS }} شهراً منها.
                اختر فترة أقصر لعرض باقي المدة.
            </p>
        @endif

    {{-- المؤشرات --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-5">
        @foreach ($cards as $card)
            <div class="rounded-2xl bg-white border border-gray-100 p-4 flex items-center gap-3">
                <span class="grid place-items-center w-11 h-11 rounded-full shrink-0 {{ $card['tone'] }}"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $card['icon'] !!}</svg></span>
                <div><p class="text-xs text-gray-400">{{ $card['label'] }}</p><p class="text-2xl font-bold text-ink tabular-nums">{{ number_format($card['value']) }}</p></div>
            </div>
        @endforeach
        <div class="rounded-2xl bg-primary-900 text-white p-4 flex flex-col justify-center col-span-2 lg:col-span-1">
            <p class="text-xs text-white/60">معدل تحول العملاء</p>
            <p class="text-3xl font-bold tabular-nums">{{ $kpis['rate'] }}<span class="text-lg">%</span></p>
            <p class="text-[11px] text-white/50 mt-1">ربح ÷ كل العملاء = {{ $kpis['won'] }} ÷ {{ $kpis['total'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
        {{-- الرسم الشهري --}}
        <section class="xl:col-span-3 rounded-2xl bg-white border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-ink">العملاء الجدد ونسبة الربح شهريًا</h3>
                <div class="flex items-center gap-4 text-[11px] text-gray-500">
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-[#5c6484]"></span>عدد العملاء</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-1 rounded bg-[#4eae7f]"></span>نسبة الربح %</span>
                </div>
            </div>
            <div class="h-72">
                <canvas data-chart="conversion" data-payload="{{ json_encode(['labels' => $report['monthly']['labels'], 'bars' => $report['monthly']['bars'], 'line' => $report['monthly']['line'], 'barLabel' => 'عدد العملاء', 'lineLabel' => 'نسبة الربح'], JSON_UNESCAPED_UNICODE) }}"></canvas>
            </div>
        </section>

        {{-- حسب مندوب المبيعات --}}
        <section class="xl:col-span-2 rounded-2xl bg-white border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-bold text-ink">حسب مندوب المبيعات</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 w-10">#</th>
                            <th class="text-start font-medium px-4 py-3">مندوب المبيعات</th>
                            <th class="text-start font-medium px-4 py-3">العملاء</th>
                            <th class="text-start font-medium px-4 py-3">ربح</th>
                            <th class="text-start font-medium px-4 py-3">خسارة</th>
                            <th class="text-start font-medium px-4 py-3">المعدل</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($report['byAgent'] as $row)
                            <tr>
                                <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 font-semibold text-ink">{{ $row['name'] }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ $row['total'] }}</td>
                                <td class="px-4 py-3 tabular-nums text-success">{{ $row['won'] }}</td>
                                <td class="px-4 py-3 tabular-nums text-danger">{{ $row['lost'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-16 h-1.5 rounded-full bg-gray-100 overflow-hidden"><span class="block h-full bg-success" style="width: {{ $row['rate'] }}%"></span></span>
                                        <span class="text-xs font-bold tabular-nums">{{ $row['rate'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">لا يوجد عملاء مسجّلون في هذه الفترة.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    </div>{{-- /منطقة النتائج --}}
</div>
@endsection
