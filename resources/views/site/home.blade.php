@extends('site.layouts.app')

@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $hero = $c['hero'] ?? [];
    $featured = $c['featured'] ?? [];
    $areasC = $c['areas'] ?? [];
    $videos = $c['videos'] ?? [];
    $why = $c['why_us'] ?? [];
    $tstH = $c['testimonials'] ?? [];
    $cta = $c['cta'] ?? [];
    // يتعامل مع حقول القسم (نص مُترجَم مباشر) وحقول العناصر ({ar,en}) معاً
    $it = function ($item, $field, $def = '') use ($loc) {
        $v = data_get($item, $field);

        return is_array($v) ? ($v[$loc] ?? $v['ar'] ?? $def) : ($v ?: $def);
    };
    // يقبل رابطاً جاهزاً (media library) أو مساراً قديماً على القرص
    $img = fn ($p) => ! $p ? null
        : (str_starts_with($p, 'http') || str_starts_with($p, '/') ? $p : \Illuminate\Support\Facades\Storage::url($p));
@endphp

@section('title', 'علم العقارية')

@section('content')
{{-- ===================== الهيرو ===================== --}}
<section class="relative isolate overflow-hidden text-white"
         x-data="{ i: 0, imgs: @js(array_values($hero['images'] ?? [])), ms: {{ (int) ($hero['rotate_seconds'] ?? 5) * 1000 }} }"
         x-init="if (imgs.length > 1) setInterval(() => i = (i + 1) % imgs.length, ms)">
    {{-- خلفية متحركة --}}
    <div class="absolute inset-0 bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950"></div>
    <template x-for="(im, idx) in imgs" :key="idx">
        <div class="absolute inset-0 bg-cover bg-center transition-opacity duration-1000"
             :style="`background-image:url('${im}')`" x-show="i === idx" x-transition.opacity.duration.1000ms></div>
    </template>
    {{-- طبقة تلوين بأزرق الهوية: تخلّي أي صورة تُرفع تأخذ الطابع الأزرق --}}
    <div class="absolute inset-0 bg-primary-900/60 mix-blend-multiply"></div>
    <div class="absolute inset-0 bg-primary-900/15"></div>
    {{-- تدرّج اتجاهي: غامق أعلى (للنافبار) وأسفل (لصندوق البحث) وأفتح بالوسط لإظهار الصورة --}}
    <div class="absolute inset-0 bg-gradient-to-b from-primary-950/70 via-transparent to-primary-950/80"></div>
    <div class="absolute inset-0 bg-gradient-to-r from-primary-950/40 via-transparent to-primary-950/40"></div>
    {{-- التلاشي السفلي من Figma: #F7F8FC 100% ← #000000 0% (نقطتان فقط، بلا درجة وسطى) --}}
    <div class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-[#f7f8fc] to-transparent"></div>

    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 pt-40 pb-16 text-center">
        @if ($it($hero, 'badge'))
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 border border-white/15 backdrop-blur-sm px-4 py-1.5 text-sm text-white/90 mb-5">
                <span class="w-1.5 h-1.5 rounded-full bg-accent-500 animate-pulse"></span>{{ $it($hero, 'badge') }}
            </span>
        @endif
        <h1 class="font-display text-[1.75rem] sm:text-[2rem] lg:text-[2.65rem] font-bold leading-[1.15] mb-3 text-balance drop-shadow-[0_2px_20px_rgba(0,0,0,0.35)]">{{ $it($hero, 'title') ?: $t('اعثر على العقار المثالي لك', 'Find your ideal property') }}</h1>
        @if ($it($hero, 'subtitle'))<p class="text-lg sm:text-xl lg:text-[1.6rem] font-semibold text-accent-400 mb-4">{{ $it($hero, 'subtitle') }}</p>@endif
        {{-- الوصف يتمدّد لعرض الهيرو كاملاً على الشاشات الكبيرة حتى لا ينكسر في مساحة ضيّقة --}}
        @if ($it($hero, 'description'))<p class="text-base sm:text-lg text-white/75 max-w-2xl lg:max-w-none mx-auto mb-8 leading-relaxed">{{ $it($hero, 'description') }}</p>@endif

        {{-- صندوق بحث زجاجي (مربوط بفلاتر صفحة العقارات) --}}
        @php
            $mk = fn ($col) => $col->map(fn ($x) => ['v' => (string) $x->id, 'l' => (string) $x->name])->values()->all();
            $refItems = $searchReferences->map(fn ($r) => ['v' => $r, 'l' => $r])->all();
            $bedItems = collect([1, 2, 3, 4, 5])->map(fn ($n) => ['v' => (string) $n, 'l' => ($n === 5 ? '+5' : $n).' '.$t('غرف', 'rooms')])->all();
            $priceItems = [
                ['v' => '0-500', 'l' => $t('أقل من 500 د.ك', 'Under 500 KD')],
                ['v' => '500-1000', 'l' => '500 - 1,000 '.$t('د.ك', 'KD')],
                ['v' => '1000-5000', 'l' => '1,000 - 5,000 '.$t('د.ك', 'KD')],
                ['v' => '5000-50000', 'l' => '5,000 - 50,000 '.$t('د.ك', 'KD')],
                ['v' => '50000+', 'l' => $t('أكثر من 50,000 د.ك', 'Over 50,000 KD')],
            ];
        @endphp
        @php $defaultCat = (string) (request('category') ?: optional($searchCategories->first())->id); @endphp
        <form action="{{ route('site.properties') }}" method="GET" x-data="{ cat: '{{ $defaultCat }}' }" class="glass rounded-[2rem] p-4 sm:p-5 max-w-5xl mx-auto shadow-2xl text-start">
            <input type="hidden" name="category" :value="cat">

            {{-- تبويب سكني/تجاري (الافتراضي: سكني) --}}
            @if ($searchCategories->count())
                <div class="flex justify-start mb-3">
                    <div class="inline-flex glass rounded-full p-1 text-sm">
                        @foreach ($searchCategories as $sc)
                            <button type="button" @click="cat = '{{ $sc->id }}'"
                                    class="px-5 py-1.5 rounded-full transition"
                                    :class="cat === '{{ $sc->id }}' ? 'bg-white text-primary-900 font-semibold shadow-sm' : 'text-white/80 hover:text-white'">{{ $sc->name }}</button>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- الحقول (قوائم منسدلة قابلة للبحث) --}}
            <div class="flex flex-wrap items-center gap-2">
                <x-site.combobox name="reference" :items="$refItems" :placeholder="$t('الرقم المرجعي', 'Reference no.')" />
                <x-site.combobox name="unit_type" :items="$mk($searchUnitTypes)" :placeholder="$t('اختر نوع الوحدة', 'Unit type')" />
                <x-site.combobox name="area" :items="$mk($searchAreas)" :placeholder="$t('اختر المناطق', 'Areas')" />
                <x-site.combobox name="bedrooms" :items="$bedItems" :placeholder="$t('غرف النوم', 'Bedrooms')" />
                <x-site.combobox name="price" :items="$priceItems" :placeholder="$t('الأسعار', 'Price')" />
                <button type="submit" class="inline-flex items-center gap-2 rounded-full gold-gradient hover:brightness-110 text-primary-900 font-semibold px-6 py-3 text-sm shrink-0 shadow-lg shadow-accent-500/25 transition">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    {{ $t('بحث عن عقار', 'Search') }}
                </button>
            </div>
        </form>

        {{-- إحصائيات --}}
        @if (! empty($hero['stats']))
            <div class="flex items-center justify-center gap-10 sm:gap-16 mt-6">
                @foreach ([['properties', $t('عقار', 'Properties')], ['clients', $t('عميل', 'Clients')], ['areas', $t('منطقة', 'Areas')]] as [$k, $lbl])
                    @if (data_get($hero, "stats.$k"))
                        <div class="text-center"><p class="text-xl sm:text-2xl font-bold text-white tabular-nums" dir="ltr">{{ data_get($hero, "stats.$k") }}</p><p class="text-[11px] text-white/60 mt-1">{{ $lbl }}</p></div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- نقاط التنقل بين صور الهيرو --}}
        <div x-show="imgs.length > 1" x-cloak class="flex justify-center mt-8">
            <div class="inline-flex items-center gap-2 rounded-full bg-white/10 backdrop-blur-md border border-white/15 px-3 py-2">
                <template x-for="(im, idx) in imgs" :key="idx">
                    <button type="button" @click="i = idx" class="h-2 rounded-full transition-all duration-300"
                            :class="i === idx ? 'w-6 bg-accent-500' : 'w-2 bg-white/40 hover:bg-white/70'"
                            :aria-label="`${idx + 1}`"></button>
                </template>
            </div>
        </div>
    </div>
