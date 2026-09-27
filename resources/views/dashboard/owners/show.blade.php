@extends('layouts.dashboard')

@section('title', $owner->name)
@section('page-title', 'ملف مالك العقار')

@php
    use App\Support\OwnerFormData;

    $files = OwnerFormData::filesPayload($owner);
@endphp

@section('content')
<div x-data="ownerForm({
        storeUrl: @js(route('dashboard.owners.store')),
        updateBase: @js(url('dashboard/owners')),
        countries: @js($countries),
        errors: @js(OwnerFormData::errorMap($errors)),
        reopen: @js(OwnerFormData::reopen($errors)),
     })">
    <x-flash />
    <a href="{{ route('dashboard.owners.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-700 mb-4"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>العودة لملاك العقارات</a>

    {{-- ===== بيانات المالك (بعرض الصفحة) ===== --}}
    <section class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden mb-6">
        <div class="navy-gradient px-6 py-5 text-white flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs text-white/60 mb-1">ملف المالك</p>
                <h2 class="text-2xl font-bold">{{ $owner->name }}</h2>
                {{-- موبايل المالك نفسه: يُعرض هنا فقط --}}
                <p class="mt-1.5 text-sm text-white/80">
                    <span class="text-white/60">موبايل المالك:</span>
                    <bdi dir="ltr" class="font-semibold tabular-nums">{{ $owner->full_mobile ?: '—' }}</bdi>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click='openFiles(@json($owner->name), @json($files))'
                        class="inline-flex items-center gap-2 rounded-full bg-white text-primary-900 hover:bg-accent-100 font-bold px-4 h-10 text-sm transition">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                    ملفات المالك
                    <span class="grid place-items-center min-w-5 h-5 px-1.5 rounded-full bg-primary-900 text-white text-[11px] font-bold">{{ count($files) }}</span>
                </button>
                @can('property_owners.edit')
                    <button type="button" @click='startEdit(@json(OwnerFormData::editPayload($owner)))'
                            class="inline-flex items-center gap-2 rounded-full border border-white/40 hover:bg-white/10 text-white font-semibold px-4 h-10 text-sm transition">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                        تعديل
                    </button>
                @endcan
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-5 text-sm">
            <div class="md:col-span-2">
                <p class="text-xs text-gray-400 mb-2">المسؤولون</p>
                <ul class="space-y-2">
                    @forelse ($owner->contacts as $contact)
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <a href="https://wa.me/{{ $contact->whatsapp_number }}" target="_blank" rel="noopener" class="font-semibold text-ink hover:text-primary-700 tabular-nums" dir="ltr">{{ $contact->full_phone }}</a>
                            @if ($contact->role || $contact->name)
                                <span class="text-xs text-gray-500">{{ collect([$contact->role, $contact->name])->filter()->implode(' · ') }}</span>
                            @endif
                            @if ($contact->properties->isNotEmpty())
                                <span class="inline-flex flex-wrap items-center gap-1 text-[11px] text-gray-400">
                                    مسؤول عن:
                                    @foreach ($contact->properties as $p)
                                        @can('properties.view')
                                            <a href="{{ route('dashboard.properties.show', $p->id) }}" class="rounded-full bg-primary-50 text-primary-700 hover:bg-primary-100 px-2 py-0.5 font-bold tabular-nums" dir="ltr">{{ $p->reference_code }}</a>
                                        @else
                                            <span class="rounded-full bg-gray-100 text-gray-600 px-2 py-0.5 font-bold tabular-nums" dir="ltr">{{ $p->reference_code }}</span>
                                        @endcan
                                    @endforeach
                                </span>
                            @endif
                        </li>
                    @empty
                        <li class="font-semibold text-ink"><bdi dir="ltr">{{ $owner->full_phone ?: '—' }}</bdi></li>
                    @endforelse
                </ul>
            </div>
            <div><p class="text-xs text-gray-400 mb-1">البريد الإلكتروني</p><p class="font-semibold text-ink break-all"><bdi dir="ltr">{{ $owner->email ?: '—' }}</bdi></p></div>
            <div><p class="text-xs text-gray-400 mb-1">المنطقة</p><p class="font-semibold text-ink">{{ collect([$owner->area?->name, $owner->area?->city?->name])->filter()->implode(' — ') ?: '—' }}</p></div>
            <div class="md:col-span-2"><p class="text-xs text-gray-400 mb-1">العنوان المسجل</p><p class="font-semibold text-ink">{{ $owner->registered_address ?: '—' }}</p></div>
            <div class="md:col-span-2"><p class="text-xs text-gray-400 mb-1">الملاحظات</p><p class="text-ink whitespace-pre-line">{{ $owner->notes ?: '—' }}</p></div>
        </div>
    </section>

    {{-- ===== عقارات المالك ===== --}}
    <section class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
        <div class="flex items-center justify-between gap-3 mb-5"><div><h3 class="font-bold text-ink">العقارات الخاصة بالمالك</h3><p class="text-sm text-gray-400">{{ number_format($owner->properties->count()) }} عقار</p></div></div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse ($owner->properties as $property)
                @can('properties.view')
                    <a href="{{ route('dashboard.properties.show', $property) }}" class="group rounded-2xl border border-gray-100 overflow-hidden hover:border-primary-200 hover:shadow-sm transition">
                @else
                    <div class="group rounded-2xl border border-gray-100 overflow-hidden">
                @endcan
                    <div class="h-36 bg-gray-100 overflow-hidden">@if ($property->cover_url)<img src="{{ $property->cover_url }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" alt="">@else<div class="w-full h-full grid place-items-center text-gray-300"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg></div>@endif</div>
                    <div class="p-4"><div class="flex justify-between gap-3"><strong class="text-ink" dir="ltr">{{ $property->reference_code }}</strong><span class="text-xs rounded-full px-2.5 py-1" style="color: {{ $property->status?->color }}; background-color: {{ $property->status?->color }}1a">{{ $property->status?->name ?: 'بدون حالة' }}</span></div><p class="text-sm text-gray-600 mt-1 truncate">{{ $property->title }}</p><p class="text-xs text-gray-400 mt-2">{{ $property->unitType?->name ?: 'بدون نوع' }} · {{ $property->area?->name ?: 'بدون منطقة' }}</p><div class="flex justify-between gap-3 mt-3 pt-3 border-t border-gray-100"><span class="text-xs text-gray-400">مندوب المبيعات: {{ $property->agent?->name ?: '—' }}</span><span class="text-sm font-bold text-primary-800">{{ number_format((float) $property->price) }} {{ auth()->user()->currencySymbol() }}</span></div></div>
                @can('properties.view')</a>@else</div>@endcan
            @empty
                <p class="md:col-span-2 xl:col-span-3 text-center text-sm text-gray-400 py-14">لا توجد عقارات مسجلة لهذا المالك.</p>
            @endforelse
        </div>
    </section>

    @include('dashboard.owners._files-modal')

    @include('dashboard.owners._form-modal')
</div>
@endsection
