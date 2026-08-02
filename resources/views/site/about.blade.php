@extends('site.layouts.app')

@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $hero = $c['hero'] ?? [];
    $story = $c['story'] ?? [];
    $values = $c['values'] ?? [];
    $team = $c['team'] ?? [];
    // يتعامل مع حقول القسم (نص مُترجَم مباشر) وحقول العناصر ({ar,en}) معاً
    $it = function ($item, $field, $def = '') use ($loc) {
        $v = data_get($item, $field);

        return is_array($v) ? ($v[$loc] ?? $v['ar'] ?? $def) : ($v ?: $def);
    };
    $img = fn ($p) => $p ? \Illuminate\Support\Facades\Storage::url($p) : null;
    // أيقونات + ألوان بطاقات القيم (بالترتيب: قيمنا · رسالتنا · رؤيتنا)
    $valueStyles = [
        ['bg' => 'bg-success-soft', 'text' => 'text-success', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
        ['bg' => 'bg-accent-100', 'text' => 'text-accent-700', 'icon' => '<circle cx="12" cy="9" r="6"/><path d="M8.2 14.3 7 22l5-3 5 3-1.2-7.7"/>'],
        ['bg' => 'bg-info-soft', 'text' => 'text-info', 'icon' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M17 7h4v4"/>'],
    ];
    $statIcons = [
        '<path d="M12 15a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M8.2 13.5 7 22l5-3 5 3-1.2-8.5"/>',
        '<path d="m12 3 2.09 4.26L19 8l-3.5 3.4.83 4.85L12 14l-4.33 2.25.83-4.85L5 8l4.91-.74z"/>',
    ];
@endphp

@section('title', $it($hero, 'title') ?: $t('من نحن', 'About Us'))

@section('content')
{{-- ===================== هيرو ===================== --}}
<section class="relative isolate text-white bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950 overflow-hidden">
    <x-site.page-hero-bg />
    <div class="absolute -top-16 -start-16 w-72 h-72 rounded-full bg-white/5"></div>
    <div class="absolute -bottom-24 -end-10 w-80 h-80 rounded-full bg-accent-500/10"></div>
    <div class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-white/70 to-transparent"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-28 sm:pt-32 pb-16 sm:pb-20 text-center">
        <span class="inline-block rounded-full bg-accent-500/20 border border-accent-500/40 text-accent-300 px-4 py-1 text-xs font-bold mb-5">{{ $it($hero, 'badge') ?: $t('من نحن', 'About Us') }}</span>
        <h1 class="font-display text-2xl sm:text-3xl font-bold leading-tight mb-4 text-balance">{{ $it($hero, 'title') ?: $t('شريكك الموثوق في عالم العقارات', 'Your trusted partner in real estate') }}</h1>
        <p class="text-white/70 text-sm leading-relaxed">{{ $it($hero, 'description') ?: $t('نُعد علم العقارية وجهتك الموثوقة لاستكشاف أفضل الفرص العقارية في الكويت.', 'Alam Realestate is your trusted destination for the best property opportunities in Kuwait.') }}</p>
    </div>
</section>

{{-- ===================== قصتنا ===================== --}}
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
    <div class="grid lg:grid-cols-2 gap-10 lg:gap-14 items-center">
        {{-- النص (شمال) --}}
        <div class="order-last">
            <h2 class="text-2xl sm:text-3xl font-bold text-ink mb-4">{{ $it($story, 'title') ?: $t('بدأت رحلتنا بشغف نحو عالم العقارات', 'Our journey began with a passion for real estate') }}</h2>
            <div class="text-sm text-gray-500 leading-8 whitespace-pre-line">{{ $it($story, 'description') }}</div>

            {{-- بطاقتا إحصاء --}}
            @if (! empty($story['stats']))
                <div class="grid sm:grid-cols-2 gap-4 mt-7">
                    @foreach (array_slice($story['stats'], 0, 2) as $i => $s)
                        <div class="rounded-2xl bg-gray-50 border border-gray-100 p-5">
                            <span class="grid place-items-center w-10 h-10 rounded-xl bg-white text-accent-600 mb-3">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $statIcons[$i % 2] !!}</svg>
                            </span>
                            <p class="font-bold text-ink mb-1"><span class="tabular-nums" dir="ltr">{{ $s['number'] ?? '' }}</span> {{ $it($s, 'title') }}</p>
                            @if ($it($s, 'description'))<p class="text-xs text-gray-500 leading-relaxed">{{ $it($s, 'description') }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- الصورة (يمين) --}}
        <div class="relative order-first rounded-3xl overflow-hidden aspect-[4/3] bg-gradient-to-br from-primary-50 to-gray-100 shadow-sm">
            @if ($img($story['image'] ?? null))
                <img src="{{ $img($story['image']) }}" class="w-full h-full object-cover" alt="{{ $it($story, 'title') }}">
                {{-- طبقة زرقاء خفيفة بلون الهوية --}}
                <div class="absolute inset-0 bg-primary-700/20 mix-blend-multiply"></div>
                <div class="absolute inset-0 bg-primary-700/10"></div>
            @else
                <div class="w-full h-full grid place-items-center text-primary-200">
                    <svg width="90" height="90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-3"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/></svg>
                </div>
            @endif
        </div>
    </div>
