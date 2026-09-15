@extends('layouts.dashboard')

@section('title', 'تقرير واتساب المعاينات')
@section('page-title', 'تقرير واتساب المعاينات')

@php
    use App\Models\ClientViewing;
    use App\Support\ClientFields;

    $kpis = $report['kpis'];
    $cards = [
        ['label' => 'إجمالي المعاينات', 'value' => $kpis['total'], 'tone' => 'bg-info-soft text-info'],
        ['label' => 'مكتملة (العلامتان)', 'value' => $kpis['complete'], 'tone' => 'bg-success-soft text-success'],
        ['label' => 'أُبلغ المسؤول', 'value' => $kpis['owner_sent'], 'tone' => 'bg-primary-50 text-primary-700'],
        ['label' => 'أُرسلت النتيجة', 'value' => $kpis['client_sent'], 'tone' => 'bg-primary-50 text-primary-700'],
        ['label' => 'بلا إبلاغ المسؤول', 'value' => $kpis['missing_owner'], 'tone' => 'bg-danger/10 text-danger'],
        ['label' => 'بلا إرسال النتيجة', 'value' => $kpis['missing_client'], 'tone' => 'bg-warning-soft text-warning'],
    ];
    $check = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
    $cross = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
@endphp

@section('content')
<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">واتساب المعاينات</h2>
            <p class="text-sm text-gray-500">لكل معاينة علامتان: إرسال بيانات العميل لمسؤول العقار، وإرسال نتيجة المعاينة له</p>
        </div>
        @include('dashboard.reports._tabs', ['tab' => 'whatsapp'])
    </div>

    <x-filter-bar id="wa-report-filters" cols="xl:grid-cols-4" :reset="array_filter($filters) ? route('dashboard.reports.viewings') : null">
        <x-filter-input label="من تاريخ" name="from" :value="$filters['from'] ?? ''" datepicker placeholder="من" />
        <x-filter-input label="إلى تاريخ" name="to" :value="$filters['to'] ?? ''" datepicker placeholder="إلى" />
        <x-filter-select label="مندوب المبيعات" name="agent_id" placeholder="كل مندوبي المبيعات"
                         :options="$agents->pluck('name', 'id')" :selected="$filters['agent_id'] ?? null" />
        <x-filter-select label="حالة الإرسال" name="state" placeholder="كل المعاينات"
                         :options="$states" :selected="$filters['state'] ?? null" />
    </x-filter-bar>

    <div data-results>
        <p class="text-xs text-gray-400 mb-3">الفترة من <bdi dir="ltr">{{ $report['from']->format('Y-m-d') }}</bdi> إلى <bdi dir="ltr">{{ $report['to']->format('Y-m-d') }}</bdi></p>
        @if ($report['shortened'])
            <p class="mb-3 rounded-field bg-warning-soft text-warning text-xs px-4 py-2.5">
                الفترة المطلوبة أطول من {{ \App\Services\ViewingService::MAX_RANGE_MONTHS }} شهراً، فعُرضت أول {{ \App\Services\ViewingService::MAX_RANGE_MONTHS }} شهراً منها.
                اختر فترة أقصر لعرض باقي المدة.
            </p>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-5">
            @foreach ($cards as $card)
                <div class="rounded-2xl bg-white border border-gray-100 p-4">
                    <p class="text-xs text-gray-400 mb-1">{{ $card['label'] }}</p>
                    <p class="text-2xl font-bold tabular-nums"><span class="rounded-lg px-2 {{ $card['tone'] }}">{{ number_format($card['value']) }}</span></p>
                </div>
            @endforeach
        </div>

        <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 w-12">#</th>
                            <th class="text-start font-medium px-4 py-3">العميل</th>
                            <th class="text-start font-medium px-4 py-3">العقار</th>
                            <th class="text-start font-medium px-4 py-3">الموعد</th>
                            <th class="text-start font-medium px-4 py-3">مندوب المبيعات</th>
                            <th class="text-start font-medium px-4 py-3">النتيجة</th>
                            <th class="text-start font-medium px-4 py-3">إبلاغ المسؤول</th>
                            <th class="text-start font-medium px-4 py-3">إرسال النتيجة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($report['rows'] as $viewing)
                            @php
                                $agent = $viewing->client?->agent ?? $viewing->property?->agent;
                                $complete = $viewing->owner_notified_at && $viewing->client_followed_up_at;
                                $pending = $viewing->outcome === ClientViewing::OUTCOME_PENDING;
                            @endphp
                            <tr class="hover:bg-gray-50/50 {{ $complete ? '' : 'bg-danger/[0.02]' }}">
                                <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3">
                                    @if ($viewing->client)
                                        <a href="{{ route('dashboard.clients.show', $viewing->client) }}" class="font-semibold text-ink hover:text-primary-700">{{ $viewing->client->name }}</a>
                                        <span class="block text-xs text-gray-400"><bdi dir="ltr">{{ $viewing->client->full_phone }}</bdi></span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-ink" dir="ltr">{{ $viewing->property?->reference_code ?? '—' }}</span>
                                    <span class="block text-xs text-gray-400 truncate max-w-[200px]">{{ $viewing->property?->title }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><bdi dir="ltr">{{ $viewing->scheduled_at?->format('Y-m-d h:i A') }}</bdi></td>
                                <td class="px-4 py-3 text-gray-600">{{ $agent?->name ?: '—' }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ ClientFields::outcomeTone($viewing->outcome) }}">{{ ClientFields::outcomeLabel($viewing->outcome) }}</span></td>
                                <td class="px-4 py-3">
                                    @if ($viewing->owner_notified_at)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-success-soft text-success px-2.5 py-1 text-xs font-semibold">{!! $check !!} تم <span dir="ltr" class="font-normal text-[11px]">{{ $viewing->owner_notified_at->format('Y-m-d') }}</span></span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-danger/10 text-danger px-2.5 py-1 text-xs font-semibold">{!! $cross !!} لم يُرسل</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($viewing->client_followed_up_at)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-success-soft text-success px-2.5 py-1 text-xs font-semibold">{!! $check !!} تم <span dir="ltr" class="font-normal text-[11px]">{{ $viewing->client_followed_up_at->format('Y-m-d') }}</span></span>
                                    @elseif ($pending)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 text-gray-500 px-2.5 py-1 text-xs font-semibold" title="بانتظار تسجيل نتيجة المعاينة">بانتظار النتيجة</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-warning-soft text-warning px-2.5 py-1 text-xs font-semibold">{!! $cross !!} لم تُرسل</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400">لا توجد معاينات في هذه الفترة.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
