@extends('site.layouts.app')

@php
    use Illuminate\Support\Facades\Storage;
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $p = $property;
    $agent = $p->agent;
    $gallery = $p->gallery_urls;
    $mainImg = $gallery[0] ?? null;
    $thumbs = array_slice($gallery, 1, 4);
    $location = collect([
        $p->area?->name,
        $p->block ? $t('قطعة', 'Block').' '.$p->block : null,
        $p->street ? $t('شارع', 'St').' '.$p->street : null,
        $p->building ? $t('منزل', 'Bldg').' '.$p->building : null,
    ])->filter()->implode('، ');
    $period = $p->purpose === 'rent' ? ($p->price_period === 'yearly' ? $t('سنوي', '/yr') : $t('شهري', '/mo')) : $t('للبيع', 'for sale');
    $spec = 'flex items-center gap-1.5 text-gray-600';
    // أيقونات المرافق (مطابقة لأسماء Phosphor المخزّنة في جدول amenities)
    $amenityIcons = [
        'WifiHigh' => '<path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0"/><circle cx="12" cy="20" r="1" fill="currentColor" stroke="none"/>',
        'Snowflake' => '<path d="M12 2v20M2.8 7l18.4 10M2.8 17 21.2 7M12 2 9.5 5M12 2l2.5 3M12 22l-2.5-3M12 22l2.5-3"/>',
        'Car' => '<path d="M5 17H3v-5l2-5h14l2 5v5h-2M5 12h14"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/>',
        'CookingPot' => '<path d="M4 10h16v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-6zM2 10h20M7.5 6 8 10M16.5 6 16 10"/>',
        'Television' => '<rect x="2" y="6" width="20" height="13" rx="2"/><path d="m8 3 4 3 4-3"/>',
        'Coffee' => '<path d="M17 9h1a3 3 0 1 1 0 6h-1M3 9h14v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4zM7 2v3M11 2v3"/>',
        'ShieldCheck' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
        'Barbell' => '<path d="M6.5 6.5v11M17.5 6.5v11M3 9.5v5M21 9.5v5M6.5 12h11"/>',
        'SwimmingPool' => '<path d="M2 16c1.6 0 2.4-1 4-1s2.4 1 4 1 2.4-1 4-1 2.4 1 4 1 2.4-1 4-1M2 20.5c1.6 0 2.4-1 4-1s2.4 1 4 1 2.4-1 4-1 2.4 1 4 1 2.4-1 4-1M7 15V5a2 2 0 1 1 4 0M15 15V5a2 2 0 1 1 4 0"/>',
        'Elevator' => '<rect x="3" y="2" width="18" height="20" rx="2"/><path d="M12 2v20M7 9.5 8.5 7 10 9.5M14 14.5 15.5 17 17 14.5"/>',
    ];
    $fallbackIcon = '<path d="M20 6 9 17l-5-5"/>';
@endphp

