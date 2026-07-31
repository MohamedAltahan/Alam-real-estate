@extends('layouts.dashboard')

@section('title', 'لوحة التحكم')
@section('page-title', 'لوحة التحكم')

@section('content')
    {{-- بانر الترحيب --}}
    <div class="rounded-card bg-primary-900 text-white p-6 sm:p-7 flex flex-wrap items-center gap-4 justify-between overflow-hidden relative">
        <div class="absolute -top-8 -start-8 w-40 h-40 rounded-full bg-white/5"></div>
        <div class="relative">
            <h2 class="text-xl sm:text-2xl font-bold mb-1">مرحباً {{ auth()->user()->name }} 👋</h2>
            <p class="text-white/70 text-sm">
                لديك <span class="text-accent-500 font-semibold">{{ $stats['requests'] }}</span> طلبات جديدة
                و<span class="text-accent-500 font-semibold">{{ $stats['properties'] }}</span> عقار في المنصة اليوم.
            </p>
        </div>
        <button class="relative inline-flex items-center gap-2 rounded-field bg-accent-500 hover:bg-accent-400 text-primary-900 font-semibold px-5 py-2.5 text-sm transition">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
            إضافة عقار جديد
        </button>
    </div>

    {{-- بطاقات المؤشرات --}}
    @php
        $cards = [
            ['label' => 'إجمالي العقارات', 'value' => $stats['properties'], 'trend' => '+20%', 'up' => true,  'color' => 'primary', 'icon' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/>'],
            ['label' => 'العملاء',         'value' => $stats['clients'],    'trend' => '+12%', 'up' => true,  'color' => 'success', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
            ['label' => 'طلبات جديدة',      'value' => $stats['requests'],   'trend' => 'جديد', 'up' => true,  'color' => 'accent',  'icon' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>'],
            ['label' => 'الوكلاء',          'value' => $stats['agents'],     'trend' => 'نشط',  'up' => true,  'color' => 'info',    'icon' => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>'],
        ];
        $tone = [
            'primary' => 'bg-primary-100 text-primary-700',
            'success' => 'bg-success-soft text-success',
            'accent'  => 'bg-accent-100 text-accent-700',
            'info'    => 'bg-info-soft text-info',
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-5">
        @foreach ($cards as $c)
            <div class="rounded-card bg-white border border-gray-100 p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <span class="grid place-items-center w-11 h-11 rounded-field {{ $tone[$c['color']] }}">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $c['icon'] !!}</svg>
                    </span>
                    <span class="text-xs font-semibold text-success bg-success-soft rounded-full px-2 py-1">{{ $c['trend'] }}</span>
                </div>
                <p class="mt-4 text-3xl font-bold text-ink tabular-nums">{{ number_format($c['value']) }}</p>
                <p class="text-sm text-gray-500 mt-0.5">{{ $c['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- منطقة الرسوم (هيكل مبدئي) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-5">
        <div class="lg:col-span-2 rounded-card bg-white border border-gray-100 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-ink">الإيراد الشهري</h3>
                <span class="text-xs text-gray-400">آخر 12 شهر</span>
            </div>
            <div class="h-56 grid place-items-center text-gray-300 text-sm border border-dashed border-gray-200 rounded-field">
                — الرسم البياني يُضاف لاحقاً —
            </div>
        </div>
        <div class="rounded-card bg-white border border-gray-100 p-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-ink">Leads حسب الشهر</h3>
            </div>
            <div class="h-56 grid place-items-center text-gray-300 text-sm border border-dashed border-gray-200 rounded-field">
                — قريباً —
            </div>
        </div>
    </div>
@endsection
