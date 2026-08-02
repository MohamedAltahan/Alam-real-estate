@extends('site.layouts.app')

@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $wa = $agent->phone ? preg_replace('/[^0-9]/', '', $agent->phone) : null;
    $years = max(1, (int) now()->diffInYears($agent->created_at));
    $langMap = ['ar' => $t('العربية', 'Arabic'), 'en' => $t('الإنجليزية', 'English')];
@endphp

@section('title', $agent->name)

@section('content')
{{-- ===================== هيرو الوكيل ===================== --}}
<section class="relative isolate text-white bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950 overflow-hidden">
    <x-site.page-hero-bg />
    <div class="absolute -top-16 -start-16 w-72 h-72 rounded-full bg-white/5"></div>
    <div class="absolute -bottom-24 -end-10 w-80 h-80 rounded-full bg-accent-500/10"></div>
    <div class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-white/70 to-transparent"></div>

    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-28 sm:pt-32 pb-12 sm:pb-16">
        <a href="{{ route('site.about') }}" class="inline-flex items-center gap-1.5 text-sm text-white/60 hover:text-white mb-8">
            <svg class="{{ $loc === 'ar' ? '' : 'rotate-180' }}" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 18l6-6-6-6"/></svg>
            {{ $t('عودة إلى الفريق', 'Back to team') }}
        </a>

        <div class="flex flex-col md:flex-row gap-8 md:items-center">
            {{-- الصورة --}}
            <div class="shrink-0 mx-auto md:mx-0">
                <div class="relative w-36 h-36 rounded-3xl overflow-hidden ring-4 ring-white/10 bg-primary-800">
                    @if ($agent->avatar_url)
                        <img src="{{ $agent->avatar_url }}" class="w-full h-full object-cover" alt="{{ $agent->name }}">
                    @else
                        <span class="absolute inset-0 grid place-items-center text-accent-500/90 font-bold text-5xl font-display">{{ mb_substr($agent->name, 0, 1) }}</span>
                    @endif
                </div>
            </div>

            {{-- المعلومات --}}
            <div class="flex-1 text-center md:text-start">
                <div class="flex items-center justify-center md:justify-start gap-2 mb-1">
                    <h1 class="font-display text-3xl sm:text-4xl font-bold">{{ $agent->name }}</h1>
                    <span class="inline-flex items-center gap-1 rounded-full bg-success/15 text-success text-xs font-semibold px-2.5 py-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-success"></span>{{ $t('متاح', 'Available') }}
                    </span>
                </div>
                <p class="text-white/70">{{ $agent->job_title ?: $t('مستشار عقاري', 'Real-estate advisor') }}</p>

                {{-- تقييم — من متوسط التقييمات المنشورة، لا قيمة ثابتة --}}
                @if ($agent->reviews_count)
                    @php $stars = (int) round($agent->rating); @endphp
                    <div class="flex items-center justify-center md:justify-start gap-2 mt-3">
                        <span class="text-accent-500">{{ str_repeat('★', $stars) }}<span class="text-white/25">{{ str_repeat('★', 5 - $stars) }}</span></span>
                        <span class="text-sm text-white/60 tabular-nums" dir="ltr">{{ number_format((float) $agent->rating, 1) }}</span>
                        <span class="text-sm text-white/60">({{ $agent->reviews_count }} {{ $t('تقييم', 'reviews') }})</span>
                    </div>
                @endif

                {{-- نبذة --}}
                @if ($agent->getTranslation('bio', $loc, false))
                    <p class="text-white/70 text-sm leading-relaxed mt-4 max-w-2xl">{{ $agent->bio }}</p>
                @endif

                {{-- لغات / سرعة الرد --}}
                <div class="flex items-center justify-center md:justify-start flex-wrap gap-2 mt-4">
                    @foreach ((array) ($agent->languages ?? []) as $lng)
                        <span class="rounded-full bg-white/10 border border-white/10 px-3 py-1 text-xs text-white/80">{{ $langMap[$lng] ?? $lng }}</span>
                    @endforeach
                    @if ($agent->response_time)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 border border-white/10 px-3 py-1 text-xs text-white/80">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            {{ $t('يرد خلال', 'Replies in') }} {{ $agent->response_time }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- إحصائيات + تواصل --}}
            <div class="md:w-64 shrink-0">
                <div class="rounded-card bg-white/5 border border-white/10 backdrop-blur p-5">
                    <div class="grid grid-cols-3 divide-x divide-x-reverse divide-white/10 text-center mb-4">
                        <div><p class="text-2xl font-bold text-accent-400 tabular-nums">{{ $listingsCount }}</p><p class="text-[11px] text-white/50">{{ $t('عقار', 'listings') }}</p></div>
                        <div><p class="text-2xl font-bold text-accent-400 tabular-nums">{{ $clientsCount }}</p><p class="text-[11px] text-white/50">{{ $t('عميل', 'clients') }}</p></div>
                        <div><p class="text-2xl font-bold text-accent-400 tabular-nums">{{ $years }}</p><p class="text-[11px] text-white/50">{{ $t('سنوات', 'years') }}</p></div>
                    </div>
                    @if ($wa)
                        <a href="https://wa.me/{{ $wa }}" target="_blank" class="flex items-center justify-center gap-2 w-full rounded-field bg-accent-500 hover:bg-accent-400 text-primary-900 font-semibold py-2.5 text-sm mb-2 transition">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.2-1.2l-.3-.2-2.9.9.9-2.8-.2-.3A8 8 0 1 1 12 20z"/></svg>
                            {{ $t('واتساب', 'WhatsApp') }}
                        </a>
                        <a href="tel:{{ preg_replace('/\s/', '', $agent->phone) }}" class="flex items-center justify-center gap-2 w-full rounded-field bg-white/10 hover:bg-white/15 text-white font-semibold py-2.5 text-sm transition">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            {{ $t('اتصال', 'Call') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===================== عقارات الوكيل ===================== --}}
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-xs font-bold tracking-wider text-accent-600 uppercase mb-1">{{ $t('المعروضات', 'Listings') }}</p>
            <h2 class="text-2xl font-bold text-ink">{{ $t('عقارات', 'Properties by') }} {{ $agent->name }}</h2>
        </div>
        <span class="text-sm text-gray-400">{{ $properties->total() }} {{ $t('عقار', 'properties') }}</span>
    </div>

    @if ($properties->count())
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($properties as $property)
                <x-site.property-card :property="$property" />
            @endforeach
        </div>
        <div class="mt-8">{{ $properties->links() }}</div>
    @else
        <div class="rounded-card bg-white border border-gray-100 py-16 text-center text-gray-400">{{ $t('لا توجد عقارات منشورة لهذا الوكيل حالياً.', 'This agent has no published listings yet.') }}</div>
    @endif
