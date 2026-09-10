@extends('layouts.dashboard')

@section('title', 'المعاينات')
@section('page-title', 'المعاينات')

@php
    use App\Support\ClientFields;
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
            <a href="{{ route('dashboard.reports.viewings') }}" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white hover:bg-gray-50 text-sm font-semibold text-gray-700 px-4 h-11 transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                تقرير واتساب المعاينات
            </a>
        @endcan
    </div>

    {{-- الفلاتر (خارج منطقة النتائج) — تُطبَّق فور الاختيار --}}
    <x-filter-bar id="viewings-filters" cols="xl:grid-cols-5" :reset="array_filter($filters) ? route('dashboard.viewings.index') : null">
        <x-filter-input label="بحث" name="search" :value="$filters['search'] ?? ''" type="search" search
                        placeholder="باسم العميل أو رقم العقار..." span="col-span-2 md:col-span-1" />
        <x-filter-input label="من تاريخ" name="from" :value="$filters['from'] ?? ''" datepicker placeholder="من" />
        <x-filter-input label="إلى تاريخ" name="to" :value="$filters['to'] ?? ''" datepicker placeholder="إلى" />
        <x-filter-select label="المسؤول" name="agent_id" placeholder="كل المسؤولين"
                         :options="$agents->pluck('name', 'id')" :selected="$filters['agent_id'] ?? null" />
        <x-filter-select label="النتيجة" name="outcome" placeholder="كل النتائج"
                         :options="$outcomes" :selected="$filters['outcome'] ?? null" />
    </x-filter-bar>

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
                            <th class="text-start font-medium px-4 py-3">واتساب</th>
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
                                        <span class="block text-xs text-gray-400"><bdi dir="ltr">{{ $viewing->client->full_phone }}</bdi></span>
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
                                    <span class="block text-ink font-medium"><bdi dir="ltr">{{ $viewing->scheduled_at?->format('Y-m-d') }}</bdi></span>
                                    <span class="block text-xs {{ $isPast ? 'text-gray-400' : 'text-primary-600' }}"><bdi dir="ltr">{{ $viewing->scheduled_at?->format('h:i A') }}</bdi></span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $viewing->in_person ? 'نعم' : 'لا' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $agent?->name ?: '—' }}</td>
                                <td class="px-4 py-3">@include('dashboard.viewings._outcome', ['viewing' => $viewing])</td>
                                <td class="px-4 py-3">@include('dashboard.viewings._wa', ['viewing' => $viewing])</td>
                                <td class="px-4 py-3 text-xs text-gray-500 max-w-[200px]"><span class="block truncate" title="{{ $viewing->notes }}">{{ $viewing->notes ?: '—' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-16 text-center text-gray-400">لا توجد معاينات مطابقة.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $viewings->links() }}</div>
    </div>

    @include('dashboard.viewings._wa-modal')
</div>
@endsection
