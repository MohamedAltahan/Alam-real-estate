@extends('layouts.dashboard')

@section('title', 'سجل النشاط')
@section('page-title', 'سجل النشاط')

@php
    $activeEvent = (string) ($filters['event'] ?? '');
    // رابط زر العملية = نفس الفلاتر الحالية مع تبديل event فقط
    $eventUrl = fn (?string $key) => route('dashboard.activity.index',
        array_filter(array_merge($filters, ['event' => $key]), fn ($v) => $v !== null && $v !== ''));
    $pill = 'inline-flex items-center gap-2 rounded-full h-9 px-3.5 text-sm font-semibold whitespace-nowrap transition';
    $pillOn = $pill.' bg-primary-900 text-white';
    $pillOff = $pill.' text-gray-600 hover:bg-white hover:text-ink';
    $badgeOn = 'grid place-items-center min-w-5 h-5 px-1 rounded-full bg-white/20 text-white text-[11px] font-bold tabular-nums';
    $badgeOff = 'grid place-items-center min-w-5 h-5 px-1 rounded-full bg-gray-200/80 text-gray-600 text-[11px] font-bold tabular-nums';
    // الفلاتر المتقدمة (كل شيء عدا البحث والعملية) — اللوحة تُفتح تلقائياً لو أحدها مفعّل
    $advancedActive = collect($filters)->except(['search', 'event'])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $hasFilters = (bool) array_filter($filters, fn ($v) => $v !== null && $v !== '');
@endphp

