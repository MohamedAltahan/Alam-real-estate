@extends('layouts.dashboard')

@section('title', 'تقرير معدل التحول')
@section('page-title', 'تقرير معدل التحول')

@php
    $kpis = $report['kpis'];
    $cards = [
        ['label' => 'إجمالي المعاينات', 'value' => $kpis['total'], 'tone' => 'bg-info-soft text-info', 'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
        ['label' => 'تم اختيار العقار', 'value' => $kpis['chosen'], 'tone' => 'bg-success-soft text-success', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>'],
        ['label' => 'لم يختر', 'value' => $kpis['rejected'], 'tone' => 'bg-danger/10 text-danger', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>'],
        ['label' => 'قيد الانتظار', 'value' => $kpis['pending'], 'tone' => 'bg-warning-soft text-warning', 'icon' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
    ];
@endphp

@section('content')
<div>
    <div class="mb-5">
        <h2 class="text-xl font-bold text-ink">معدل التحول</h2>
        <p class="text-sm text-gray-500">نسبة المعاينات التي انتهت باختيار العقار</p>
    </div>

    <x-filter-bar id="conversion-filters" cols="xl:grid-cols-4" :reset="array_filter($filters) ? route('dashboard.reports.conversion') : null">
        <x-filter-input label="من تاريخ" name="from" :value="$filters['from'] ?? ''" datepicker placeholder="من" />
        <x-filter-input label="إلى تاريخ" name="to" :value="$filters['to'] ?? ''" datepicker placeholder="إلى" />
        <x-filter-select label="المسؤول" name="agent_id" placeholder="كل المسؤولين"
                         :options="$agents->pluck('name', 'id')" :selected="$filters['agent_id'] ?? null" />
    </x-filter-bar>

    <div data-results>
    <p class="text-xs text-gray-400 mb-3">الفترة من <bdi dir="ltr">{{ $report['from']->format('Y-m-d') }}</bdi> إلى <bdi dir="ltr">{{ $report['to']->format('Y-m-d') }}</bdi></p>
        @if ($report['shortened'])
            <p class="mb-3 rounded-field bg-warning-soft text-warning text-xs px-4 py-2.5">
                الفترة المطلوبة أطول من {{ \App\Services\ViewingService::MAX_RANGE_MONTHS }} شهراً، فعُرضت أول {{ \App\Services\ViewingService::MAX_RANGE_MONTHS }} شهراً منها.
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
            <p class="text-xs text-white/60">معدل التحول</p>
            <p class="text-3xl font-bold tabular-nums">{{ $kpis['rate'] }}<span class="text-lg">%</span></p>
            <p class="text-[11px] text-white/50 mt-1">اختار ÷ (اختار + لم يختر) = {{ $kpis['chosen'] }} ÷ {{ $kpis['decided'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">
        {{-- الرسم الشهري --}}
        <section class="xl:col-span-3 rounded-2xl bg-white border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-ink">المعاينات ونسبة الاختيار شهريًا</h3>
                <div class="flex items-center gap-4 text-[11px] text-gray-500">
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-[#5c6484]"></span>عدد المعاينات</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-3 h-1 rounded bg-[#4eae7f]"></span>نسبة الاختيار %</span>
                </div>
            </div>
            <div class="h-72">
                <canvas data-chart="conversion" data-payload="{{ json_encode(['labels' => $report['monthly']['labels'], 'bars' => $report['monthly']['bars'], 'line' => $report['monthly']['line'], 'barLabel' => 'عدد المعاينات', 'lineLabel' => 'نسبة الاختيار'], JSON_UNESCAPED_UNICODE) }}"></canvas>
            </div>
        </section>

        {{-- حسب المسؤول --}}
        <section class="xl:col-span-2 rounded-2xl bg-white border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100"><h3 class="font-bold text-ink">حسب مندوب المبيعات</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 w-10">#</th>
                            <th class="text-start font-medium px-4 py-3">المسؤول</th>
                            <th class="text-start font-medium px-4 py-3">المعاينات</th>
                            <th class="text-start font-medium px-4 py-3">اختار</th>
                            <th class="text-start font-medium px-4 py-3">لم يختر</th>
                            <th class="text-start font-medium px-4 py-3">المعدل</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($report['byAgent'] as $row)
                            <tr>
                                <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 font-semibold text-ink">{{ $row['name'] }}</td>
                                <td class="px-4 py-3 tabular-nums">{{ $row['total'] }}</td>
                                <td class="px-4 py-3 tabular-nums text-success">{{ $row['chosen'] }}</td>
                                <td class="px-4 py-3 tabular-nums text-danger">{{ $row['rejected'] }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-16 h-1.5 rounded-full bg-gray-100 overflow-hidden"><span class="block h-full bg-success" style="width: {{ $row['rate'] }}%"></span></span>
                                        <span class="text-xs font-bold tabular-nums">{{ $row['rate'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-gray-400">لا توجد معاينات في هذه الفترة.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    </div>{{-- /منطقة النتائج --}}
</div>
@endsection