</section>

{{-- ===================== عروض مميزة ===================== --}}
@if ($it($featured, 'title'))
    <section class="max-w-7xl mx-auto px-4 sm:px-6 mt-6 relative z-10">
        <div class="rounded-3xl navy-gradient text-white p-6 sm:p-8 flex flex-wrap items-center gap-5 justify-between overflow-hidden relative isolate">
            <div class="absolute -top-16 -end-10 w-56 h-56 rounded-full bg-white/[0.07] blur-2xl"></div>
            <div class="absolute -bottom-20 -start-16 w-56 h-56 rounded-full bg-white/[0.05] blur-2xl"></div>

            {{-- الأيقونة (تُرفع من الداشبورد؛ وإلا أيقونة افتراضية) --}}
            <div class="relative flex items-center gap-4 max-w-2xl">
                <span class="hidden sm:grid place-items-center w-16 h-16 shrink-0">
                    @if ($img($featured['image'] ?? null))
                        <img src="{{ $img($featured['image']) }}" class="w-16 h-16 object-contain" alt="">
                    @else
                        <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" class="text-accent-500">
                            <path d="M3 21h18M6 21V8l7-4v17M18 21V12l-5-2.5" stroke-linejoin="round"/>
                            <path d="M9 9v.01M9 12v.01M9 15v.01M15.5 14v.01M15.5 17v.01" stroke-linecap="round" stroke-width="2"/>
                        </svg>
                    @endif
                </span>
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold mb-2">{{ $it($featured, 'title') }}</h2>
                    <p class="text-white/70 text-sm font-bold leading-relaxed">{{ $it($featured, 'description') }}</p>
                </div>
            </div>

            <a href="{{ route('site.properties') }}" class="relative rounded-full gold-gradient hover:brightness-110 text-primary-900 font-semibold px-7 py-3 text-sm shadow-lg shadow-accent-500/20 transition">{{ $t('اكتشف العروض', 'Explore offers') }}</a>
        </div>
    </section>
