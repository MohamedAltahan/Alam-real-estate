@extends('layouts.dashboard')

@section('title', 'المعاينات')
@section('page-title', 'المعاينات')

@php
    use App\Support\ClientFields;

    $filterSelect = 'appearance-none rounded-full bg-white border border-gray-200 ps-4 pe-10 h-11 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15';
    $filterInput = 'rounded-full bg-white border border-gray-200 px-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15';
    $chevron = '<svg class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>';
@endphp

@section('content')
<div>
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">مواعيد المعاينات</h2>
            <p class="text-sm text-gray-500">{{ number_format($viewings->total()) }} معاينة</p>
        </div>
        @can('reports.view')
            <a href="{{ route('dashboard.reports.conversion') }}" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white hover:bg-gray-50 text-sm font-semibold text-gray-700 px-4 h-11 transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3v18h18"/><path d="m7 15 4-5 4 3 5-7"/></svg>
                تقرير معدل التحول
            </a>
        @endcan
    </div>

    {{-- الفلاتر (خارج منطقة النتائج) --}}
    <form method="GET" id="viewings-filters" data-live-filters class="flex flex-wrap items-center gap-3 mb-4">
        <div class="relative flex-1 min-w-[220px]">
            <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث باسم العميل أو رقم العقار..." autocomplete="off" class="w-full {{ $filterInput }} ps-11">
        </div>
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
            <select name="outcome" class="{{ $filterSelect }}">
                <option value="">كل النتائج</option>
                @foreach ($outcomes as $value => $text)<option value="{{ $value }}" @selected(($filters['outcome'] ?? '') === $value)>{{ $text }}</option>@endforeach
            </select>
            {!! $chevron !!}
        </div>
        @if (array_filter($filters))
            <a href="{{ route('dashboard.viewings.index') }}" class="text-sm text-gray-500 hover:text-danger whitespace-nowrap">مسح الفلاتر</a>
        @endif
    </form>

    <div data-results>
        <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 w-12">#</th>
                            <th class="text-start font-medium px-4 py-3">العميل</th>
                            <th class="text-start font-medium px-4 py-3">العقار</th>
                            <th class="text-start font-medium px-4 py-3">الموعد</th>
                            <th class="text-start font-medium px-4 py-3">حضوري</th>
                            <th class="text-start font-medium px-4 py-3">المسؤول</th>
                            <th class="text-start font-medium px-4 py-3">النتيجة</th>
                            <th class="text-start font-medium px-4 py-3">ملاحظة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($viewings as $viewing)
                            @php
                                $isPast = $viewing->scheduled_at?->isPast();
                                $agent = $viewing->client?->agent ?? $viewing->property?->agent;
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $viewings->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3">
                                    @if ($viewing->client)
                                        <a href="{{ route('dashboard.clients.show', $viewing->client) }}" class="font-semibold text-ink hover:text-primary-700">{{ $viewing->client->name }}</a>
                                        <span class="block text-xs text-gray-400" dir="ltr">{{ $viewing->client->full_phone }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($viewing->property)
                                        @can('properties.view')
                                            <a href="{{ route('dashboard.properties.show', $viewing->property) }}" class="font-semibold text-ink hover:text-primary-700" dir="ltr">{{ $viewing->property->reference_code }}</a>
                                        @else
                                            <span class="font-semibold text-ink" dir="ltr">{{ $viewing->property->reference_code }}</span>
                                        @endcan
                                        <span class="block text-xs text-gray-400 truncate max-w-[220px]">{{ $viewing->property->title }}@if ($viewing->property->area) · {{ $viewing->property->area->name }}@endif</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="block text-ink font-medium" dir="ltr">{{ $viewing->scheduled_at?->format('Y-m-d') }}</span>
                                    <span class="block text-xs {{ $isPast ? 'text-gray-400' : 'text-primary-600' }}" dir="ltr">{{ $viewing->scheduled_at?->format('h:i A') }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $viewing->in_person ? 'نعم' : 'لا' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $agent?->name ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @can('clients.edit')
                                        <form method="POST" action="{{ route('dashboard.viewings.outcome', $viewing) }}">
                                            @csrf @method('PATCH')
                                            <select name="outcome" onchange="this.form.requestSubmit()" class="appearance-none rounded-full border-0 ps-3 pe-8 py-1 text-xs font-semibold cursor-pointer {{ ClientFields::outcomeTone($viewing->outcome) }}">
                                                @foreach ($outcomes as $value => $text)<option value="{{ $value }}" @selected($viewing->outcome === $value)>{{ $text }}</option>@endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ ClientFields::outcomeTone($viewing->outcome) }}">{{ ClientFields::outcomeLabel($viewing->outcome) }}</span>
                                    @endcan
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 max-w-[200px]"><span class="block truncate" title="{{ $viewing->notes }}">{{ $viewing->notes ?: '—' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400">لا توجد معاينات مطابقة.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $viewings->links() }}</div>
    </div>
</div>
@endsection
