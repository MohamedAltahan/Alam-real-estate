@extends('layouts.dashboard')

@section('title', 'العقارات')
@section('page-title', 'العقارات')

@section('content')
<div x-data="{ delAction: '', delName: '' }">
    <x-flash />

    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">العقارات</h2>
            <p class="text-sm text-gray-500">{{ number_format($properties->total()) }} عقار</p>
        </div>
        @can('properties.create')
            <a href="{{ route('dashboard.properties.create') }}" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2.5 text-sm transition">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة عقار
            </a>
        @endcan
    </div>

    {{-- الفلاتر تُطبَّق فور الاختيار (البحث بالضغط على Enter) --}}
    <form method="GET" id="properties-filters" data-live-filters class="flex flex-wrap items-center gap-3 mb-4">
        <div class="relative flex-1 min-w-[220px]">
            <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث برمز العقار..." autocomplete="off" class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
        </div>
        <div class="relative">
            <select name="purpose" class="appearance-none rounded-full bg-white border border-gray-200 ps-4 pe-10 h-11 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
                <option value="">بيع وإيجار</option>
                <option value="sale" @selected(($filters['purpose'] ?? '') === 'sale')>بيع</option>
                <option value="rent" @selected(($filters['purpose'] ?? '') === 'rent')>إيجار</option>
            </select>
            <svg class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
        </div>
        <div class="relative">
            <select name="status_id" class="appearance-none rounded-full bg-white border border-gray-200 ps-4 pe-10 h-11 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
                <option value="">كل الحالات</option>
                @foreach ($statuses as $s)<option value="{{ $s->id }}" @selected(($filters['status_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach
            </select>
            <svg class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
        </div>
    </form>

    <div data-results>
    <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                        <th class="text-start font-medium px-4 py-3">العقار</th>
                        <th class="text-start font-medium px-4 py-3">النوع / المنطقة</th>
                        <th class="text-start font-medium px-4 py-3">السعر</th>
                        <th class="text-start font-medium px-4 py-3">الحالة</th>
                        <th class="text-start font-medium px-4 py-3">الوكيل</th>
                        <th class="text-start font-medium px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($properties as $p)
                        {{-- النقر على الصف كله يفتح العقار --}}
                        <tr class="hover:bg-gray-50/50 cursor-pointer" @click="window.location = '{{ route('dashboard.properties.show', $p) }}'">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-11 h-11 rounded-lg bg-gray-100 overflow-hidden shrink-0 grid place-items-center text-gray-300">
                                        @if ($p->cover_url)<img src="{{ $p->cover_url }}" class="w-full h-full object-cover" alt="">
                                        @else <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/></svg>@endif
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-ink truncate">{{ $p->title }}</span>
                                        <span class="block text-xs text-gray-400"><span dir="ltr">{{ $p->reference_code }}</span></span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $p->unitType?->name }} · {{ $p->area?->name }}</td>
                            <td class="px-4 py-3 text-ink font-semibold tabular-nums">{{ number_format($p->price, 3) }} د.ك<span class="text-xs text-gray-400 font-normal">{{ $p->purpose === 'rent' ? '/'.($p->price_period === 'yearly' ? 'سنة' : 'شهر') : '' }}</span></td>
                            <td class="px-4 py-3">
                                @if ($p->status)<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" style="color: {{ $p->status->color }}; background-color: {{ $p->status->color }}1a;"><span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $p->status->color }}"></span>{{ $p->status->name }}</span>@endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $p->agent?->name ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @can('properties.delete')
                                    <button @click.stop="delAction = '{{ route('dashboard.properties.destroy', $p) }}'; delName = @js($p->reference_code); $dispatch('open-modal', 'prop-delete')" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف"><x-icon.trash /></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-16 text-center text-gray-400">لا توجد عقارات. <a href="{{ route('dashboard.properties.create') }}" class="text-primary-700 font-medium">أضف أول عقار</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $properties->links() }}</div>
    </div>{{-- /منطقة النتائج --}}

    <x-modal name="prop-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
            <h3 class="font-bold text-ink mb-1">حذف العقار</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف العقار "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="delOpen = false" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
</div>
@endsection
