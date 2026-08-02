{{--
    وضع «الجزء»: طلبات الفلترة الحيّة (X-Fragment) ترجع محتوى الصفحة فقط بدون
    القالب — أخفّ على السيرفر ويكفي لاستبدال منطقة النتائج في المتصفح.
--}}
@if (request()->header('X-Fragment') === '1')
    @yield('content')
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة التحكم') — علم العقارية</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/dashboard.js'])
</head>

@php
    $nav = [
        ['label' => 'لوحة التحكم',    'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'grid'],
        ['label' => 'إدارة العملاء',  'route' => 'dashboard.clients.index', 'active' => 'dashboard.clients.*', 'icon' => 'users'],
        ['label' => 'ملاك العقارات',  'route' => 'dashboard.owners.index', 'active' => 'dashboard.owners.*', 'icon' => 'key'],
        ['label' => 'طلبات التواصل',  'route' => 'dashboard.requests.index', 'active' => 'dashboard.requests.*', 'icon' => 'mail'],
        ['label' => 'مصادر التسويق',  'route' => 'dashboard.sources.index', 'active' => 'dashboard.sources.*', 'icon' => 'mega'],
        ['label' => 'إدارة الموقع',   'route' => 'dashboard.website.index', 'active' => 'dashboard.website.*', 'icon' => 'globe'],
        ['label' => 'العقارات',       'route' => 'dashboard.properties.index', 'active' => 'dashboard.properties.*', 'icon' => 'building'],
        ['label' => 'إدارة الأدوار',  'route' => 'dashboard.roles.index', 'active' => 'dashboard.roles.*', 'icon' => 'shield'],
        ['label' => 'الصلاحيات',      'route' => 'dashboard.permissions.index', 'active' => 'dashboard.permissions.*', 'icon' => 'lock'],
        ['label' => 'المشرفين',       'route' => 'dashboard.supervisors.index', 'active' => 'dashboard.supervisors.*', 'icon' => 'gear'],
    ];

    $icons = [
        'grid'     => '<rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/>',
        'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'key'      => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
        'mail'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'mega'     => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.2-3"/>',
        'globe'    => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/>',
        'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/>',
        'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 0 0-16 0"/>',
        'phone'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'check'    => '<circle cx="12" cy="12" r="10"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
    ];

    $feedTone = [
        'accent'  => 'bg-accent-100 text-accent-700',
        'success' => 'bg-success-soft text-success',
        'info'    => 'bg-info-soft text-info',
        'primary' => 'bg-primary-50 text-primary-700',
    ];

    $me = auth()->user();
    $initial = mb_substr($me->name ?? 'ع', 0, 1);
@endphp

<body class="bg-gray-50 font-sans text-ink antialiased" x-data="{ logoutOpen: false, sidebarOpen: false }">

<div class="flex min-h-screen">

    {{-- ===================== القائمة الجانبية ===================== --}}
    <div x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-primary-950/50 lg:hidden"></div>

    <aside class="fixed lg:sticky inset-y-0 start-0 top-0 z-40 w-72 shrink-0 h-screen flex flex-col
                  bg-sidebar text-white transition-transform duration-300 lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full'">

        {{-- الشعار — النسخة الأصلية نفسها المستخدمة في فوتر الموقع (موحّدة) --}}
        <a href="{{ route('dashboard') }}" class="h-[68px] shrink-0 flex items-center justify-center px-5">
            {{-- هالة ذهبية خفيفة تُبرز الأجزاء الكحلية من الشعار فوق خلفية القائمة الداكنة --}}
            <img src="{{ asset('images/logo.png') }}" alt="علم العقارية"
                 class="h-10 w-auto drop-shadow-[0_0_8px_rgba(196,154,25,0.75)]">
        </a>

        {{-- الروابط --}}
        <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-1">
            @foreach ($nav as $item)
                @php $active = request()->routeIs($item['active']); @endphp
                {{-- العنصر النشط: حبّة دائرية رمادية دافئة بحدّ ذهبي على جهة البداية (مطابق لتصميم Figma) --}}
                <a href="{{ route($item['route']) }}"
                   class="group flex items-center gap-3 rounded-full px-3.5 h-11 text-sm border-s-2 transition
                          {{ $active
                              ? 'bg-sidebar-active border-sidebar-ring text-white font-bold'
                              : 'border-transparent text-white/70 hover:bg-white/5 hover:text-white' }}">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round"
                         class="shrink-0 {{ $active ? 'text-accent-500' : 'text-white/55 group-hover:text-white/90' }}">{!! $icons[$item['icon']] !!}</svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- المستخدم + تسجيل الخروج --}}
        <div class="shrink-0 border-t border-white/10 px-4 pt-4 pb-5">
            <div class="flex items-center gap-3">
                <span class="grid place-items-center w-9 h-9 shrink-0 rounded-full bg-accent-500 text-primary-900 font-bold text-sm">{{ $initial }}</span>
                <div class="leading-tight min-w-0 flex-1">
                    <p class="text-sm font-bold truncate">{{ $me->name }}</p>
                    <p class="text-[11px] text-white/45 truncate">{{ $me->job_title ?? 'مدير النظام' }}</p>
                </div>
                <a href="{{ route('dashboard.supervisors.index') }}" class="text-white/40 hover:text-white/80 transition" aria-label="الإعدادات">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">{!! $icons['gear'] !!}</svg>
                </a>
            </div>

            <button type="button" @click="logoutOpen = true"
                    class="mt-4 w-full flex items-center justify-center gap-2 text-sm font-bold text-danger hover:text-danger/80 transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">{!! $icons['logout'] !!}</svg>
                تسجيل الخروج
            </button>
        </div>
    </aside>

    {{-- ===================== المحتوى ===================== --}}
    <div class="flex-1 min-w-0 flex flex-col">

        {{-- الشريط العلوي --}}
        <header class="h-[68px] shrink-0 bg-white flex items-center gap-3 px-4 sm:px-6 sticky top-0 z-20">

            <button type="button" @click="sidebarOpen = true"
                    class="lg:hidden grid place-items-center w-10 h-10 rounded-full text-gray-500 hover:bg-gray-50" aria-label="القائمة">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>

            <h1 class="text-lg font-bold text-ink">@yield('page-title', 'لوحة التحكم')</h1>

            <div class="ms-auto flex items-center gap-3">

                {{-- ===== الإشعارات ===== --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                    <button type="button" @click="open = ! open"
                            class="relative grid place-items-center w-[42px] h-[42px] rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition"
                            aria-label="الإشعارات">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                        @if ($feedUnread)
                            <span class="absolute top-2 end-2.5 w-2.5 h-2.5 rounded-full bg-danger ring-2 ring-white"></span>
                        @endif
                    </button>

                    <div x-cloak x-show="open" @click.outside="open = false"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         class="absolute top-[calc(100%+8px)] end-0 w-[340px] max-w-[calc(100vw-2rem)]
                                rounded-2xl bg-white border border-gray-100 shadow-xl shadow-primary-950/10 overflow-hidden">

                        {{-- رأس القائمة --}}
                        <div class="flex items-center gap-2 px-4 h-14 border-b border-gray-100">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                                 stroke-linecap="round" stroke-linejoin="round" class="text-ink"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                            <span class="text-sm font-bold text-ink">الإشعارات</span>
                            @if ($feedUnread)
                                <span class="grid place-items-center min-w-5 h-5 px-1.5 rounded-full bg-danger text-white text-[11px] font-bold">{{ $feedUnread }}</span>
                            @endif
                            @if ($feedUnread)
                                <form method="POST" action="{{ route('dashboard.notifications.read-all') }}" class="ms-auto">
                                    @csrf
                                    <button class="flex items-center gap-1 text-xs text-gray-500 hover:text-primary-800 transition">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 13 4 4L14 7"/><path d="m11 15 2 2L22 7"/></svg>
                                        قراءة الكل
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- العناصر --}}
                        <div class="max-h-[380px] overflow-y-auto">
                            @forelse ($feedItems as $n)
                                <a href="{{ $n['url'] }}" class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 hover:bg-gray-50/70 transition">
                                    <span class="grid place-items-center w-9 h-9 shrink-0 rounded-full {{ $feedTone[$n['tone']] }}">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                                             stroke-linecap="round" stroke-linejoin="round">{!! $icons[$n['icon']] !!}</svg>
                                    </span>
                                    <span class="min-w-0 flex-1 leading-snug">
                                        <span class="block text-[13px] font-semibold text-ink truncate">{{ $n['title'] }}</span>
                                        <span class="block text-[11px] text-gray-400 mt-0.5">{{ $n['at']->locale('ar')->diffForHumans() }}</span>
                                    </span>
                                    @if ($n['unread'])
                                        <span class="w-2 h-2 shrink-0 rounded-full bg-info"></span>
                                    @endif
                                </a>
                            @empty
                                <p class="px-4 py-10 text-center text-sm text-gray-400">لا توجد إشعارات بعد</p>
                            @endforelse
                        </div>

                        <a href="{{ route('dashboard.requests.index') }}"
                           class="block py-3.5 text-center text-sm font-bold text-primary-800 hover:bg-gray-50 transition">عرض كل الإشعارات</a>
                    </div>
                </div>

                {{-- ===== البروفايل ===== --}}
                <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                    <button type="button" @click="open = ! open"
                            class="flex items-center gap-2.5 rounded-full border border-gray-200 bg-white ps-1.5 pe-2 py-1 hover:bg-gray-50 transition">
                        <span class="grid place-items-center w-8 h-8 shrink-0 me-2 rounded-full bg-accent-500 text-primary-900 font-bold text-sm">{{ $initial }}</span>
                        <span class="hidden sm:block text-end leading-tight">
                            <span class="block text-[13px] font-bold text-ink">{{ $me->name }}</span>
                            <span class="block text-[10px] text-gray-400">{{ $me->job_title ?? 'مدير النظام' }}</span>
                        </span>
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                             stroke-linecap="round" stroke-linejoin="round" class="text-gray-400 transition-transform"
                             :class="open && 'rotate-180'"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <div x-cloak x-show="open" @click.outside="open = false"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         class="absolute top-[calc(100%+8px)] end-0 w-[314px] max-w-[calc(100vw-2rem)]
                                rounded-2xl bg-white border border-gray-100 shadow-xl shadow-primary-950/10 overflow-hidden">

                        <div class="flex items-center gap-3 px-4 py-4 border-b border-gray-100">
                            <span class="grid place-items-center w-10 h-10 shrink-0 rounded-full bg-accent-500 text-primary-900 font-bold">{{ $initial }}</span>
                            <span class="min-w-0 flex-1 leading-tight">
                                <span class="block text-sm font-bold text-ink truncate">{{ $me->name }}</span>
                                <span class="block text-[11px] text-gray-400 truncate">{{ $me->job_title ?? 'مدير النظام' }}</span>
                            </span>
                            <a href="{{ route('dashboard.supervisors.index') }}" class="text-gray-400 hover:text-ink transition" aria-label="الإعدادات">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                     stroke-linecap="round" stroke-linejoin="round">{!! $icons['gear'] !!}</svg>
                            </a>
                        </div>

                        <a href="#" class="flex items-center gap-3 px-4 h-12 text-sm text-ink hover:bg-gray-50 transition">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round" class="text-gray-500">{!! $icons['user'] !!}</svg>
                            عرض الملف الشخصي
                        </a>
                        <a href="#" class="flex items-center gap-3 px-4 h-12 text-sm text-ink hover:bg-gray-50 transition border-b border-gray-100">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                 stroke-linecap="round" stroke-linejoin="round" class="text-gray-500">{!! $icons['key'] !!}</svg>
                            تغيير كلمة المرور
                        </a>

                        <button type="button" @click="open = false; logoutOpen = true"
                                class="w-full flex items-center gap-3 px-4 h-12 text-sm font-bold text-danger hover:bg-danger/5 transition">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                                 stroke-linecap="round" stroke-linejoin="round">{!! $icons['logout'] !!}</svg>
                            تسجيل الخروج
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 sm:px-6 pt-6 pb-8">
            @yield('content')
        </main>
    </div>
