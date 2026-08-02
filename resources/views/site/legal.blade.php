@extends('site.layouts.app')

@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $titles = [
        'terms' => $t('الشروط والأحكام', 'Terms & Conditions'),
        'privacy' => $t('سياسة الخصوصية', 'Privacy Policy'),
    ];
    $title = $titles[$slug] ?? $slug;
    $other = $slug === 'terms' ? 'privacy' : 'terms';
@endphp

@section('title', $title)

@section('content')
{{-- ===================== هيرو ===================== --}}
<section class="relative isolate text-white bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950 overflow-hidden">
    <x-site.page-hero-bg />
    <div class="absolute -top-16 -start-16 w-72 h-72 rounded-full bg-accent-500/10"></div>
    <div class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-white/70 to-transparent"></div>
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 pt-28 sm:pt-32 pb-16 sm:pb-20 text-center">
        <span class="inline-grid place-items-center w-14 h-14 rounded-2xl bg-white/10 border border-white/15 text-accent-400 mb-5 mx-auto">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>
        </span>
        <h1 class="font-display text-3xl sm:text-4xl font-bold leading-tight mb-3">{{ $title }}</h1>
        @if ($updatedAt)
            <p class="text-white/60 text-sm">{{ $t('آخر تحديث:', 'Last updated:') }} {{ $updatedAt->translatedFormat('d F Y') }}</p>
        @endif
    </div>
</section>

<div class="max-w-3xl mx-auto px-4 sm:px-6 py-14">
    <article class="rounded-card bg-white border border-gray-100 shadow-sm p-6 sm:p-10">
        @if (trim($body) !== '')
            <div class="text-[15px] text-gray-600 leading-8 whitespace-pre-line">{!! nl2br(e($body)) !!}</div>
        @else
            <p class="text-center text-gray-400 py-10">{{ $t('لم تتم إضافة محتوى هذه الصفحة بعد.', 'This page has no content yet.') }}</p>
        @endif
    </article>

    {{-- تذييل الصفحة القانونية --}}
    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm">
        <a href="{{ route('site.' . $other) }}" class="inline-flex items-center gap-2 text-primary-700 hover:text-primary-900 font-medium">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
            {{ $slug === 'terms' ? $t('سياسة الخصوصية', 'Privacy Policy') : $t('الشروط والأحكام', 'Terms & Conditions') }}
        </a>
        <span class="text-gray-400">{{ $t('أسئلة حول هذه السياسة؟', 'Questions about this policy?') }} <a href="{{ route('site.contact') }}" class="text-accent-600 hover:text-accent-700 font-medium">{{ $t('تواصل معنا', 'Contact us') }}</a></span>
    </div>
</div>
@endsection