@section('content')
<div x-data="{ filtersOpen: {{ $advancedActive ? 'true' : 'false' }} }">
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">سجل النشاط</h2>
            <p class="text-sm text-gray-500">{{ number_format($logs->total()) }} حركة — كل إضافة وتعديل وحذف على العقارات والملاك والعملاء والمعاينات والمهام والمستخدمين والأدوار</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            {{-- البحث دائم الظهور وينضمّ لنموذج الفلاتر بالخاصية form --}}
            <div class="relative w-[260px] max-w-[55vw]">
                <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="باسم السجل أو رقم العقار..." autocomplete="off" form="activity-filters"
                       class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
            </div>

            <button type="button" @click="filtersOpen = ! filtersOpen"
                    :class="filtersOpen ? 'bg-primary-50 border-primary-200 text-primary-800' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="inline-flex items-center gap-2 rounded-full border px-4 h-11 text-sm font-semibold transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                فلاتر
                @if ($advancedActive)<span class="w-2 h-2 rounded-full bg-accent-500"></span>@endif
            </button>
        </div>
    </div>

    {{-- الفلاتر المتقدمة (خارج منطقة النتائج حتى لا تُستبدَل مع كل فلترة) — تُطبَّق فور الاختيار --}}
    <div x-show="filtersOpen" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 -translate-y-2">
    <x-filter-bar id="activity-filters" cols="xl:grid-cols-4" :reset="$hasFilters ? route('dashboard.activity.index') : null">
        <x-filter-input label="من تاريخ" name="from" :value="$filters['from'] ?? ''" datepicker placeholder="من" />
        <x-filter-input label="إلى تاريخ" name="to" :value="$filters['to'] ?? ''" datepicker placeholder="إلى" />
        <x-filter-select label="المستخدم" name="user_id" placeholder="كل المستخدمين"
                         :options="$users->pluck('name', 'id')" :selected="$filters['user_id'] ?? null" />
        <x-filter-select label="الوحدة" name="module" placeholder="كل الوحدات"
                         :options="$modules" :selected="$filters['module'] ?? null" />
    </x-filter-bar>
    </div>

    {{-- حقل العملية ينضمّ للنموذج ليبدّله صفّ الأزرار داخل النتائج --}}
    <input type="hidden" name="event" value="{{ $activeEvent }}" form="activity-filters">

    <div data-results>
        {{-- صفّ العملية (إضافة · تعديل · حذف) — يبدّل حقل event في نموذج الفلاتر --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="inline-flex items-center gap-1 rounded-full bg-gray-100/70 border border-gray-100 p-1 max-w-full overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <a href="{{ $eventUrl(null) }}" data-filter-set="event" data-filter-value="" class="{{ $activeEvent === '' ? $pillOn : $pillOff }}">
                    كل الحركات
                    <span class="{{ $activeEvent === '' ? $badgeOn : $badgeOff }}">{{ $eventCounts['total'] }}</span>
                </a>
                @foreach ($events as $key => $label)
                    @php $on = $activeEvent === $key; @endphp
                    <a href="{{ $eventUrl($key) }}" data-filter-set="event" data-filter-value="{{ $key }}" class="{{ $on ? $pillOn : $pillOff }}">
                        {{ $label }}
                        <span class="{{ $on ? $badgeOn : $badgeOff }}">{{ $eventCounts['events'][$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            @if ($hasFilters)
                <a href="{{ route('dashboard.activity.index') }}" class="text-sm text-gray-500 hover:text-danger whitespace-nowrap">مسح الفلاتر</a>
            @endif
        </div>

        <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 whitespace-nowrap">التاريخ والوقت</th>
                            <th class="text-start font-medium px-4 py-3">المستخدم</th>
                            <th class="text-start font-medium px-4 py-3">العملية</th>
                            <th class="text-start font-medium px-4 py-3">الوحدة</th>
                            <th class="text-start font-medium px-4 py-3">السجل</th>
                            <th class="text-start font-medium px-4 py-3">التفاصيل</th>
                        </tr>
                    </thead>
                    @forelse ($rows as $row)
                        {{-- tbody لكل حركة: صف التفاصيل يُفتح تحته بنطاق Alpine خاص --}}
                        <tbody x-data="{ open: false }" class="border-b border-gray-50 last:border-0">
                            <tr class="hover:bg-gray-50/50 transition align-top">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="block text-ink font-medium"><bdi dir="ltr">{{ $row['at']?->format('Y-m-d') }}</bdi></span>
                                    <span class="block text-xs text-gray-400"><bdi dir="ltr">{{ $row['at']?->format('h:i A') }}</bdi></span>
                                </td>
                                <td class="px-4 py-3 text-ink">{{ $row['user'] ?? 'النظام' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $row['tone'] }}">{{ $row['event_label'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $row['module_label'] }}</td>
                                <td class="px-4 py-3 max-w-[280px]">
                                    @if ($row['url'])
                                        <a href="{{ $row['url'] }}" class="font-semibold text-ink hover:text-primary-700" dir="auto">{{ $row['subject'] ?: '—' }}</a>
                                    @else
                                        <span class="text-gray-500" dir="auto">{{ $row['subject'] ?: '—' }}</span>
                                        @if ($row['event'] === 'deleted' || $row['subject'])<span class="text-[11px] text-gray-400 ms-1">(محذوف)</span>@endif
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($row['lines'])
                                        <button type="button" @click="open = ! open" :aria-expanded="open"
                                                class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white hover:bg-gray-50 px-3 h-8 text-xs font-semibold text-gray-700 transition">
                                            <span>{{ count($row['lines']) }} {{ count($row['lines']) === 1 ? 'تغيير' : (count($row['lines']) <= 10 ? 'تغييرات' : 'تغييراً') }}</span>
                                            <svg class="transition" :class="open ? 'rotate-180' : ''" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>
                            @if ($row['lines'])
                                <tr x-show="open" x-cloak class="bg-gray-50/40">
                                    <td colspan="6" class="px-6 pb-4 pt-1">
                                        <ul class="space-y-1 text-xs">
                                            @foreach ($row['lines'] as $line)
                                                <li class="flex flex-wrap items-center gap-1.5">
                                                    <span class="text-gray-400">{{ $line['field'] }}:</span>
                                                    @if ($line['old'] !== null)
                                                        <span class="line-through text-gray-400 break-all" dir="auto">{{ $line['old'] }}</span>
                                                    @endif
                                                    @if ($line['old'] !== null && $line['new'] !== null)
                                                        <span class="text-gray-300">←</span>
                                                    @endif
                                                    @if ($line['new'] !== null)
                                                        <span class="font-semibold text-ink break-all" dir="auto">{{ $line['new'] }}</span>
                                                    @elseif ($line['old'] !== null)
                                                        <span class="text-danger text-[11px]">(حُذف)</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    @empty
                        <tbody>
                            <tr><td colspan="6" class="px-4 py-16 text-center text-gray-400">لا توجد حركات مطابقة.</td></tr>
                        </tbody>
                    @endforelse
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
