<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'لوحة التحكم') — علم العقارية</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 font-sans text-ink antialiased">
<div class="flex min-h-screen">

    {{-- ===== القائمة الجانبية ===== --}}
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
            'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
            'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
            'key'      => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
            'mail'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
            'mega'     => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.2-3"/>',
            'globe'    => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/>',
            'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/>',
            'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        ];
    @endphp

    <aside class="w-64 shrink-0 bg-sidebar text-white sticky top-0 h-screen flex flex-col">
        {{-- الشعار --}}
        <div class="h-16 flex items-center gap-2.5 px-5 border-b border-white/10">
            <span class="grid place-items-center w-9 h-9 rounded-lg bg-accent-500 text-primary-900 font-extrabold text-sm">علم</span>
            <div class="leading-tight">
                <p class="font-bold text-[15px]">علم العقارية</p>
                <p class="text-[11px] text-white/50">Alam Realestate</p>
            </div>
        </div>

        {{-- روابط القائمة --}}
        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            @foreach ($nav as $item)
                @php $active = ($item['route'] !== '#') && request()->routeIs($item['active'] ?? $item['route']); @endphp
                <a href="{{ $item['route'] === '#' ? '#' : route($item['route']) }}"
                   class="group flex items-center gap-3 rounded-field px-3 py-2.5 text-sm transition
                          {{ $active ? 'bg-white/10 text-white font-semibold' : 'text-white/70 hover:bg-white/5 hover:text-white' }}">
                    <span class="{{ $active ? 'text-accent-500' : 'text-white/60 group-hover:text-white/90' }}">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$item['icon']] !!}</svg>
                    </span>
                    {{ $item['label'] }}
                    @if ($active)<span class="ms-auto w-1.5 h-1.5 rounded-full bg-accent-500"></span>@endif
                </a>
            @endforeach
        </nav>

        {{-- المستخدم + خروج --}}
        <div class="border-t border-white/10 p-3">
            <div class="flex items-center gap-3 px-2 py-2">
                <span class="grid place-items-center w-9 h-9 rounded-full bg-accent-500 text-primary-900 font-bold text-sm">
                    {{ mb_substr(auth()->user()->name ?? 'ع', 0, 1) }}
                </span>
                <div class="leading-tight min-w-0">
                    <p class="text-sm font-semibold truncate">{{ auth()->user()->name ?? 'مستخدم' }}</p>
                    <p class="text-[11px] text-white/50 truncate">{{ auth()->user()->job_title ?? 'مدير النظام' }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="mt-1 w-full flex items-center gap-2 rounded-field px-3 py-2 text-sm text-danger/90 hover:bg-danger/10 transition">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
                    تسجيل الخروج
                </button>
            </form>
        </div>
    </aside>

    {{-- ===== المحتوى ===== --}}
    <div class="flex-1 min-w-0 flex flex-col">
        {{-- الشريط العلوي --}}
        <header class="h-16 bg-white border-b border-gray-100 flex items-center gap-4 px-6 sticky top-0 z-10">
            <h1 class="text-lg font-bold text-ink">@yield('page-title', 'لوحة التحكم')</h1>

            <div class="flex-1 max-w-md hidden md:block">
                <div class="relative">
                    <svg class="absolute inset-y-0 start-3 my-auto text-gray-400" width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" placeholder="بحث سريع..."
                           class="w-full rounded-field bg-gray-50 border border-gray-200 ps-10 pe-4 py-2 text-sm
                                  focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white">
                </div>
            </div>

            <div class="ms-auto flex items-center gap-3">
                <button class="relative grid place-items-center w-10 h-10 rounded-full hover:bg-gray-100 text-gray-500" aria-label="الإشعارات">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    <span class="absolute top-2 end-2.5 w-2 h-2 rounded-full bg-danger ring-2 ring-white"></span>
                </button>
                <div class="flex items-center gap-2.5 ps-2">
                    <span class="grid place-items-center w-9 h-9 rounded-full bg-primary-900 text-white font-bold text-sm">
                        {{ mb_substr(auth()->user()->name ?? 'ع', 0, 1) }}
                    </span>
                    <span class="text-sm font-medium text-ink hidden sm:block">{{ auth()->user()->name ?? 'مستخدم' }}</span>
                </div>
            </div>
        </header>

        <main class="flex-1 p-6">
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