</section>

{{-- ===================== آراء العملاء ===================== --}}
@if ($reviews->count())
    <section class="bg-gray-50 py-14">
        <div class="max-w-5xl mx-auto px-4 sm:px-6">
            <p class="text-xs font-bold tracking-wider text-accent-600 uppercase mb-1">{{ $t('التقييمات', 'Reviews') }}</p>
            <h2 class="text-2xl font-bold text-ink mb-6">{{ $t('ماذا قال العملاء عن', 'What clients said about') }} {{ $agent->name }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                @foreach ($reviews as $review)
                    <div class="rounded-card bg-white border border-gray-100 shadow-sm p-5">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-3">
                                <span class="grid place-items-center w-10 h-10 rounded-full bg-primary-100 text-primary-700 font-bold">{{ mb_substr($review->reviewer_name ?: '؟', 0, 1) }}</span>
                                <p class="font-semibold text-sm text-ink">{{ $review->reviewer_name ?: $t('عميل', 'Client') }}</p>
                            </div>
                            <span class="text-accent-500 text-sm">{{ str_repeat('★', $review->rating ?? 5) }}<span class="text-gray-200">{{ str_repeat('★', 5 - ($review->rating ?? 5)) }}</span></span>
                        </div>
                        @if ($review->comment)<p class="text-sm text-gray-600 leading-relaxed">"{{ $review->comment }}"</p>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- ===================== CTA ===================== --}}
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
    <div class="rounded-card bg-primary-900 text-white text-center p-10">
        <h2 class="text-2xl font-bold mb-3">{{ $t('مهتم بالتعامل مع', 'Interested in working with') }} {{ $agent->name }}؟</h2>
        <p class="text-white/70 max-w-2xl mx-auto mb-6">{{ $t('تواصل مباشرةً أو أرسل استفسارك وسيرد عليك في أقرب وقت.', 'Reach out directly or send your inquiry and get a quick reply.') }}</p>
        <div class="flex items-center justify-center gap-3 flex-wrap">
            @if ($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" class="rounded-field bg-accent-500 hover:bg-accent-400 text-primary-900 font-semibold px-6 py-3 text-sm">{{ $t('تواصل عبر واتساب', 'WhatsApp') }}</a>@endif
            <a href="{{ route('site.contact') }}" class="rounded-field bg-white/10 border border-white/20 hover:bg-white/15 text-white font-semibold px-6 py-3 text-sm">{{ $t('نموذج التواصل', 'Contact form') }}</a>
        </div>
    </div>
</section>
@endsection