@endif

{{-- ===================== أفضل المناطق ===================== --}}
@if (! empty($areasC['items']))
    <section id="areas" class="max-w-7xl mx-auto px-4 sm:px-6 pt-20 pb-10 scroll-mt-24" x-data="carousel">
        {{-- الترويسة + أسهم التنقل --}}
        <div class="flex items-end justify-between gap-4 mb-7">
            <div>
                <span class="inline-block rounded-full bg-accent-100 border border-accent-700 text-accent-700 px-3.5 py-1 text-xs font-bold mb-3">{{ $t('التغطية الجغرافية', 'Coverage') }}</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-ink mb-1">{{ $it($areasC, 'title') ?: $t('أفضل المناطق', 'Best Areas') }}</h2>
                <p class="text-gray-500 text-sm">{{ $it($areasC, 'description') }}</p>
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

        {{-- شريط المناطق --}}
        <div x-ref="track" class="flex gap-4 overflow-x-auto snap-x scroll-smooth pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($areasC['items'] as $item)
                @php $area = $areas[$item['area_id']] ?? null; @endphp
                @if ($area)
                    <a href="{{ route('site.properties', ['area' => $area->id]) }}"
                       {{-- على lg: خمس بطاقات في الصف بالضبط — العرض = (100% ناقص 4 فواصل gap-4) ÷ 5 --}}
                       class="relative snap-start shrink-0 w-[46%] sm:w-[31%] lg:w-[calc((100%-4rem)/5)] rounded-3xl overflow-hidden aspect-[3/2] group block bg-primary-900">
                        @if ($img($item['image'] ?? null))
                            <img src="{{ $img($item['image']) }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500" alt="{{ $area->name }}">
                        @endif
                        <div class="absolute inset-0 bg-gradient-to-t from-primary-950/90 via-primary-950/25 to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-4 flex items-end justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-bold text-white truncate">{{ $area->name }}</p>
                                <p class="text-xs text-accent-400">{{ $item['count'] ?? '' }} {{ $t('عقار', 'properties') }}</p>
                            </div>
                            <span class="grid place-items-center w-9 h-9 rounded-full bg-white/15 backdrop-blur-sm border border-white/25 text-white shrink-0 group-hover:bg-accent-500 group-hover:text-primary-900 group-hover:border-accent-500 transition">
                                <svg class="{{ $loc === 'ar' ? '' : 'rotate-180' }}" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
                            </span>
                        </div>
                    </a>
                @endif
            @endforeach
        </div>
    </section>
