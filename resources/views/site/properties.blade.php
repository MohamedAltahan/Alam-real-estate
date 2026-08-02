@extends('site.layouts.app')

@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $hTitle = data_get($header, 'title') ?: $t('اكتشف العقارات المتاحة', 'Discover Available Properties');
    $hDesc = data_get($header, 'description') ?: $t('اكتشف المزيد من العقارات السكنية والتجارية والمفروشة، واختر العقار الذي يجمع بين الموقع المثالي والقيمة المناسبة.', 'Explore residential, commercial and furnished properties and pick the one that fits.');
    // صف خيار داخل الفلتر: النص يمين + زر الراديو شمال
    $optRow = 'flex items-center justify-between gap-3 py-2 cursor-pointer text-sm text-gray-600 hover:text-primary-800 transition';
    $radio = 'appearance-none w-[18px] h-[18px] rounded-full border border-gray-300 bg-white checked:border-[5px] checked:border-primary-800 cursor-pointer shrink-0 transition';
    $groupTitle = 'text-sm font-bold text-ink mb-1';
    $prices = [
        ['0-100000', $t('أقل من 100,000 د.ك', 'Under KD 100,000')],
        ['100000-500000', $t('100,000 - 500,000 د.ك', 'KD 100,000 - 500,000')],
        ['500000+', $t('أكثر من 500,000 د.ك', 'Over KD 500,000')],
        ['0-1000', $t('أقل من 1,000 د.ك / شهر', 'Under KD 1,000 /mo')],
        ['1000+', $t('أكثر من 1,000 د.ك / شهر', 'Over KD 1,000 /mo')],
    ];
    $viewBtn = 'grid place-items-center w-10 h-10 rounded-full border transition';
@endphp

@section('title', $hTitle)

