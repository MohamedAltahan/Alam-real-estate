@extends('site.layouts.app')

@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
@endphp

@section('title', $t('الصفحة غير موجودة', 'Page Not Found'))

@section('content')
<section class="relative overflow-hidden bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950 text-white">
    <div class="absolute -top-16 -start-16 w-72 h-72 rounded-full bg-white/5"></div>
    <div class="absolute -bottom-24 -end-10 w-80 h-80 rounded-full bg-accent-500/10"></div>

    <div class="relative max-w-2xl mx-auto px-4 sm:px-6 pt-32 sm:pt-40 pb-24 sm:pb-32 text-center">
        <p class="font-display font-bold leading-none text-[7rem] sm:text-[10rem] bg-gradient-to-b from-white to-white/30 bg-clip-text text-transparent">404</p>
        <h1 class="text-2xl sm:text-3xl font-bold -mt-2 mb-3">{{ $t('عذراً، الصفحة غير موجودة', 'Sorry, this page was not found') }}</h1>
        <p class="text-white/60 max-w-md mx-auto mb-8">{{ $t('ربما تم نقل الصفحة أو حذفها، أو أن الرابط غير صحيح. دعنا نعيدك إلى الطريق الصحيح.', 'The page may have been moved or removed, or the link is incorrect. Let us get you back on track.') }}</p>

        <div class="flex items-center justify-center gap-3 flex-wrap">
            <a href="{{ route('site.home') }}" class="inline-flex items-center gap-2 rounded-field bg-accent-500 hover:bg-accent-400 text-primary-900 font-semibold px-6 py-3 text-sm transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/></svg>
                {{ $t('العودة للرئيسية', 'Back home') }}
            </a>
            <a href="{{ route('site.properties') }}" class="inline-flex items-center gap-2 rounded-field bg-white/10 border border-white/20 hover:bg-white/15 text-white font-semibold px-6 py-3 text-sm transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                {{ $t('تصفح العقارات', 'Browse properties') }}
            </a>
        </div>

        <div class="mt-10 flex items-center justify-center gap-x-6 gap-y-2 flex-wrap text-sm text-white/50">
            <a href="{{ route('site.about') }}" class="hover:text-white">{{ $t('من نحن', 'About') }}</a>
            <a href="{{ route('site.contact') }}" class="hover:text-white">{{ $t('تواصل معنا', 'Contact') }}</a>
            <a href="{{ route('site.faq') }}" class="hover:text-white">{{ $t('الأسئلة الشائعة', 'FAQ') }}</a>
        </div>
    </div>
</section>
@endsection