</div>

{{-- ===================== تأكيد تسجيل الخروج ===================== --}}
<div x-cloak x-show="logoutOpen" x-transition.opacity.duration.150ms
     class="fixed inset-0 z-50 grid place-items-center p-4 bg-primary-950/40 backdrop-blur-[3px]"
     @keydown.escape.window="logoutOpen = false">

    <div @click.outside="logoutOpen = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         class="w-full max-w-[470px] rounded-2xl bg-white shadow-2xl p-6 sm:p-7">

        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-bold text-ink">تسجيل الخروج</h2>
            <button type="button" @click="logoutOpen = false" class="text-gray-400 hover:text-ink transition" aria-label="إغلاق">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="grid place-items-center w-[72px] h-[72px] mx-auto rounded-full bg-danger text-white mb-5">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">{!! $icons['logout'] !!}</svg>
        </div>

        <h3 class="text-xl font-bold text-ink text-center mb-2">تسجيل الخروج</h3>
        <p class="text-sm text-gray-500 text-center mb-7">هل أنت متأكد أنك تريد تسجيل الخروج من حسابك؟</p>

        <div class="flex items-center gap-3">
            <form method="POST" action="{{ route('logout') }}" class="flex-1">
                @csrf
                <button type="submit" class="w-full rounded-full bg-primary-800 hover:bg-primary-900 text-white font-bold py-3 text-sm transition">تأكيد</button>
            </form>
            <button type="button" @click="logoutOpen = false"
                    class="flex-1 rounded-full bg-white border border-gray-200 hover:bg-gray-50 text-ink font-bold py-3 text-sm transition">إلغاء</button>
        </div>
    </div>
</div>

@stack('scripts')
</body>
</html>
@endif