@section('content')
{{-- ===== هيدر ===== --}}
<section class="relative isolate text-white bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950 overflow-hidden">
    <x-site.page-hero-bg />
    <div class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-white/70 to-transparent"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 pt-28 pb-14 text-center">
        <span class="inline-block rounded-full bg-accent-500/20 border border-accent-500/40 text-accent-300 px-4 py-1 text-xs font-bold mb-4">{{ $t('العقارات المتاحة', 'Available Properties') }}</span>
        <h1 class="font-display text-2xl sm:text-3xl font-bold mb-3">{{ $hTitle }}</h1>
        <p class="text-white/70 max-w-2xl mx-auto text-sm">{{ $hDesc }}</p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10" x-data="{ filters: true, view: 'grid' }">
    {{-- شريط الأدوات: العنوان + الترتيب --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h2 class="text-xl sm:text-2xl font-bold text-ink">{{ $t('أكثر من', 'More than') }} {{ number_format($properties->total()) }} {{ $t('بيت في عروض العقارات', 'properties available') }}</h2>
        <form method="GET" class="relative">
            @foreach (['category', 'unit_type', 'area', 'bedrooms', 'purpose', 'reference', 'price'] as $f)
                @if (data_get($filters, $f))<input type="hidden" name="{{ $f }}" value="{{ data_get($filters, $f) }}">@endif
            @endforeach
            <select name="sort" onchange="this.form.submit()"
                    class="appearance-none rounded-full bg-white border-2 border-primary-900 ps-5 pe-10 py-2.5 text-sm text-primary-900 hover:bg-primary-50 focus:outline-none cursor-pointer transition">
                <option value="">{{ $t('العروض العقارية', 'Sort by') }}</option>
                <option value="newest" @selected(request('sort') === 'newest')>{{ $t('الأحدث', 'Newest') }}</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>{{ $t('الأقل سعراً', 'Price: low') }}</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>{{ $t('الأعلى سعراً', 'Price: high') }}</option>
            </select>
            <svg class="absolute end-4 top-1/2 -translate-y-1/2 text-primary-900 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m6 9 6 6 6-6"/></svg>
        </form>
    </div>

    {{-- الصف الثاني: زر الفلتر + العدد | أزرار طريقة العرض --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex items-center gap-3">
            <button type="button" @click="filters = ! filters"
                    class="inline-flex items-center gap-2 rounded-full border-2 border-primary-900 bg-white px-4 py-2 text-sm text-primary-900 hover:navy-gradient hover:text-white transition">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/></svg>
                <span x-text="filters ? '{{ $t('إخفاء الفلتر', 'Hide filters') }}' : '{{ $t('إظهار الفلتر', 'Show filters') }}'"></span>
            </button>
            <span class="text-sm text-gray-400">{{ $properties->total() }} {{ $t('نتيجة', 'results') }}</span>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" @click="view = 'grid'" aria-label="grid"
                    :class="view === 'grid' ? 'navy-gradient border-primary-800 text-white' : 'bg-white border-gray-200 text-gray-400 hover:text-primary-800'"
                    class="{{ $viewBtn }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            </button>
            <button type="button" @click="view = 'list'" aria-label="list"
                    :class="view === 'list' ? 'navy-gradient border-primary-800 text-white' : 'bg-white border-gray-200 text-gray-400 hover:text-primary-800'"
                    class="{{ $viewBtn }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
            </button>
        </div>
    </div>

    {{-- الفلتر (يمين) + الشبكة (يسار) --}}
    <div class="flex flex-col lg:flex-row gap-6">
        <aside x-show="filters" x-cloak class="w-full lg:w-72 shrink-0 order-first">
            <form method="GET" class="rounded-2xl bg-white border border-gray-100 p-5 sticky top-24">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-ink">{{ $t('تصفية النتائج', 'Filters') }}</h3>
                    <a href="{{ route('site.properties') }}" class="text-xs font-bold text-accent-600 hover:text-accent-700">{{ $t('مسح الكل', 'Clear all') }}</a>
                </div>

                {{-- نوع العقار --}}
                <div class="py-3 border-t border-gray-100">
                    <p class="{{ $groupTitle }}">{{ $t('نوع العقار', 'Category') }}</p>
                    @foreach ($categories as $cat)
                        <label class="{{ $optRow }}"><span>{{ $cat->name }}</span>
                            <input type="radio" name="category" value="{{ $cat->id }}" @checked(($filters['category'] ?? '') == $cat->id) onchange="this.form.submit()" class="{{ $radio }}"></label>
                    @endforeach
                </div>

                {{-- نوع الوحدة --}}
                <div class="py-3 border-t border-gray-100">
                    <p class="{{ $groupTitle }}">{{ $t('نوع الوحدة', 'Unit type') }}</p>
                    @foreach ($unitTypes as $u)
                        <label class="{{ $optRow }}"><span>{{ $u->name }}</span>
                            <input type="radio" name="unit_type" value="{{ $u->id }}" @checked(($filters['unit_type'] ?? '') == $u->id) onchange="this.form.submit()" class="{{ $radio }}"></label>
                    @endforeach
                </div>

                {{-- المنطقة --}}
                <div class="py-3 border-t border-gray-100">
                    <p class="{{ $groupTitle }}">{{ $t('المنطقة', 'Area') }}</p>
                    @foreach ($areas as $a)
                        <label class="{{ $optRow }}"><span>{{ $a->name }}</span>
                            <input type="radio" name="area" value="{{ $a->id }}" @checked(($filters['area'] ?? '') == $a->id) onchange="this.form.submit()" class="{{ $radio }}"></label>
                    @endforeach
                </div>

                {{-- نطاق السعر --}}
                <div class="py-3 border-t border-gray-100">
                    <p class="{{ $groupTitle }}">{{ $t('نطاق السعر', 'Price range') }}</p>
                    @foreach ($prices as [$val, $label])
                        <label class="{{ $optRow }}"><span>{{ $label }}</span>
                            <input type="radio" name="price" value="{{ $val }}" @checked(($filters['price'] ?? '') === $val) onchange="this.form.submit()" class="{{ $radio }}"></label>
                    @endforeach
                </div>

                {{-- غرف النوم --}}
                <div class="py-3 border-t border-gray-100">
                    <p class="{{ $groupTitle }}">{{ $t('غرف النوم', 'Bedrooms') }}</p>
                    @foreach ([1, 2, 3, 4, 5] as $n)
                        <label class="{{ $optRow }}"><span>{{ $n == 5 ? '+5' : $n }} {{ $t('غرف', 'rooms') }}</span>
                            <input type="radio" name="bedrooms" value="{{ $n }}" @checked(($filters['bedrooms'] ?? '') == $n) onchange="this.form.submit()" class="{{ $radio }}"></label>
                    @endforeach
                </div>
            </form>
        </aside>

        <div class="flex-1 min-w-0">
            @if ($properties->count())
                <div :class="view === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5' : 'list-view flex flex-col gap-4'">
                    @foreach ($properties as $property)
                        <x-site.property-card :property="$property" />
                    @endforeach
                </div>
                <div class="mt-8">{{ $properties->links() }}</div>
            @else
                <div class="rounded-2xl bg-white border border-gray-100 py-20 text-center text-gray-400">{{ $t('لا توجد عقارات مطابقة للفلتر.', 'No properties match the filters.') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
