@php
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $t = fn ($ar, $en) => $locale === 'ar' ? $ar : $en;
    $set = fn ($group, $key, $def = '') => \App\Models\Setting::get($group, $key, $def);
    $wa = preg_replace('/[^0-9]/', '', $set('contact', 'whatsapp') ?: $set('contact', 'phone'));
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'علم العقارية') — @yield('seo_title', $t('عقارات الكويت', 'Kuwait Real Estate'))</title>
    <meta name="description" content="@yield('seo_description', '')">
    @hasSection('seo_keywords')<meta name="keywords" content="@yield('seo_keywords')">@endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- x-data على الـ body حتى تعمل مخازن Alpine ($store) في أي عنصر بأي صفحة --}}
<body x-data class="font-sans text-ink bg-white antialiased" style="direction: {{ $rtl ? 'rtl' : 'ltr' }}">

    {{-- ===== النافبار ===== --}}
    @php $navSolid = trim($__env->yieldContent('nav_solid')) === '1'; @endphp
    <header x-data="{ open: false, scrolled: {{ $navSolid ? 'true' : 'false' }} }"
            @scroll.window="scrolled = {{ $navSolid ? 'true' : 'window.scrollY > 24' }}"
            class="fixed top-0 inset-x-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-4">
        {{-- غير مُمرَّر: نفس زجاج صندوق البحث بالضبط · مُمرَّر: نفس تدرّج كارت "عروض عقارية مميزة" --}}
        <nav class="rounded-full px-3 sm:px-4 h-16 flex items-center gap-4 text-white shadow-sm shadow-primary-950/10 transition-all duration-300"
             :class="scrolled ? 'navy-gradient border border-white/10 backdrop-blur-xl' : 'glass'">
            {{-- الشعار — النسخة الأصلية نفسها المستخدمة في الفوتر (موحّدة في كل الموقع) --}}
            <a href="{{ route('site.home') }}" class="flex items-center shrink-0">
                {{-- هالة ذهبية خفيفة تُبرز الأجزاء الكحلية من الشعار فوق الخلفية الداكنة --}}
                <img src="{{ asset('images/logo.png') }}" alt="{{ $t('علم العقارية', 'Alam Realestate') }}"
                     class="h-8 sm:h-9 w-auto drop-shadow-[0_0_8px_rgba(196,154,25,0.75)]">
            </a>

            {{-- روابط سطح المكتب --}}
            <div class="hidden md:flex items-center gap-1 mx-auto">
                @foreach ([['site.home', $t('الرئيسية', 'Home')], ['site.about', $t('من نحن', 'About')], ['site.properties', $t('العقارات', 'Properties')], ['site.contact', $t('تواصل معنا', 'Contact')]] as [$route, $label])
                    <a href="{{ route($route) }}" class="px-4 py-2 rounded-full text-sm transition {{ request()->routeIs($route) ? 'bg-white/20 text-white font-semibold shadow-sm' : 'text-white/80 hover:text-white hover:bg-white/10' }}">{{ $label }}</a>
                @endforeach
            </div>

            {{-- يمين: لغة + زر --}}
            <div class="flex items-center gap-2 ms-auto md:ms-0">
                <a href="{{ route('site.locale', $rtl ? 'en' : 'ar') }}" class="grid place-items-center w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 border border-white/10 text-white/90 transition" title="{{ $t('English', 'عربي') }}">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/></svg>
                </a>
                <a href="{{ route('site.list-property') }}" class="hidden sm:inline-flex items-center gap-2 rounded-full gold-gradient hover:brightness-110 text-primary-900 font-semibold px-5 py-2.5 text-sm shadow-lg shadow-accent-500/20 transition">{{ $t('اعرض عقارك', 'List your property') }}</a>
                <button @click="open = !open" class="md:hidden grid place-items-center w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 border border-white/10" aria-label="menu">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 12h16M4 6h16M4 18h16"/></svg>
                </button>
            </div>
        </nav>

        {{-- قائمة الموبايل --}}
        <div x-show="open" x-cloak class="md:hidden mt-2 rounded-3xl border border-white/10 bg-primary-900/95 backdrop-blur-xl shadow-xl text-white px-4 py-3 space-y-1">
            @foreach ([[route('site.home'), $t('الرئيسية', 'Home')], [route('site.about'), $t('من نحن', 'About')], [route('site.properties'), $t('العقارات', 'Properties')], [route('site.contact'), $t('تواصل معنا', 'Contact')]] as [$href, $label])
                <a href="{{ $href }}" class="block px-3 py-2 rounded-full text-sm text-white/80 hover:bg-white/10">{{ $label }}</a>
            @endforeach
            <a href="{{ route('site.list-property') }}" class="block px-3 py-2 rounded-full text-sm gold-gradient text-primary-900 font-semibold text-center">{{ $t('اعرض عقارك', 'List your property') }}</a>
        </div>
      </div>
    </header>

    <main>
        @yield('content')
    </main>

    {{-- ===== الفوتر ===== --}}
    @php
        $socialIcons = [
            'twitter' => '<path d="M18.9 2H22l-7 8 8.2 12h-6.4l-5-7.3-5.8 7.3H2.9l7.5-8.6L2.5 2h6.6l4.5 6.7zm-1.1 18h1.8L7.3 3.9H5.4z"/>',
            'facebook' => '<path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5h1.65V3.6c-.29-.04-1.27-.13-2.41-.13-2.39 0-4.02 1.46-4.02 4.13v2.3H7.5V13h2.77v8z"/>',
            'linkedin' => '<path d="M4.98 3.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM3 9h4v12H3zM9 9h3.8v1.7h.05c.53-1 1.83-2.05 3.76-2.05C20.5 8.65 21 11 21 14.2V21h-4v-6c0-1.43-.03-3.27-2-3.27-2 0-2.3 1.56-2.3 3.17V21H9z"/>',
            'instagram' => '<path d="M12 2.2c-2.7 0-3 .01-4.05.06-1.05.05-1.77.22-2.4.46a4.8 4.8 0 0 0-1.75 1.14A4.8 4.8 0 0 0 2.66 5.6c-.24.63-.4 1.35-.46 2.4C2.16 9.05 2.15 9.38 2.15 12s.01 2.95.06 4c.05 1.05.22 1.77.46 2.4a4.8 4.8 0 0 0 1.14 1.75 4.8 4.8 0 0 0 1.75 1.14c.63.24 1.35.4 2.4.46 1.05.05 1.38.06 4.05.06s3-.01 4.05-.06c1.05-.05 1.77-.22 2.4-.46a5 5 0 0 0 2.89-2.89c.24-.63.4-1.35.46-2.4.05-1.05.06-1.38.06-4s-.01-2.95-.06-4c-.05-1.05-.22-1.77-.46-2.4a4.8 4.8 0 0 0-1.14-1.75 4.8 4.8 0 0 0-1.75-1.14c-.63-.24-1.35-.4-2.4-.46-1.05-.05-1.38-.06-4.05-.06zm0 1.8c2.62 0 2.93.01 3.97.06.96.04 1.48.2 1.82.34.46.18.79.39 1.13.73.34.34.55.67.73 1.13.13.34.3.86.34 1.82.05 1.04.06 1.35.06 3.92s-.01 2.88-.06 3.92c-.4.96-.2 1.48-.34 1.82-.18.46-.39.79-.73 1.13-.34.34-.67.55-1.13.73-.34.13-.86.3-1.82.34-1.04.05-1.35.06-3.97.06s-2.93-.01-3.97-.06c-.96-.04-1.48-.2-1.82-.34a3 3 0 0 1-1.13-.73 3 3 0 0 1-.73-1.13c-.13-.34-.3-.86-.34-1.82C3.96 14.88 3.95 14.57 3.95 12s.01-2.88.06-3.92c.04-.96.2-1.48.34-1.82.18-.46.39-.79.73-1.13a3 3 0 0 1 1.13-.73c.34-.13.86-.3 1.82-.34C9.07 4.01 9.38 4 12 4z"/><path d="M12 15.2a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4zm0-8.13a4.93 4.93 0 1 0 0 9.86 4.93 4.93 0 0 0 0-9.86zM18.4 6.87a1.15 1.15 0 1 1-2.3 0 1.15 1.15 0 0 1 2.3 0z"/>',
        ];
        $cRow = 'flex items-center gap-2.5 text-sm text-gray-500';
        $cIcon = 'grid place-items-center w-5 shrink-0 text-primary-800';
    @endphp
    <footer class="bg-gray-50 border-t border-gray-100 mt-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr] gap-8 lg:gap-10">
                {{-- العلامة + التواصل --}}
                <div>
                    <div class="flex items-center mb-4">
                        <img src="{{ asset('images/logo.png') }}" alt="{{ $t('علم العقارية', 'Alam Realestate') }}" class="h-10 w-auto">
                    </div>
                    <p class="text-sm text-gray-500 leading-relaxed mb-5">{{ $t('علم العقارية منصة متخصصة في تقديم حلول عقارية متكاملة، نوفّر من خلالها مجموعة متنوعة من العقارات السكنية والتجارية والمفروشة، مع تجربة بحث سهلة ومعلومات دقيقة تساعدك على اتخاذ القرار بثقة.', 'Alam Realestate is a specialized platform delivering complete property solutions — residential, commercial and furnished — with easy search and accurate information.') }}</p>
                    <ul class="space-y-1.5">
                        @if ($set('contact', 'email'))
                            <li class="{{ $cRow }}">
                                <span class="{{ $cIcon }}"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg></span>
                                <a href="mailto:{{ $set('contact', 'email') }}" class="hover:text-primary-700" dir="ltr">{{ $set('contact', 'email') }}</a>
                            </li>
                        @endif
                        @if ($set('contact', 'phone'))
                            <li class="{{ $cRow }}">
                                <span class="{{ $cIcon }}"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                                <a href="tel:{{ preg_replace('/\s/', '', $set('contact', 'phone')) }}" class="hover:text-primary-700" dir="ltr">{{ $set('contact', 'phone') }}</a>
                            </li>
                        @endif
                        @if ($set('contact', 'address'))
                            <li class="{{ $cRow }}">
                                <span class="{{ $cIcon }}"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                                <span>{{ $set('contact', 'address') }}</span>
                            </li>
                        @endif
                    </ul>
                </div>

                {{-- روابط سريعة --}}
                <div>
                    <h4 class="font-bold text-ink mb-4 text-sm">{{ $t('روابط سريعة :', 'Quick Links') }}</h4>
                    <ul class="space-y-3 text-sm text-gray-500">
                        <li><a href="{{ route('site.home') }}" class="hover:text-primary-700">{{ $t('الرئيسية', 'Home') }}</a></li>
                        <li><a href="{{ route('site.about') }}" class="hover:text-primary-700">{{ $t('من نحن', 'About') }}</a></li>
                        <li><a href="{{ route('site.home') }}#areas" class="hover:text-primary-700">{{ $t('أفضل المناطق', 'Top Areas') }}</a></li>
                        <li><a href="{{ route('site.properties') }}" class="hover:text-primary-700">{{ $t('العقارات', 'Properties') }}</a></li>
                        <li><a href="{{ route('site.list-property') }}" class="hover:text-primary-700">{{ $t('اعرض عقارك', 'List your property') }}</a></li>
                    </ul>
                </div>

                {{-- روابط مهمة --}}
                <div>
                    <h4 class="font-bold text-ink mb-4 text-sm">{{ $t('روابط مهمة :', 'Important') }}</h4>
                    <ul class="space-y-3 text-sm text-gray-500">
                        <li><a href="{{ route('site.terms') }}" class="hover:text-primary-700">{{ $t('الشروط والأحكام', 'Terms & Conditions') }}</a></li>
                        <li><a href="{{ route('site.privacy') }}" class="hover:text-primary-700">{{ $t('سياسة الخصوصية', 'Privacy Policy') }}</a></li>
                        <li><a href="{{ route('site.faq') }}" class="hover:text-primary-700">{{ $t('الأسئلة الشائعة', 'FAQ') }}</a></li>
                        <li><a href="{{ route('site.contact') }}" class="hover:text-primary-700">{{ $t('تواصل معنا', 'Contact us') }}</a></li>
                    </ul>
                </div>

                {{-- اتصال --}}
                <div>
                    <h4 class="font-bold text-ink mb-4 text-sm">{{ $t('اتصال', 'Get in touch') }}</h4>
                    <ul class="space-y-3 text-sm text-gray-500">
                        @if ($set('contact', 'phone'))<li><a href="tel:{{ preg_replace('/\s/', '', $set('contact', 'phone')) }}" class="hover:text-primary-700" dir="ltr">{{ $set('contact', 'phone') }}</a></li>@endif
                        @if ($set('contact', 'whatsapp'))<li><a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $set('contact', 'whatsapp')) }}" target="_blank" class="hover:text-primary-700" dir="ltr">{{ $set('contact', 'whatsapp') }}</a></li>@endif
                        @if ($set('contact', 'email'))<li><a href="mailto:{{ $set('contact', 'email') }}" class="hover:text-primary-700" dir="ltr">{{ $set('contact', 'email') }}</a></li>@endif
                        @if ($set('contact', 'address'))<li>{{ $set('contact', 'address') }}</li>@endif
                    </ul>
                </div>
            </div>

            {{-- الشريط السفلي --}}
            <div class="border-t border-gray-200 mt-10 pt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-xs text-gray-400 order-2 sm:order-1">© {{ date('Y') }} {{ $t('علم العقارية . جميع الحقوق محفوظة', 'Alam Realestate. All rights reserved.') }}</p>
                @php $activeSocials = collect(['twitter', 'facebook', 'linkedin', 'instagram'])->filter(fn ($s) => $set('social', $s)); @endphp
                @if ($activeSocials->count())
                    <div class="flex items-center gap-3 order-1 sm:order-2">
                        <span class="text-xs text-gray-400">{{ $t('تابعنا', 'Follow us') }}</span>
                        <div class="flex items-center gap-2">
                            @foreach ($activeSocials as $soc)
                                <a href="{{ $set('social', $soc) }}" target="_blank" rel="noopener" aria-label="{{ $soc }}"
                                   class="grid place-items-center w-9 h-9 rounded-full navy-gradient hover:gold-gradient text-white hover:text-primary-900 transition">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">{!! $socialIcons[$soc] !!}</svg>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </footer>

    {{-- ===== أزرار عائمة ===== --}}
    <div class="fixed bottom-5 end-5 z-40 flex flex-col gap-2">
        @if ($wa)
            <a href="https://wa.me/{{ $wa }}" target="_blank" class="grid place-items-center w-12 h-12 rounded-full bg-success text-white shadow-lg hover:scale-105 transition" aria-label="WhatsApp">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.7-.9-2-1-.3-.1-.5-.1-.6.2l-.8 1c-.2.2-.3.2-.6.1-1.5-.7-2.5-1.3-3.4-3-.3-.4.3-.4.7-1.3.1-.2 0-.4 0-.5l-.9-2.1c-.2-.5-.4-.5-.6-.5h-.5c-.2 0-.5.1-.7.3-.9.9-1.1 2-.7 3.3.5 1.6 1.6 3 3.1 4 2.2 1.5 3.8 1.6 4.6 1.5.6-.1 1.7-.7 1.9-1.4.2-.6.2-1.2.2-1.3-.1-.1-.2-.1-.5-.2z"/><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.2-1.2l-.3-.2-2.9.9.9-2.8-.2-.3A8 8 0 1 1 12 20z"/></svg>
            </a>
        @endif
        @if ($set('contact', 'phone'))
            <a href="tel:{{ preg_replace('/\s/', '', $set('contact', 'phone')) }}" class="grid place-items-center w-12 h-12 rounded-full navy-gradient text-white shadow-lg hover:scale-105 transition" aria-label="call">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </a>
        @endif
    </div>

    {{-- ===== مشغّل الفيديو ===== --}}
    <div x-cloak x-show="$store.video.open" x-transition.opacity.duration.200ms
         @keydown.escape.window="$store.video.close()"
         @click.self="$store.video.close()"
         class="fixed inset-0 z-[60] grid place-items-center p-4 sm:p-6 bg-primary-950/85 backdrop-blur-md">

        <div x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 scale-95"
             class="w-full max-w-4xl">

            <div class="flex items-center gap-3 mb-3">
                <h3 class="min-w-0 flex-1 text-white font-bold text-sm sm:text-base truncate" x-text="$store.video.title"></h3>
                <button type="button" @click="$store.video.close()"
                        class="grid place-items-center w-10 h-10 shrink-0 rounded-full bg-white/10 border border-white/20 text-white hover:bg-white/20 transition"
                        aria-label="{{ $t('إغلاق', 'Close') }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="relative aspect-video rounded-3xl overflow-hidden bg-black ring-1 ring-white/15 shadow-2xl shadow-primary-950/60">
                <template x-if="$store.video.src">
                    <iframe :src="$store.video.src" class="absolute inset-0 w-full h-full" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                </template>
            </div>
        </div>
    </div>
</body>
</html>