@endif

{{-- ===================== فيديوهات تعريفية ===================== --}}
@if ($videoProperties->count())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-20" x-data="carousel">
        {{-- الترويسة + أسهم التنقل --}}
        <div class="flex items-end justify-between gap-4 mb-7">
            <div>
                <span class="inline-block rounded-full bg-accent-100 border border-accent-700 text-accent-700 px-3.5 py-1 text-xs font-bold mb-3">{{ $t('فيديوهات تعريفية', 'Videos') }}</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-ink mb-1">{{ $it($videos, 'title') ?: $t('تعريف الخدمات المقدمة', 'Our Services') }}</h2>
                <p class="text-gray-500 text-sm">{{ $it($videos, 'description') ?: $t('تعرّف على خدماتنا عبر مقاطع فيديو تعريفية مُصمّمة لتُوصّل كل المعلومات بسهولة ووضوح.', 'Get to know our services through short, clear intro videos.') }}</p>
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

        {{-- شريط الفيديوهات --}}
        <div x-ref="track" class="flex gap-5 overflow-x-auto snap-x scroll-smooth pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($videoProperties as $vp)
                <a href="{{ $vp->video_url }}" target="_blank" rel="noopener"
                   @if ($vp->video_id) @click.prevent="$store.video.show('{{ $vp->video_id }}', @js($vp->title))" @endif
                   {{-- على lg: أربع بطاقات كاملة في الصف — العرض = (100% ناقص 3 فواصل gap-5) ÷ 4 --}}
                   class="snap-start shrink-0 w-[85%] sm:w-[46%] lg:w-[calc((100%-3.75rem)/4)] rounded-3xl bg-white border border-gray-100 overflow-hidden group">
                    <div class="relative aspect-video bg-gray-100">
                        {{-- صورة الفيديو من يوتيوب أدقّ من غلاف العقار، ونرجع للغلاف إن تعذّرت --}}
                        @php $thumb = $vp->video_thumb ?: $vp->cover_url; @endphp
                        @if ($thumb)
                            <img src="{{ $thumb }}" class="w-full h-full object-cover" alt="{{ $vp->title }}"
                                 @if ($vp->cover_url) onerror="this.onerror=null;this.src='{{ $vp->cover_url }}'" @endif>
                        @endif
                        <span class="absolute inset-0 bg-primary-950/15 grid place-items-center">
                            <span class="grid place-items-center w-12 h-12 rounded-full bg-white/25 backdrop-blur-sm border border-white/40 text-white group-hover:bg-accent-500 group-hover:text-primary-900 group-hover:border-accent-500 group-hover:scale-110 transition">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 3 20 12 6 21 6 3"/></svg>
                            </span>
                        </span>
                    </div>
                    <div class="p-4">
                        <span class="text-xs font-bold text-accent-600" dir="ltr">{{ $vp->reference_code }}</span>
                        <div class="flex items-center justify-between gap-2 mt-1">
                            <h3 class="font-bold text-ink truncate">{{ $vp->title }}</h3>
                            <span class="flex items-center gap-1 text-xs text-gray-500 shrink-0">
                                <span class="font-semibold text-ink">{{ $vp->rating ? number_format($vp->rating, 1) : '—' }}</span>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="text-accent-500"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            </span>
                        </div>
                        @if ($vp->short_description)
                            <p class="text-xs text-gray-400 mt-1 line-clamp-2">{{ $vp->short_description }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        {{-- زر المزيد --}}
        <div class="text-center mt-9">
            <a href="{{ route('site.properties') }}" class="inline-flex items-center gap-2 rounded-full border-2 border-primary-900 hover:navy-gradient hover:text-white text-primary-900 font-semibold px-7 py-3 text-sm transition">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                {{ $t('المزيد من الفيديوهات', 'More videos') }}
            </a>
        </div>
    </section>
@endif

{{-- ===================== لماذا علم العقارية ===================== --}}
@if (! empty($why['items']))
    <section class="relative bg-gray-50 pt-10 pb-6 overflow-hidden" x-data="carousel">
        {{-- أيقونة عقار في الخلفية --}}
        <div class="pointer-events-none absolute bottom-4 end-2 xl:end-6 hidden lg:block" aria-hidden="true">
            <svg width="150" height="245" viewBox="0 0 180 290" fill="none" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="95" cy="278" rx="85" ry="12" fill="#1D2026" opacity="0.06"/>
                <rect x="14" y="96" width="34" height="182" rx="5" fill="#E9EAF0"/>
                <rect x="48" y="34" width="112" height="244" rx="6" fill="#DFE1E9"/>
                @for ($r = 0; $r < 6; $r++)
                    @for ($cIdx = 0; $cIdx < 3; $cIdx++)
                        <rect x="{{ 62 + $cIdx * 32 }}" y="{{ 56 + $r * 36 }}" width="18" height="23" rx="3" fill="#FBD300"/>
                    @endfor
                @endfor
                <rect x="22" y="118" width="18" height="23" rx="3" fill="#F0F1F5"/>
                <rect x="22" y="160" width="18" height="23" rx="3" fill="#F0F1F5"/>
                <rect x="22" y="202" width="18" height="23" rx="3" fill="#F0F1F5"/>
            </svg>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 text-center">
            {{-- الترويسة: الأسهم مطلقة بجانبها حتى لا تشغل صفّاً وتُبعد البطاقات عن العنوان --}}
            <div class="relative mb-8">
                <span class="inline-block rounded-full bg-accent-100 border border-accent-700 text-accent-700 px-3.5 py-1 text-xs font-bold mb-4">{{ $t('لماذا علم العقارية', 'Why us') }}</span>
                <h2 class="text-2xl sm:text-4xl font-bold text-ink mb-2">{{ $it($why, 'title') ?: $t('لماذا علم العقارية؟', 'Why Alam?') }}</h2>
                <p class="text-gray-500 text-sm max-w-2xl mx-auto">{{ $it($why, 'description') }}</p>

                {{-- من lg فقط: دون ذلك يضيق العرض فتتداخل الأسهم مع الوصف --}}
                <div class="hidden lg:flex items-center gap-2 absolute end-0 top-1/2 -translate-y-1/2">
                    <button type="button" @click="nav(-1)" :disabled="! canScroll" :class="canScroll ? 'hover:bg-gray-50 hover:text-primary-800' : 'opacity-40 cursor-not-allowed'" class="grid place-items-center w-9 h-9 rounded-full bg-white border border-gray-200 text-gray-500 transition" aria-label="prev">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                    <button type="button" @click="nav(1)" :disabled="! canScroll" :class="canScroll ? 'hover:bg-gray-50 hover:text-primary-800' : 'opacity-40 cursor-not-allowed'" class="grid place-items-center w-9 h-9 rounded-full bg-white border border-gray-200 text-gray-500 transition" aria-label="next">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                </div>
            </div>

            <div x-ref="track" class="flex gap-5 overflow-x-auto snap-x scroll-smooth pb-1 text-start [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($why['items'] as $w)
                    <div class="snap-start shrink-0 w-[78%] sm:w-[46%] lg:w-[23.5%] rounded-2xl bg-white/80 p-6">
                        <div class="flex items-center justify-between gap-2 mb-4">
                            <span class="grid place-items-center w-11 h-11 rounded-full gold-gradient text-white shadow-sm shrink-0"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></span>
                            <span class="text-3xl font-bold text-gray-300 tabular-nums" dir="ltr">{{ $w['number'] ?? '' }}</span>
                        </div>
                        <h3 class="font-bold text-ink mb-1.5">{{ $it($w, 'title') }}</h3>
                        <p class="text-sm text-gray-500 leading-relaxed">{{ $it($w, 'description') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- ===================== آراء العملاء ===================== --}}
@if ($testimonials->count())
    <section class="bg-white pt-16 pb-14" x-data="carousel">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            {{-- الترويسة + أسهم التنقّل --}}
            <div class="flex items-end justify-between gap-4 mb-8">
                <div>
                    <span class="inline-block rounded-full bg-accent-100 border border-accent-700 text-accent-700 px-3.5 py-1 text-xs font-bold mb-3">{{ $t('آراء عملائنا', 'Testimonials') }}</span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-ink">{{ $it($tstH, 'title') ?: $t('ماذا يقولون عنا', 'What they say') }}</h2>
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
                @foreach ($testimonials as $ts)
                    @php $stars = (int) ($ts->rating ?? 5); @endphp
                    <div class="snap-start shrink-0 w-[78%] sm:w-[46%] lg:w-[23.5%] rounded-2xl bg-gray-50 border border-gray-100 p-5 flex flex-col">
                        <div class="text-sm mb-3">
                            <span class="text-accent-500">{{ str_repeat('★', $stars) }}</span><span class="text-gray-200">{{ str_repeat('★', max(0, 5 - $stars)) }}</span>
                        </div>
                        <p class="text-sm text-gray-600 leading-relaxed mb-5 flex-1">"{{ $ts->content }}"</p>
                        <div class="flex items-center gap-3 border-t border-gray-200 pt-4">
                            @if ($ts->avatar_url)
                                <img src="{{ $ts->avatar_url }}" class="w-10 h-10 rounded-full object-cover shrink-0" alt="{{ $ts->name }}">
                            @else
                                <span class="grid place-items-center w-10 h-10 rounded-full bg-primary-100 text-primary-700 font-bold text-sm shrink-0">{{ mb_substr($ts->name, 0, 1) }}</span>
                            @endif
                            <div class="min-w-0"><p class="font-semibold text-sm text-ink truncate">{{ $ts->name }}</p><p class="text-xs text-gray-400 truncate">{{ $ts->title }}</p></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- ===================== ابدأ رحلتك ===================== --}}
