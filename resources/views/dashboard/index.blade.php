@extends('layouts.dashboard')

@section('title', 'لوحة التحكم')
@section('page-title', 'لوحة التحكم')

@php
    $cardIcons = [
        'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/>',
        'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'money'    => '<line x1="12" y1="2" x2="12" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
        'check'    => '<circle cx="12" cy="12" r="10"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
    ];
    $cardTone = [
        'info'    => 'bg-info-soft text-info',
        'accent'  => 'bg-accent-100 text-accent-700',
        'primary' => 'bg-primary-50 text-primary-700',
        'success' => 'bg-success-soft text-success',
    ];

    $panel = 'rounded-2xl bg-white border border-gray-100';

    // ألوان الحرف الأول في قائمة طلبات التواصل
    $avatarTones = ['bg-success', 'bg-warning', 'bg-primary-700', 'bg-info', 'bg-danger'];

    // حالة طلب التواصل → بادج
    $requestBadge = function ($r) {
        if ($r->status === 'contacted') {
            return ['تم الرد', 'bg-success-soft text-success'];
        }

        return $r->is_read
            ? ['قيد المعالجة', 'bg-warning-soft text-warning']
            : ['جديد', 'bg-info-soft text-info'];
    };
@endphp

@section('content')

    {{-- ===================== بانر الترحيب ===================== --}}
    <div class="relative overflow-hidden rounded-2xl bg-primary-900 text-white px-6 sm:px-7 py-6">
        <div class="absolute -top-16 -start-10 w-56 h-56 rounded-full bg-white/[0.04]"></div>
        <div class="absolute -bottom-24 start-40 w-64 h-64 rounded-full bg-white/[0.03]"></div>

        <div class="relative flex flex-wrap items-center gap-5 justify-between">
            <div class="min-w-0">
                <p class="text-[11px] text-white/45 mb-1.5">{{ $today }}</p>
                <h2 class="text-xl sm:text-[26px] font-bold mb-2">مرحباً، {{ auth()->user()->name }} 👋</h2>
                <p class="text-sm text-white/65">
                    لديك <span class="text-accent-500 font-bold">{{ $openRequests }} طلبات جديدة</span>
                    و <span class="text-accent-500 font-bold">{{ $openFollowUps }} متابعة معلقة</span> اليوم
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard.requests.index') }}"
                   class="inline-flex items-center gap-2 rounded-full bg-accent-500 hover:bg-accent-400 text-primary-900 font-bold px-4 h-11 text-sm transition">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
                    طلبات التواصل
                </a>
                <a href="{{ route('dashboard.properties.create') }}"
                   class="inline-flex items-center gap-2 rounded-full border border-white/25 hover:bg-white/10 text-white font-bold px-4 h-11 text-sm transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    إضافة عقار
                </a>
            </div>
        </div>
    </div>

    {{-- ===================== بطاقات المؤشرات ===================== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-5">
        @foreach ($stats as $c)
            <div class="{{ $panel }} p-5">
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-gray-500 mb-1">{{ $c['label'] }}</p>
                        <p class="text-[22px] font-bold text-ink tabular-nums leading-none">{!! $c['value'] !!}</p>
                    </div>
                    <span class="grid place-items-center w-11 h-11 shrink-0 rounded-full {{ $cardTone[$c['tone']] }}">
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                             stroke-linecap="round" stroke-linejoin="round">{!! $cardIcons[$c['icon']] !!}</svg>
                    </span>
                </div>

                <p class="mt-3 pt-3 border-t border-gray-100 text-xs text-gray-500">
                    <span class="text-ink font-bold tabular-nums">{{ $c['sub_value'] }}</span>
                    {{ $c['sub_label'] }}
                </p>

                <p class="mt-2 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold tabular-nums
                                 {{ $c['trend']['up'] ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger' }}">
                        {{ $c['trend']['pct'] }}
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            @if ($c['trend']['up'])<path d="M7 17 17 7M9 7h8v8"/>@else<path d="M7 7l10 10M17 9v8H9"/>@endif
                        </svg>
                    </span>
                    <span class="text-[10px] text-gray-400">
                        {{ $c['trend']['up'] ? 'أعلى من الشهر الماضي' : 'أقل من الشهر الماضي' }}
                    </span>
                </p>
            </div>
        @endforeach
    </div>

    {{-- ===================== الرسوم البيانية ===================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">

        {{-- الـ Leads حسب الشهر --}}
        <div class="{{ $panel }} lg:col-span-2 p-5">
            <h3 class="font-bold text-ink">الـ Leads حسب الشهر</h3>
            <p class="text-xs text-gray-400 mb-4">مقارنة الطلبات والمغلقة</p>
            <div class="h-[250px]">
                <canvas data-chart="leads" data-payload="{{ json_encode($charts['leads']) }}"></canvas>
            </div>
        </div>

        {{-- الإيراد الشهري --}}
        <div class="{{ $panel }} p-5">
            <h3 class="font-bold text-ink">الإيراد الشهري</h3>
            <p class="text-xs text-gray-400 mb-4">آلاف KD — آخر 8 أشهر</p>
            <div class="h-[250px]">
                <canvas data-chart="revenue" data-payload="{{ json_encode($charts['revenue']) }}"></canvas>
            </div>
        </div>
    </div>

    {{-- ===================== القوائم السفلية ===================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">

        {{-- معدل التحويل --}}
        <div class="{{ $panel }} p-5">
            <h3 class="font-bold text-ink">معدل التحويل</h3>
            <p class="text-xs text-gray-400">نسبة إغلاق الصفقات</p>

            {{-- دليل الرسم — الألوان مطابقة لـ SLATE و GREEN في resources/js/dashboard.js --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 mt-3 mb-4">
                <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500">
                    <span class="w-2.5 h-2.5 rounded-sm bg-[#5c6484]"></span>
                    عدد الطلبات
                </span>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500">
                    <span class="w-2.5 h-2.5 rounded-full bg-[#4eae7f]"></span>
                    نسبة إغلاق الصفقات (%)
                </span>
            </div>

            <div class="h-[250px]">
                <canvas data-chart="conversion" data-payload="{{ json_encode($charts['conversion']) }}"></canvas>
            </div>
        </div>

        {{-- أحدث العقارات --}}
        <div class="{{ $panel }} p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-ink">أحدث العقارات</h3>
                <a href="{{ route('dashboard.properties.index') }}" class="text-xs text-primary-600 hover:text-primary-800 transition">عرض الكل</a>
            </div>

            <ul class="space-y-3.5">
                @forelse ($latestProperties as $p)
                    <li>
                        <a href="{{ route('dashboard.properties.show', $p) }}" class="flex items-center gap-3 group">
                            <span class="w-11 h-11 shrink-0 rounded-xl bg-gray-100 overflow-hidden grid place-items-center text-gray-300">
                                @if ($p->cover_url)
                                    <img src="{{ $p->cover_url }}" alt="" class="w-full h-full object-cover">
                                @else
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg>
                                @endif
                            </span>

                            <span class="min-w-0 flex-1 leading-tight">
                                <span class="block text-[13px] font-bold text-ink truncate group-hover:text-primary-700 transition">{{ $p->title }}</span>
                                <span class="flex items-center gap-1 text-[11px] text-gray-400 mt-1">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="shrink-0"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    <span class="truncate">{{ $p->reference_code }} · {{ $p->area?->name }}</span>
                                </span>
                            </span>

                            @php
                                $unit = 'KD'.($p->purpose === 'rent' ? '/'.($p->price_period === 'yearly' ? 'سنة' : 'شهر') : '');
                            @endphp
                            <span class="shrink-0 text-end">
                                <span class="block text-[13px] font-bold text-ink tabular-nums">
                                    {{ number_format($p->price) }} <span class="text-[10px] text-gray-400 font-bold">{{ $unit }}</span>
                                </span>
                                @if ($p->status)
                                    <span class="inline-block mt-1.5 rounded-md px-2 py-0.5 text-[10px] font-bold
                                        @class([
                                            'bg-success-soft text-success' => $p->status->key === 'available',
                                            'bg-warning-soft text-warning' => $p->status->key === 'reserved',
                                            'bg-danger-soft text-danger' => $p->status->key === 'sold',
                                        ])">{{ $p->status->name }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-gray-400">لا توجد عقارات بعد</li>
                @endforelse
            </ul>
        </div>

        {{-- أحدث طلبات التواصل --}}
        <div class="{{ $panel }} p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-ink">أحدث طلبات التواصل</h3>
                <a href="{{ route('dashboard.requests.index') }}" class="text-xs text-primary-600 hover:text-primary-800 transition">عرض الكل</a>
            </div>

            <ul class="space-y-3.5">
                @forelse ($latestRequests as $i => $r)
                    @php [$badgeLabel, $badgeClass] = $requestBadge($r); @endphp
                    <li class="flex items-center gap-3">
                        <span class="grid place-items-center w-11 h-11 shrink-0 rounded-full text-white font-bold {{ $avatarTones[$i % count($avatarTones)] }}">
                            {{ mb_substr($r->name, 0, 1) }}
                        </span>

                        <span class="min-w-0 flex-1 leading-tight">
                            <span class="block text-[13px] font-bold text-ink truncate">{{ $r->name }}</span>
                            <span class="block text-[11px] text-gray-400 truncate mt-1">{{ $r->subject ?: $r->requestType?->name }}</span>
                        </span>

                        <span class="shrink-0 rounded-md px-2.5 py-1 text-[10px] font-bold {{ $badgeClass }}">{{ $badgeLabel }}</span>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-gray-400">لا توجد طلبات بعد</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
