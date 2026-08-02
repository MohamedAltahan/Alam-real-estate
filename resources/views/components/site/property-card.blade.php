@props(['property'])

@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $p = $property;
    $rtl = $loc === 'ar';
    // الموقع: المنطقة + قطعة + شارع + مبنى
    $location = collect([
        $p->area?->name,
        $p->block ? $t('قطعة', 'Block').' '.$p->block : null,
        $p->street ? $t('شارع', 'St').' '.$p->street : null,
        $p->building ? $t('مبنى', 'Bldg').' '.$p->building : null,
    ])->filter()->implode('، ');
    $period = $p->purpose === 'rent'
        ? ' / '.($p->price_period === 'yearly' ? $t('سنوي', '/yr') : $t('شهري', '/mo'))
        : $t(' للبيع', ' for sale');
    // معرض صور الكارت: الغلاف + صور العقار (بدون تكرار)
    $gallery = collect($p->gallery_urls);
@endphp

<div class="rounded-2xl bg-white border border-gray-100 overflow-hidden group flex flex-col [.list-view_&]:sm:flex-row">
    {{-- الصورة --}}
    <div class="relative aspect-[4/3] bg-gray-100 shrink-0 overflow-hidden [.list-view_&]:sm:w-72 [.list-view_&]:sm:aspect-auto"
         @if ($gallery->count() > 1) x-data="{ i: 0 }" @endif>
        @if ($gallery->count())
            @foreach ($gallery as $idx => $imgPath)
                <img src="{{ $imgPath }}"
                     class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500"
                     alt="{{ $p->title }}"
                     @if ($gallery->count() > 1) x-show="i === {{ $idx }}" x-transition.opacity.duration.400ms @endif>
            @endforeach
        @else
            <div class="w-full h-full grid place-items-center text-gray-300"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg></div>
        @endif

        {{-- نقاط التنقل بين الصور --}}
        @if ($gallery->count() > 1)
            <div class="absolute bottom-3 inset-x-0 flex justify-center gap-1.5 z-10">
                @foreach ($gallery as $idx => $imgPath)
                    <button type="button" @click.stop="i = {{ $idx }}" aria-label="{{ $idx + 1 }}"
                            class="h-1.5 rounded-full transition-all duration-300"
                            :class="i === {{ $idx }} ? 'w-4 bg-white' : 'w-1.5 bg-white/55 hover:bg-white/80'"></button>
                @endforeach
            </div>
        @endif
        @if ($p->is_featured)
            <span class="absolute top-3 start-3 z-10 rounded-full bg-white/35 backdrop-blur-md border border-white/60 text-primary-900 text-xs font-extrabold px-4 py-1.5 shadow-lg shadow-primary-950/10">{{ $t('سعر مميز', 'Featured') }}</span>
        @endif
        <button type="button" class="absolute top-3 end-3 z-10 grid place-items-center w-9 h-9 rounded-full bg-white/35 backdrop-blur-md border border-white/60 text-primary-900 hover:bg-white/55 shadow-lg shadow-primary-950/10 transition" aria-label="{{ $t('مشاركة', 'Share') }}">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5 8.6 10.5"/></svg>
        </button>
    </div>

    {{-- الجسم --}}
    <div class="p-4 flex flex-col flex-1">
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-bold text-accent-600" dir="ltr">{{ $p->reference_code }}</span>
            <span class="flex items-center gap-1 text-xs text-gray-500">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" class="text-accent-500"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <span class="font-semibold text-ink">{{ $p->rating ? number_format($p->rating, 1) : '—' }}</span>
                <span class="text-gray-400">({{ $p->reviews_count }})</span>
            </span>
        </div>

        <h3 class="font-bold text-ink mb-1 truncate">
            <a href="{{ route('site.property', $p) }}" class="no-underline text-inherit">{{ $p->title }}</a>
        </h3>

        <p class="flex items-center gap-1 text-xs text-gray-400 mb-2 truncate">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span class="truncate">{{ $location ?: '—' }}</span>
        </p>

        <p class="font-bold text-primary-900 mb-3">{{ number_format($p->price, 0) }} {{ $t('د.ك', 'KD') }}<span class="text-xs text-gray-400 font-normal">{{ $period }}</span></p>

        {{-- المواصفات --}}
        <div class="flex items-center gap-3 text-xs text-gray-500 pb-3 mb-3 border-b border-gray-50">
            <span class="flex items-center gap-1"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3"/><path d="M2 11v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/><path d="M4 18v2M20 18v2"/></svg>{{ $p->bedrooms ?? 0 }} {{ $t('غرف', 'bd') }}</span>
            <span class="flex items-center gap-1"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h16a1 1 0 0 1 1 1v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-3a1 1 0 0 1 1-1z"/><path d="M6 12V5a2 2 0 0 1 2-2 2 2 0 0 1 2 2"/><path d="M8 20l-1 2M17 20l1 2"/></svg>{{ $p->bathrooms ?? 0 }} {{ $t('حمام', 'ba') }}</span>
            <span class="flex items-center gap-1"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v18H3zM9 3v18M3 9h18"/></svg>{{ $p->area_size ? rtrim(rtrim(number_format($p->area_size, 0), '0'), '.') : 0 }} {{ $t('م²', 'm²') }}</span>
        </div>

        {{-- الفوتر: الوكيل يمين + زر التفاصيل شمال --}}
        <div class="flex items-center justify-between gap-2 mt-auto">
            @if ($p->agent)
                <div class="flex items-center gap-2 min-w-0">
                    @if ($p->agent->avatar_url)
                        <img src="{{ $p->agent->avatar_url }}" class="w-7 h-7 rounded-full object-cover shrink-0" alt="{{ $p->agent->name }}">
                    @else
                        <span class="grid place-items-center w-7 h-7 rounded-full bg-primary-100 text-primary-700 font-bold text-[11px] shrink-0">{{ mb_substr($p->agent->name, 0, 1) }}</span>
                    @endif
                    <span class="text-xs text-gray-600 truncate max-w-[90px]">{{ $p->agent->name }}</span>
                </div>
            @else
                <span></span>
            @endif
            <a href="{{ route('site.property', $p) }}" class="inline-flex items-center gap-1.5 rounded-full navy-gradient hover:brightness-125 text-white text-xs font-semibold px-4 py-2 shrink-0 transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                {{ $t('التفاصيل', 'Details') }}
            </a>
        </div>
    </div>
</div>
