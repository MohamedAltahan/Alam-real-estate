@extends('layouts.dashboard')

@section('title', $owner->name)
@section('page-title', 'ملف مالك العقار')

@section('content')
<div>
    <x-flash />
    <a href="{{ route('dashboard.owners.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-700 mb-4"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>العودة لملاك العقارات</a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <aside class="space-y-5">
            <section class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
                <div class="navy-gradient px-6 py-5 text-white"><p class="text-xs text-white/60 mb-1">ملف المالك</p><h2 class="text-xl font-bold">{{ $owner->name }}</h2></div>
                <dl class="p-6 space-y-4 text-sm">
                    <div><dt class="text-xs text-gray-400 mb-1">الهاتف</dt><dd class="font-semibold text-ink" dir="ltr">{{ $owner->phone }}</dd></div>
                    <div><dt class="text-xs text-gray-400 mb-1">البريد الإلكتروني</dt><dd class="font-semibold text-ink break-all" dir="ltr">{{ $owner->email ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-400 mb-1">الجنسية</dt><dd class="font-semibold text-ink">{{ $owner->nationality ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-400 mb-1">المنطقة</dt><dd class="font-semibold text-ink">{{ $owner->area?->name ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-400 mb-1">العنوان المسجل</dt><dd class="font-semibold text-ink">{{ $owner->registered_address ?: '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-400 mb-1">حالة العقد</dt><dd><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $owner->status === 'active' ? 'bg-success-soft text-success' : 'bg-danger/10 text-danger' }}">{{ $owner->status === 'active' ? 'ساري' : 'منتهي' }}</span></dd></div>
                </dl>
            </section>

            <section class="rounded-card bg-white border border-gray-100 shadow-sm p-5">
                <h3 class="font-bold text-ink mb-3">العقد</h3>
                @if ($owner->contract_file)
                    <div class="rounded-2xl bg-primary-50 border border-primary-100 p-4"><div class="flex items-center gap-3"><span class="grid place-items-center w-11 h-11 rounded-xl bg-white text-primary-700"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span><div class="min-w-0 flex-1"><p class="text-sm font-semibold text-ink truncate">{{ $owner->contract_file->file_name }}</p><p class="text-xs text-gray-400">{{ strtoupper(pathinfo($owner->contract_file->file_name, PATHINFO_EXTENSION)) }} · {{ number_format($owner->contract_file->size / 1024, 1) }} KB</p></div></div><a href="{{ $owner->contract_file->getUrl() }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center justify-center w-full rounded-full bg-primary-900 text-white px-4 py-2 text-sm font-semibold">عرض أو تنزيل العقد</a></div>
                @else
                    <p class="text-center text-sm text-gray-400 py-5">لا يوجد عقد مرفوع.</p>
                @endif
            </section>
        </aside>

        <div class="lg:col-span-2">
            <section class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <div class="flex items-center justify-between gap-3 mb-5"><div><h3 class="font-bold text-ink">العقارات الخاصة بالمالك</h3><p class="text-sm text-gray-400">{{ number_format($owner->properties->count()) }} عقار</p></div></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @forelse ($owner->properties as $property)
                        @can('properties.view')
                            <a href="{{ route('dashboard.properties.show', $property) }}" class="group rounded-2xl border border-gray-100 overflow-hidden hover:border-primary-200 hover:shadow-sm transition">
                        @else
                            <div class="group rounded-2xl border border-gray-100 overflow-hidden">
                        @endcan
                            <div class="h-36 bg-gray-100 overflow-hidden">@if ($property->cover_url)<img src="{{ $property->cover_url }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" alt="">@else<div class="w-full h-full grid place-items-center text-gray-300"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg></div>@endif</div>
                            <div class="p-4"><div class="flex justify-between gap-3"><strong class="text-ink" dir="ltr">{{ $property->reference_code }}</strong><span class="text-xs rounded-full px-2.5 py-1" style="color: {{ $property->status?->color }}; background-color: {{ $property->status?->color }}1a">{{ $property->status?->name ?: 'بدون حالة' }}</span></div><p class="text-sm text-gray-600 mt-1 truncate">{{ $property->title }}</p><p class="text-xs text-gray-400 mt-2">{{ $property->unitType?->name ?: 'بدون نوع' }} · {{ $property->area?->name ?: 'بدون منطقة' }}</p><div class="flex justify-between gap-3 mt-3 pt-3 border-t border-gray-100"><span class="text-xs text-gray-400">مسؤول العقار: {{ $property->agent?->name ?: '—' }}</span><span class="text-sm font-bold text-primary-800">{{ number_format((float) $property->price) }} {{ auth()->user()->currencySymbol() }}</span></div></div>
                        @can('properties.view')</a>@else</div>@endcan
                    @empty
                        <p class="md:col-span-2 text-center text-sm text-gray-400 py-14">لا توجد عقارات مسجلة لهذا المالك.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