</section>

{{-- ===================== قيمنا ===================== --}}
@if (! empty($values['items']) || $it($values, 'title'))
    <section class="relative bg-gray-50 py-14 overflow-hidden">
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

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6">
            <h2 class="text-2xl sm:text-3xl font-bold text-ink mb-2">{{ $it($values, 'title') ?: $t('قيمنا التي نعتز بها', 'Values we are proud of') }}</h2>
            <p class="text-gray-500 text-sm mb-8 leading-relaxed">{{ $it($values, 'subtitle') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                @foreach (($values['items'] ?? []) as $i => $v)
                    @php $st = $valueStyles[$i % 3]; @endphp
                    <div class="rounded-2xl bg-white/80 border border-gray-100 p-6">
                        <span class="grid place-items-center w-11 h-11 rounded-full {{ $st['bg'] }} {{ $st['text'] }} mb-4">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $st['icon'] !!}</svg>
                        </span>
                        <h3 class="font-bold text-ink mb-2">{{ $it($v, 'title') }}</h3>
                        <p class="text-sm text-gray-500 leading-relaxed">{{ $it($v, 'description') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- ===================== فريق العمل ===================== --}}
@if ($agents->count())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
        <h2 class="text-2xl sm:text-3xl font-bold text-ink mb-2">{{ $it($team, 'title') ?: $t('عائلة علم العقارية', 'The Alam family') }}</h2>
        <p class="text-gray-500 text-sm mb-8 leading-relaxed">{{ $it($team, 'subtitle') ?: $t('وراء كل نجاح فريق يعمل بشغف واحترافية. نفخر بامتلاك نخبة من المستشارين والمتخصصين الذين يكرسون خبراتهم لتقديم تجربة عقارية موثوقة.', 'Behind every success is a passionate team of expert advisors.') }}</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach ($agents as $agent)
                <div class="rounded-2xl bg-white border border-gray-100 overflow-hidden group">
                    {{-- الصورة + شريط سفلي --}}
                    <a href="{{ route('site.agent', $agent) }}" class="relative block aspect-square bg-primary-900">
                        @if ($agent->avatar_url)
                            <img src="{{ $agent->avatar_url }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500" alt="{{ $agent->name }}">
                        @else
                            <span class="absolute inset-0 grid place-items-center text-accent-500/90 font-bold text-5xl font-display">{{ mb_substr($agent->name, 0, 1) }}</span>
                        @endif
                        <div class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-primary-700/95 via-primary-700/55 to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-3 flex items-center justify-between text-white">
                            <span class="text-xs font-bold">{{ $agent->properties_count }} {{ $t('عقار', 'listings') }}</span>
                            {{-- التقييم الحقيقي من متوسط تقييمات الوكيل --}}
                            @if ($agent->reviews_count)
                                <span class="text-accent-500 text-xs flex items-center gap-1">
                                    ★<span class="tabular-nums" dir="ltr">{{ number_format((float) $agent->rating, 1) }}</span>
                                </span>
                            @endif
                        </div>
                    </a>

                    <div class="p-4">
                        <h3 class="font-bold text-ink truncate"><a href="{{ route('site.agent', $agent) }}" class="no-underline text-inherit">{{ $agent->name }}</a></h3>
                        <p class="text-xs text-gray-400 mb-4 truncate">{{ $agent->job_title ?: $t('مستشار عقاري', 'Real-estate advisor') }}</p>

                        @if ($agent->phone)
                            <div class="flex items-center gap-2">
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $agent->phone) }}" target="_blank" rel="noopener"
                                   class="flex-1 inline-flex items-center justify-center gap-2 rounded-full navy-gradient hover:brightness-125 text-white text-xs font-semibold py-3 transition">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/></svg>
                                    {{ $t('واتساب', 'WhatsApp') }}
                                </a>
                                <a href="tel:{{ preg_replace('/\s/', '', $agent->phone) }}"
                                   class="flex-1 inline-flex items-center justify-center gap-2 rounded-full bg-primary-50 hover:bg-primary-100 text-primary-800 text-xs font-semibold py-3 transition">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    {{ $t('اتصال', 'Call') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
@endsection
