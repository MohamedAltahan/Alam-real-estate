@extends('layouts.dashboard')

@section('title', 'المعاينات')
@section('page-title', 'المعاينات')

@php
    use App\Support\ClientFields;

    $activeOutcome = (string) ($filters['outcome'] ?? '');
    // رابط زر النتيجة = نفس الفلاتر الحالية مع تبديل outcome فقط
    $outcomeUrl = fn (?string $key) => route('dashboard.viewings.index',
        array_filter(array_merge($filters, ['outcome' => $key]), fn ($v) => $v !== null && $v !== ''));
    $pill = 'inline-flex items-center gap-2 rounded-full h-9 px-3.5 text-sm font-semibold whitespace-nowrap transition';
    $pillOn = $pill.' bg-primary-900 text-white';
    $pillOff = $pill.' text-gray-600 hover:bg-white hover:text-ink';
    $badgeOn = 'grid place-items-center min-w-5 h-5 px-1 rounded-full bg-white/20 text-white text-[11px] font-bold tabular-nums';
    $badgeOff = 'grid place-items-center min-w-5 h-5 px-1 rounded-full bg-gray-200/80 text-gray-600 text-[11px] font-bold tabular-nums';
    // الفلاتر المتقدمة (كل شيء عدا البحث والنتيجة) — اللوحة تُفتح تلقائياً لو أحدها مفعّل
    $advancedActive = collect($filters)->except(['search', 'outcome'])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@section('content')