@section('title', $p->title)
@section('seo_title', $p->getTranslation('title', $loc, false) ?: $p->title)
{{-- الصفحة تبدأ بخلفية بيضاء ⇒ نافبار صلب من البداية --}}
@section('nav_solid', '1')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 pt-28 pb-8">
    {{-- العنوان + مشاركة --}}
    <div class="flex items-center justify-between gap-4 mb-5">
        <h1 class="text-2xl sm:text-3xl font-bold text-ink">{{ $p->title }}</h1>
        <button class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-800 shrink-0 transition">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="m16 6-4-4-4 4"/><path d="M12 2v13"/></svg>
            {{ $t('مشاركة', 'Share') }}
        </button>
    </div>

    {{-- المعرض: صورة كبيرة (يمين) + 4 مصغّرات 2×2 (شمال) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-9">
        <div class="relative rounded-2xl overflow-hidden bg-gray-100 aspect-[4/3] lg:aspect-auto lg:min-h-[420px]">
            @if ($mainImg)<img src="{{ $mainImg }}" class="absolute inset-0 w-full h-full object-cover" alt="{{ $p->title }}">
            @else<div class="absolute inset-0 grid place-items-center text-gray-300"><svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg></div>@endif
            <div class="absolute bottom-4 start-4 flex gap-2">
                <button class="rounded-full bg-white/85 backdrop-blur-md border border-white/60 text-primary-900 text-xs font-bold px-5 py-2.5 shadow-lg shadow-primary-950/10 hover:bg-white transition">{{ $t('عرض كل الصور', 'All photos') }}</button>
                @if ($p->video_url)
                    {{-- الفيديو يُفتح داخل الموقع؛ الرابط يبقى fallback لو الجافاسكربت متعطّل --}}
                    <a href="{{ $p->video_url }}" target="_blank" rel="noopener"
                       @if ($p->video_id) @click.prevent="$store.video.show('{{ $p->video_id }}', @js($p->title))" @endif
                       class="inline-flex items-center gap-2 rounded-full bg-white/85 backdrop-blur-md border border-white/60 text-primary-900 text-xs font-bold px-5 py-2.5 shadow-lg shadow-primary-950/10 hover:bg-white transition">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.5v13l11-6.5-11-6.5z"/></svg>
                        {{ $t('عرض فيديو الوحدة', 'Unit video') }}
                    </a>
                @endif
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            @for ($i = 0; $i < 4; $i++)
                <div class="rounded-2xl overflow-hidden bg-gray-100 aspect-[4/3] lg:aspect-auto lg:min-h-[204px]">
                    @if (isset($thumbs[$i]))<img src="{{ $thumbs[$i] }}" class="w-full h-full object-cover" alt="">
                    @else<div class="w-full h-full grid place-items-center text-gray-200"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/></svg></div>@endif
                </div>
            @endfor
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- المحتوى --}}
        <div class="lg:col-span-2 space-y-8">
            <div>
                <p class="text-sm font-bold text-accent-600 mb-2" dir="ltr">{{ $p->reference_code }}</p>
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <h2 class="text-xl font-bold text-ink">{{ $p->title }}</h2>
                            <span class="flex items-center gap-1 text-sm text-gray-500"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="text-accent-500"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><span class="font-semibold text-ink">{{ $p->rating ? number_format($p->rating, 1) : '—' }}</span><span class="text-gray-400">({{ $p->reviews_count }} {{ $t('تقييم', 'reviews') }})</span></span>
                        </div>
                        <p class="flex items-center gap-1.5 text-sm text-gray-500"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>{{ $location }}</p>
                    </div>
                    <p class="text-2xl font-bold text-primary-900">{{ number_format($p->price, 0) }} {{ $t('د.ك', 'KD') }} <span class="text-sm text-gray-400 font-normal">/ {{ $period }}</span></p>
                </div>
                <div class="flex items-center flex-wrap gap-x-8 gap-y-3 mt-5 pt-5 border-t border-gray-100 text-sm">
                    <span class="{{ $spec }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9V6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v3"/><path d="M2 11v5a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/><path d="M4 18v2M20 18v2"/></svg>{{ $p->bedrooms ?? 0 }} {{ $t('غرفة نوم', 'beds') }}</span>
                    <span class="{{ $spec }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h16a1 1 0 0 1 1 1v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-3a1 1 0 0 1 1-1z"/><path d="M6 12V5a2 2 0 0 1 2-2 2 2 0 0 1 2 2"/></svg>{{ $p->bathrooms ?? 0 }} {{ $t('حمام', 'baths') }}</span>
                    <span class="{{ $spec }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>{{ $p->area_size ? rtrim(rtrim(number_format($p->area_size, 0), '0'), '.') : 0 }} {{ $t('م²', 'm²') }}</span>
                </div>
            </div>

            @if ($p->getTranslation('description', $loc, false))
                <div><h3 class="text-lg font-bold text-ink mb-2">{{ $t('الوصف', 'Description') }}</h3><p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ $p->description }}</p></div>
            @endif
            @if ($p->getTranslation('specifications', $loc, false))
                <div><h3 class="text-lg font-bold text-ink mb-2">{{ $t('المواصفات', 'Specifications') }}</h3><p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ $p->specifications }}</p></div>
            @endif

            @if ($p->amenities->count())
                <div>
                    <h3 class="text-lg font-bold text-ink mb-3">{{ $t('المرافق والخدمات', 'Amenities') }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach ($p->amenities as $a)
                            <div class="flex items-center gap-2.5 rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600">
                                <svg class="text-accent-600 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $amenityIcons[$a->icon] ?? $fallbackIcon !!}</svg>
                                <span class="truncate">{{ $a->name }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- كارت الوكيل --}}
        @if ($agent)
            <aside>
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6 sticky top-24">
                    <h3 class="font-bold text-ink mb-5">{{ $t('الوكيل المسؤول', 'Listing Agent') }}</h3>

                    {{-- الصورة يمين + الاسم + التقييم شمال --}}
                    <div class="flex items-center justify-between gap-3 mb-5">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="relative shrink-0">
                                @if ($agent->avatar_url)
                                    <img src="{{ $agent->avatar_url }}" class="w-14 h-14 rounded-full object-cover" alt="{{ $agent->name }}">
                                @else
                                    <span class="grid place-items-center w-14 h-14 rounded-full bg-primary-100 text-primary-700 font-bold text-xl">{{ mb_substr($agent->name, 0, 1) }}</span>
                                @endif
                                <span class="absolute bottom-0 end-0 w-3.5 h-3.5 rounded-full bg-success ring-2 ring-white"></span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-ink truncate">{{ $agent->name }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ $agent->job_title ?: $t('مستشار عقاري', 'Real-estate advisor') }}</p>
                            </div>
                        </div>
                        <div class="text-end shrink-0">
                            <p class="text-accent-500 text-sm leading-none">{{ str_repeat('★', 4) }}</p>
                            <p class="text-[11px] text-gray-400 mt-1">({{ $agent->reviews()->count() }} {{ $t('تقييم', 'reviews') }})</p>
                        </div>
                    </div>

                    {{-- إحصائيات في صناديق --}}
                    <div class="grid grid-cols-3 gap-2 mb-5">
                        @foreach ([[$agent->clients()->count(), $t('عميل سعيد', 'clients')], [$agent->properties()->count(), $t('عقار', 'listings')], [max(1, (int) now()->diffInYears($agent->created_at)), $t('سنوات خبرة', 'years')]] as [$num, $lbl])
                            <div class="rounded-xl bg-gray-50 py-3 text-center">
                                <p class="font-bold text-ink tabular-nums">{{ $num }}</p>
                                <p class="text-[11px] text-gray-400 mt-0.5">{{ $lbl }}</p>
                            </div>
                        @endforeach
                    </div>

                    @if ($agent->getTranslation('bio', $loc, false))
                        <p class="text-xs text-gray-500 leading-relaxed mb-5">{{ $agent->bio }}</p>
                    @endif

                    @if ($agent->phone)
                        <div class="flex items-center gap-2 mb-2">
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $agent->phone) }}" target="_blank" rel="noopener"
                               class="flex-1 inline-flex items-center justify-center gap-2 rounded-full navy-gradient hover:brightness-125 text-white font-semibold py-3 text-sm transition">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg>
                                {{ $t('تواصل عبر واتساب', 'WhatsApp') }}
                            </a>
                            <a href="tel:{{ preg_replace('/\s/', '', $agent->phone) }}"
                               class="flex-1 inline-flex items-center justify-center gap-2 rounded-full bg-primary-50 hover:bg-primary-100 text-primary-800 font-semibold py-3 text-sm transition">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                {{ $t('اتصل رقم الجوال', 'Call') }}
                            </a>
                        </div>
                    @endif
                    <a href="{{ route('site.agent', $agent) }}" class="flex items-center justify-center w-full rounded-full border border-gray-200 hover:border-primary-300 hover:bg-gray-50 text-gray-700 font-semibold py-3 text-sm transition">{{ $t('عرض ملف الوكيل كاملاً', 'Full profile') }}</a>
                </div>
            </aside>
        @endif
    </div>

    {{-- عقارات مشابهة --}}
    @if ($similar->count())
        <section class="mt-14" x-data="carousel">
            <div class="flex items-end justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-ink mb-1">{{ $t('اكتشف العقارات المشابهة', 'Similar Properties') }}</h2>
                    <p class="text-gray-500 text-sm">{{ $t('استعرض مجموعة من العقارات التي تتشابه في الموقع، والسعر، والمواصفات، لتساعدك على مقارنة الخيارات والعثور على العقار الأنسب.', 'Browse properties similar in location, price and specs to help you compare and find the best fit.') }}</p>
                </div>
                <div class="hidden sm:flex items-center gap-2 shrink-0">
                    <button type="button" @click="nav(-1)" :disabled="! canScroll" :class="canScroll ? 'hover:bg-gray-50 hover:text-primary-800' : 'opacity-40 cursor-not-allowed'" class="grid place-items-center w-9 h-9 rounded-full border border-gray-200 text-gray-500 transition" aria-label="prev">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                    <button type="button" @click="nav(1)" :disabled="! canScroll" :class="canScroll ? 'hover:bg-gray-50 hover:text-primary-800' : 'opacity-40 cursor-not-allowed'" class="grid place-items-center w-9 h-9 rounded-full border border-gray-200 text-gray-500 transition" aria-label="next">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                </div>
            </div>
            <div x-ref="track" class="flex gap-5 overflow-x-auto snap-x scroll-smooth pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($similar as $property)
                    <div class="snap-start shrink-0 w-[85%] sm:w-[46%] lg:w-[calc((100%-3.75rem)/4)]">
                        <x-site.property-card :property="$property" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
