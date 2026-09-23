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
    // المحتوى يُكتب من الداشبورد كنص عادي أو HTML بسيط: نسمح بوسوم التنسيق فقط ونشيل كل الخصائص (عدا href الآمن)
    $isHtml = $body !== strip_tags($body);
    if ($isHtml) {
        $html = strip_tags($body, '<h2><h3><h4><p><br><strong><b><em><i><u><ul><ol><li><a><hr>');
        $html = preg_replace_callback('/<(\w+)\b[^>]*>/', function ($m) {
            if (strtolower($m[1]) === 'a' && preg_match('/href\s*=\s*["\']((?:https?:|mailto:|tel:|\/)[^"\']*)["\']/i', $m[0], $h)) {
                return '<a href="'.e($h[1]).'">';
            }
            return '<'.$m[1].'>';
        }, $html);
    }
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
        @if (trim($body) !== '' && $isHtml)
            <div class="text-[15px] text-gray-600 leading-8
                        [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-ink [&_h2]:mt-8 [&_h2]:mb-3
                        [&_h3]:text-lg [&_h3]:font-bold [&_h3]:text-ink [&_h3]:mt-7 [&_h3]:mb-2
                        [&_h4]:font-bold [&_h4]:text-ink [&_h4]:mt-5 [&_h4]:mb-2
                        [&>:first-child]:mt-0 [&_p]:mb-4 [&_ul]:list-disc [&_ol]:list-decimal [&_ul]:ps-6 [&_ol]:ps-6 [&_ul]:mb-4 [&_ol]:mb-4
                        [&_a]:text-primary-700 [&_a]:underline [&_strong]:text-ink [&_hr]:my-6 [&_hr]:border-gray-100">{!! $html !!}</div>
        @elseif (trim($body) !== '')
            <div class="text-[15px] text-gray-600 leading-8 whitespace-pre-line">{!! nl2br(e($body)) !!}</div>
        @else
            <p class="text-center text-gray-400 py-10">{{ $t('لم تتم إضافة محتوى هذه الصفحة بعد.', 'This page has no content yet.') }}</p>
        @endif
    </article>

    {{-- تذييل الصفحة القانونية --}}
    <div class="mt-6 grid gap-3 sm:grid-cols-2 text-sm">
        <a href="{{ route('site.' . $other) }}" class="flex items-center gap-3 rounded-2xl bg-white border border-gray-100 shadow-sm p-4 hover:border-primary-200 transition">
            <span class="grid place-items-center w-10 h-10 shrink-0 rounded-xl bg-primary-50 text-primary-700">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
            </span>
            <span class="min-w-0">
                <span class="block text-xs text-gray-400">{{ $t('اقرأ أيضاً', 'Also read') }}</span>
                <span class="block font-semibold text-primary-900">{{ $slug === 'terms' ? $t('سياسة الخصوصية', 'Privacy Policy') : $t('الشروط والأحكام', 'Terms & Conditions') }}</span>
            </span>
        </a>
        <a href="{{ route('site.contact') }}" class="flex items-center gap-3 rounded-2xl bg-white border border-gray-100 shadow-sm p-4 hover:border-accent-300 transition">
            <span class="grid place-items-center w-10 h-10 shrink-0 rounded-xl bg-accent-50 text-accent-600">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </span>
            <span class="min-w-0">
                <span class="block text-xs text-gray-400">{{ $slug === 'terms' ? $t('أسئلة حول هذه الشروط؟', 'Questions about these terms?') : $t('أسئلة حول هذه السياسة؟', 'Questions about this policy?') }}</span>
                <span class="block font-semibold text-accent-600">{{ $t('تواصل معنا', 'Contact us') }}</span>
            </span>
        </a>
    </div>
</div>
@endsection