<div x-data="{ filtersOpen: {{ $advancedActive ? 'true' : 'false' }} }">
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">مواعيد المعاينات</h2>
            <p class="text-sm text-gray-500">{{ number_format($viewings->total()) }} معاينة</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            {{-- البحث دائم الظهور وينضمّ لنموذج الفلاتر بالخاصية form --}}
            <div class="relative w-[260px] max-w-[55vw]">
                <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="باسم العميل أو رقم العقار..." autocomplete="off" form="viewings-filters"
                       class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
            </div>

            {{-- إظهار/إخفاء الفلاتر المتقدمة (مثل شاشة العملاء) --}}
            <button type="button" @click="filtersOpen = ! filtersOpen"
                    :class="filtersOpen ? 'bg-primary-50 border-primary-200 text-primary-800' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="inline-flex items-center gap-2 rounded-full border px-4 h-11 text-sm font-semibold transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                فلاتر
                @if ($advancedActive)<span class="w-2 h-2 rounded-full bg-accent-500"></span>@endif
            </button>
        @can('reports.view')
            <a href="{{ route('dashboard.reports.conversion') }}" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white hover:bg-gray-50 text-sm font-semibold text-gray-700 px-4 h-11 transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3v18h18"/><path d="m7 15 4-5 4 3 5-7"/></svg>
                تقرير تحول المعاينات
            </a>
            <a href="{{ route('dashboard.reports.viewings') }}" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white hover:bg-gray-50 text-sm font-semibold text-gray-700 px-4 h-11 transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                تقرير واتساب المعاينات
            </a>
        @endcan
        </div>
    </div>

    {{-- الفلاتر المتقدمة (خارج منطقة النتائج حتى لا تُستبدَل مع كل فلترة) — تُطبَّق فور الاختيار --}}
    <div x-show="filtersOpen" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 -translate-y-2">
    <x-filter-bar id="viewings-filters" cols="xl:grid-cols-5" :reset="array_filter($filters) ? route('dashboard.viewings.index') : null">
        <x-filter-input label="من تاريخ" name="from" :value="$filters['from'] ?? ''" datepicker placeholder="من" />
        <x-filter-input label="إلى تاريخ" name="to" :value="$filters['to'] ?? ''" datepicker placeholder="إلى" />
        <x-filter-select label="مندوب المبيعات" name="agent_id" placeholder="كل مندوبي المبيعات"
                         :options="$agents->pluck('name', 'id')" :selected="$filters['agent_id'] ?? null" />
        <x-filter-select label="النتيجة" name="outcome" placeholder="كل النتائج"
                         :options="$outcomes" :selected="$filters['outcome'] ?? null" />
        <x-filter-select label="حالة واتساب" name="wa_state" placeholder="الكل"
                         :options="$waStates" :selected="$filters['wa_state'] ?? null" />
        {{-- المنطقة تتبع المحافظة — نطاق Alpine واحد بلا كسر الشبكة (display:contents) --}}
        <div class="contents" x-data="{
                city: @js((string) ($filters['city_id'] ?? '')),
                area: @js((string) ($filters['area_id'] ?? '')),
                areas: @js($areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'city_id' => $a->city_id])->values()),
                areasFor() { return this.city ? this.areas.filter(a => String(a.city_id) === String(this.city)) : this.areas; }
             }">
            <x-filter-select label="محافظة العقار" name="city_id" placeholder="كل المحافظات"
                             :options="$cities->pluck('name', 'id')" x-model="city" @change="area = ''; $refs.areaSelect.value = ''" />
            <x-filter-select label="منطقة العقار" name="area_id" placeholder="كل المناطق" x-ref="areaSelect" x-model="area">
                <template x-for="a in areasFor()" :key="a.id">
                    <option :value="a.id" x-text="a.name" :selected="String(a.id) === String(area)"></option>
                </template>
            </x-filter-select>
        </div>
        <x-filter-select label="نوع الوحدة" name="unit_type_id" placeholder="كل الأنواع"
                         :options="$unitTypes->pluck('name', 'id')" :selected="$filters['unit_type_id'] ?? null" />
        <x-filter-select label="الغرض" name="purpose" placeholder="بيع وإيجار"
                         :options="['sale' => 'بيع', 'rent' => 'إيجار']" :selected="$filters['purpose'] ?? null" />
        <x-filter-select label="حضوري" name="in_person" placeholder="الكل"
                         :options="['1' => 'نعم', '0' => 'لا']" :selected="$filters['in_person'] ?? null" />
    </x-filter-bar>
    </div>

    <div data-results>
        {{-- صفّ النتائج (مثل مراحل العملاء) — يبدّل حقل outcome في نموذج الفلاتر --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="inline-flex items-center gap-1 rounded-full bg-gray-100/70 border border-gray-100 p-1 max-w-full overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <a href="{{ $outcomeUrl(null) }}" data-filter-set="outcome" data-filter-value="" class="{{ $activeOutcome === '' ? $pillOn : $pillOff }}">
                    كل المعاينات
                    <span class="{{ $activeOutcome === '' ? $badgeOn : $badgeOff }}">{{ $outcomeCounts['total'] }}</span>
                </a>
                @foreach ($outcomes as $key => $label)
                    @php $on = $activeOutcome === $key; @endphp
                    <a href="{{ $outcomeUrl($key) }}" data-filter-set="outcome" data-filter-value="{{ $key }}" class="{{ $on ? $pillOn : $pillOff }}">
                        {{ $label }}
                        <span class="{{ $on ? $badgeOn : $badgeOff }}">{{ $outcomeCounts['outcomes'][$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            @if (array_filter($filters, fn ($v) => $v !== null && $v !== ''))
                <a href="{{ route('dashboard.viewings.index') }}" class="text-sm text-gray-500 hover:text-danger whitespace-nowrap">مسح الفلاتر</a>
            @endif
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
                            <th class="text-start font-medium px-4 py-3">حضوري</th>
                            <th class="text-start font-medium px-4 py-3">مندوب المبيعات</th>
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
                                            <a href="{{ route('dashboard.properties.show', $viewing->property) }}" class="font-semibold text-ink hover:text-primary-700"><bdi dir="ltr">{{ $viewing->property->reference_code }}</bdi>@if ($viewing->property->building_name)<span class="text-xs font-normal text-gray-500"> — مبنى: {{ $viewing->property->building_name }}</span>@endif</a>
                                        @else
                                            <span class="font-semibold text-ink"><bdi dir="ltr">{{ $viewing->property->reference_code }}</bdi>@if ($viewing->property->building_name)<span class="text-xs font-normal text-gray-500"> — مبنى: {{ $viewing->property->building_name }}</span>@endif</span>
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
                                <td class="px-4 py-3 max-w-[220px]">@include('dashboard.viewings._notes', ['viewing' => $viewing])</td>
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
    @include('dashboard.viewings._notes-modal')
</div>
@endsection
