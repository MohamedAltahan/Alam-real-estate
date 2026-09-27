{{--
    صفحة «نعود قريباً» — تُعرض للزوار بحالة 503 أثناء إيقاف الموقع من الإعدادات (SiteMaintenance).
    مستقلة عن قالب الموقع: روابط النافبار والفوتر كلها متوقفة، فيبقى هنا التواصل المباشر فقط.
--}}
@php
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $t = fn ($ar, $en) => $rtl ? $ar : $en;
    $contact = fn ($key) => \App\Models\Setting::get('contact', $key);
    $phone = $contact('phone');
    $email = $contact('email');
    $wa = preg_replace('/[^0-9]/', '', (string) ($contact('whatsapp') ?: $phone));
    $btn = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold px-5 py-3 text-sm transition active:scale-[0.98]';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1e2559">
    {{-- بلا noindex عمداً: حالة 503 وحدها تُفهم كتوقف مؤقت، أما noindex فقد يُسقط الصفحات من الفهرس فعلاً --}}
    <title>{{ $t('علم العقارية — نعود قريباً', 'Alam Realestate — Back soon') }}</title>
    <meta name="description" content="{{ $t('نعمل حالياً على تحديث موقع علم العقارية، وسنعود خلال وقت قصير.', 'Alam Realestate is being updated and will be back shortly.') }}">

    @include('layouts.partials.favicon')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased text-white bg-primary-950" style="direction: {{ $rtl ? 'rtl' : 'ltr' }}">

@if ($preview)
    <div class="bg-accent-500 text-primary-900 text-center text-xs font-bold px-4 py-2.5">
        {{ $t('معاينة — هذه الصفحة التي يراها الزوار ومحركات البحث أثناء إيقاف الموقع', 'Preview — this is what visitors and search engines see while the site is paused') }}
    </div>
@endif

<main class="relative isolate min-h-dvh overflow-hidden flex flex-col bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950">
    {{-- زخارف الخلفية — نفس روح صفحة 404 --}}
    <div class="absolute -top-24 -start-24 w-80 h-80 rounded-full bg-white/5 -z-10"></div>
    <div class="absolute -bottom-32 -end-20 w-[26rem] h-[26rem] rounded-full bg-accent-500/10 -z-10"></div>
    <div class="absolute top-1/3 end-[12%] w-40 h-40 rounded-full bg-white/[0.03] -z-10"></div>

    <header class="w-full max-w-5xl mx-auto px-4 sm:px-6 pt-6 flex items-center justify-between gap-4">
        <img src="{{ asset('images/logo.png') }}" alt="{{ $t('علم العقارية', 'Alam Realestate') }}"
             class="h-9 sm:h-10 w-auto drop-shadow-[0_0_8px_rgba(196,154,25,0.75)]">
        <a href="{{ route('site.locale', $rtl ? 'en' : 'ar') }}" rel="nofollow"
           class="inline-flex items-center gap-2 h-10 rounded-full bg-white/10 hover:bg-white/20 active:bg-white/25 border border-white/10 px-4 text-sm text-white/90 transition">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/></svg>
            {{ $rtl ? 'English' : 'عربي' }}
        </a>
    </header>

    <section class="flex-1 grid place-items-center px-4 sm:px-6 py-12 sm:py-16">
        <div class="w-full max-w-xl text-center">
            <span class="grid place-items-center w-20 h-20 mx-auto mb-7 rounded-full gold-gradient text-primary-900 shadow-xl shadow-accent-500/20 ring-8 ring-white/5">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            </span>

            <span class="inline-flex items-center gap-2 rounded-full glass px-4 py-1.5 text-xs text-white/85 mb-5">
                <span class="w-2 h-2 rounded-full bg-accent-500 animate-pulse"></span>
                {{ $t('تحديث مجدول', 'Scheduled update') }}
            </span>

            <h1 class="text-[1.75rem] sm:text-4xl font-bold leading-snug mb-4">{{ $t('نعود قريباً بتجربة أفضل', 'We’ll be back shortly') }}</h1>
            <p class="text-white/65 leading-relaxed max-w-md mx-auto text-balance">
                {{ $t('نعمل حالياً على تحديث الموقع وتجهيز عقارات جديدة لك. شكراً لصبرك، لن يطول الأمر.', 'We’re updating the website and preparing new properties for you. Thank you for your patience — it won’t be long.') }}
            </p>

            @if ($wa || $phone || $email)
                <div class="mt-10 rounded-3xl glass p-5 sm:p-6">
                    <p class="text-sm text-white/80 mb-4">{{ $t('تحتاج مساعدة الآن؟ فريقنا متاح للرد عليك', 'Need help now? Our team is here for you') }}</p>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3">
                        @if ($wa)
                            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="{{ $btn }} bg-success hover:brightness-110 text-white">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.7-.9-2-1-.3-.1-.5-.1-.6.2l-.8 1c-.2.2-.3.2-.6.1-1.5-.7-2.5-1.3-3.4-3-.3-.4.3-.4.7-1.3.1-.2 0-.4 0-.5l-.9-2.1c-.2-.5-.4-.5-.6-.5h-.5c-.2 0-.5.1-.7.3-.9.9-1.1 2-.7 3.3.5 1.6 1.6 3 3.1 4 2.2 1.5 3.8 1.6 4.6 1.5.6-.1 1.7-.7 1.9-1.4.2-.6.2-1.2.2-1.3-.1-.1-.2-.1-.5-.2z"/><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.2-1.2l-.3-.2-2.9.9.9-2.8-.2-.3A8 8 0 1 1 12 20z"/></svg>
                                {{ $t('واتساب', 'WhatsApp') }}
                            </a>
                        @endif
                        @if ($phone)
                            <a href="tel:{{ preg_replace('/\s/', '', $phone) }}" class="{{ $btn }} gold-gradient hover:brightness-110 text-primary-900">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                <span dir="ltr">{{ $phone }}</span>
                            </a>
                        @endif
                        @if ($email)
                            <a href="mailto:{{ $email }}" class="{{ $btn }} bg-white/10 hover:bg-white/15 border border-white/15 text-white">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg>
                                {{ $t('راسلنا', 'Email us') }}
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </section>

    <footer class="px-4 pb-6 text-center text-xs text-white/40">
        © {{ date('Y') }} {{ $t('علم العقارية. جميع الحقوق محفوظة', 'Alam Realestate. All rights reserved.') }}
    </footer>
</main>
</body>
</html>
