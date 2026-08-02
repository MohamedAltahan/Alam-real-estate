@extends('site.layouts.app')

@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
@endphp

@section('title', $t('الأسئلة الشائعة', 'FAQ'))

@section('content')
{{-- ===================== هيرو ===================== --}}
<section class="relative isolate text-white bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950 overflow-hidden">
    <x-site.page-hero-bg />
    <div class="absolute -top-16 -end-16 w-72 h-72 rounded-full bg-accent-500/10"></div>
    <div class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-white/70 to-transparent"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 pt-28 sm:pt-32 pb-16 sm:pb-20 text-center">
        <span class="inline-block rounded-full bg-accent-500/90 text-primary-900 px-4 py-1 text-xs font-bold mb-5">{{ $t('مركز المساعدة', 'Help Center') }}</span>
        <h1 class="font-display text-3xl sm:text-5xl font-bold leading-tight mb-4">{{ $t('الأسئلة الشائعة', 'Frequently Asked Questions') }}</h1>
        <p class="text-white/70 max-w-2xl mx-auto">{{ $t('جمعنا لك أكثر الأسئلة تكراراً حول خدماتنا العقارية. لم تجد إجابتك؟ تواصل معنا.', 'The most common questions about our services. Didn’t find your answer? Contact us.') }}</p>
    </div>
</section>

<div class="max-w-3xl mx-auto px-4 sm:px-6 py-14">
    @if ($faqs->count())
        <div class="space-y-3" x-data="{ open: 0 }">
            @foreach ($faqs as $i => $faq)
                <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
                    <button type="button" @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                            class="w-full flex items-center justify-between gap-4 p-5 text-start hover:bg-gray-50/60 transition">
                        <span class="font-semibold text-ink">{{ $faq->question }}</span>
                        <span class="grid place-items-center w-7 h-7 rounded-full shrink-0 transition"
                              :class="open === {{ $i }} ? 'bg-accent-500 text-primary-900 rotate-180' : 'bg-gray-100 text-gray-500'">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
                        </span>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="px-5 pb-5 text-sm text-gray-500 leading-relaxed whitespace-pre-line border-t border-gray-50 pt-4">{{ $faq->answer }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-card bg-white border border-gray-100 py-16 text-center text-gray-400">{{ $t('لا توجد أسئلة منشورة حالياً.', 'No published questions yet.') }}</div>
    @endif

    {{-- CTA --}}
    <div class="mt-10 rounded-card bg-primary-900 text-white text-center p-8 relative overflow-hidden">
        <div class="absolute -bottom-10 -start-8 w-40 h-40 rounded-full bg-white/5"></div>
        <div class="relative">
            <h2 class="text-xl font-bold mb-2">{{ $t('لديك سؤال آخر؟', 'Still have a question?') }}</h2>
            <p class="text-white/60 text-sm mb-5 max-w-xl mx-auto">{{ $t('فريقنا جاهز للإجابة على كل استفساراتك العقارية.', 'Our team is ready to answer all your property questions.') }}</p>
            <a href="{{ route('site.contact') }}" class="inline-flex items-center gap-2 rounded-field bg-accent-500 hover:bg-accent-400 text-primary-900 font-semibold px-6 py-3 text-sm transition">{{ $t('تواصل معنا', 'Contact us') }}</a>
        </div>
    </div>
</div>
@endsection
