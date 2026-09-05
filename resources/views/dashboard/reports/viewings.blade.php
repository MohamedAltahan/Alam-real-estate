@extends('layouts.dashboard')

@section('title', 'تقرير واتساب المعاينات')
@section('page-title', 'تقرير واتساب المعاينات')

@php
    use App\Models\ClientViewing;
    use App\Support\ClientFields;

    $kpis = $report['kpis'];
    $filterInput = 'rounded-full bg-white border border-gray-200 px-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15';
    $filterSelect = 'appearance-none rounded-full bg-white border border-gray-200 ps-4 pe-10 h-11 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15';
    $chevron = '<svg class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>';
    $cards = [
        ['label' => 'إجمالي المعاينات', 'value' => $kpis['total'], 'tone' => 'bg-info-soft text-info'],
        ['label' => 'مكتملة (العلامتان)', 'value' => $kpis['complete'], 'tone' => 'bg-success-soft text-success'],
        ['label' => 'أُبلغ المالك', 'value' => $kpis['owner_sent'], 'tone' => 'bg-primary-50 text-primary-700'],
        ['label' => 'أُرسلت المتابعة', 'value' => $kpis['client_sent'], 'tone' => 'bg-primary-50 text-primary-700'],
        ['label' => 'بلا إبلاغ المالك', 'value' => $kpis['missing_owner'], 'tone' => 'bg-danger/10 text-danger'],
        ['label' => 'بلا متابعة', 'value' => $kpis['missing_client'], 'tone' => 'bg-warning-soft text-warning'],
    ];
    $check = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
    $cross = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
@endphp

@section('content')
<div>
    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">واتساب المعاينات</h2>
            <p class="text-sm text-gray-500">لكل معاينة علامتان: إرسال بيانات العميل للمالك، وإرسال المتابعة للعميل — من <span dir="ltr">{{ $report['from']->format('Y-m-d') }}</span> إلى <span dir="ltr">{{ $report['to']->format('Y-m-d') }}</span></p>
        </div>
        <form method="GET" id="wa-report-filters" data-live-filters class="flex flex-wrap items-center gap-3">
            <input name="from" data-datepicker value="{{ $filters['from'] ?? '' }}" placeholder="من تاريخ" class="{{ $filterInput }} w-40">
            <input name="to" data-datepicker value="{{ $filters['to'] ?? '' }}" placeholder="إلى تاريخ" class="{{ $filterInput }} w-40">
            <div class="relative">
                <select name="agent_id" class="{{ $filterSelect }}">
                    <option value="">كل المسؤولين</option>
                    @foreach ($agents as $agent)<option value="{{ $agent->id }}" @selected(($filters['agent_id'] ?? '') == $agent->id)>{{ $agent->name }}</option>@endforeach
                </select>
                {!! $chevron !!}
            </div>
            <div class="relative">
                <select name="state" class="{{ $filterSelect }}">
                    <option value="">كل المعاينات</option>
                    @foreach ($states as $value => $label)<option value="{{ $value }}" @selected(($filters['state'] ?? '') === $value)>{{ $label }}</option>@endforeach
                </select>
                {!! $chevron !!}
            </div>
        </form>
    </div>

    <div data-results>
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
                            <th class="text-start font-medium px-4 py-3">المسؤول</th>
                            <th class="text-start font-medium px-4 py-3">النتيجة</th>
                            <th class="text-start font-medium px-4 py-3">بيانات العميل للمالك</th>
                            <th class="text-start font-medium px-4 py-3">متابعة العميل</th>
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
                                        <span class="block text-xs text-gray-400" dir="ltr">{{ $viewing->client->full_phone }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-ink" dir="ltr">{{ $viewing->property?->reference_code ?? '—' }}</span>
                                    <span class="block text-xs text-gray-400 truncate max-w-[200px]">{{ $viewing->property?->title }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap" dir="ltr">{{ $viewing->scheduled_at?->format('Y-m-d h:i A') }}</td>
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