@if ($it($cta, 'title'))
    {{-- بلا حشو رأسي: الفراغ يأتي من حشو قسم الآراء أعلاه (56px) وهامش الفوتر أدناه (40px) --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="relative isolate rounded-[2.5rem] overflow-hidden bg-primary-900 text-white text-center p-10 sm:p-14">
            @if ($img($cta['image'] ?? null))
                <img src="{{ $img($cta['image']) }}" class="absolute inset-0 w-full h-full object-cover" alt="">
                {{-- نفس طبقة التدرّج الرسمية المستخدمة في هيرو الصفحات الفرعية --}}
                <div class="absolute inset-0 navy-gradient"></div>
            @endif
            <div class="relative">
                @if ($it($cta, 'badge'))
                    <span class="inline-block rounded-full bg-white/10 border border-white/20 backdrop-blur-sm text-accent-400 px-4 py-1.5 text-xs font-semibold mb-4">{{ $it($cta, 'badge') }}</span>
                @endif
                <h2 class="text-2xl sm:text-3xl font-bold mb-3">{{ $it($cta, 'title') }}</h2>
                <p class="text-white/70 max-w-2xl mx-auto mb-7">{{ $it($cta, 'description') }}</p>
                <div class="flex items-center justify-center gap-3 flex-wrap">
                    <a href="{{ route('site.properties') }}" class="rounded-full gold-gradient hover:brightness-110 text-primary-900 font-semibold px-7 py-3 text-sm shadow-lg shadow-accent-500/20 transition">{{ $t('ابدأ البحث الآن', 'Start searching') }}</a>
                    <a href="{{ route('site.contact') }}" class="rounded-full bg-white/15 border border-white/25 backdrop-blur-sm hover:bg-white/25 text-white font-semibold px-7 py-3 text-sm transition">{{ $t('تواصل معنا', 'Contact us') }}</a>
                </div>
            </div>
        </div>
    </section>
@endif
@endsection
