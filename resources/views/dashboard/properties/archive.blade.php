@extends('layouts.dashboard')

@section('title', 'أرشيف العقارات')
@section('page-title', 'العقارات')

@section('content')
<div>
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">أرشيف العقارات</h2>
            <p class="text-sm text-gray-500">{{ number_format($properties->total()) }} عقار مؤرشف — مخفية من الموقع والقوائم ونتائج البحث، وتحتفظ بحالتها وقت الأرشفة، ويمكن استرجاع أي منها كما كان.</p>
        </div>
        <a href="{{ route('dashboard.properties.index') }}" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-semibold px-4 py-2.5 text-sm transition">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            العودة للعقارات
        </a>
    </div>

    {{-- بحث بالرقم المرجعي أو الاسم أو المبنى --}}
    <form method="GET" class="mb-4 max-w-md">
        <label class="relative block">
            <span class="sr-only">بحث</span>
            <svg class="absolute start-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" value="{{ $search }}" placeholder="باسم أو رقم العقار أو اسم المبنى..."
                   class="w-full rounded-full border border-gray-200 bg-white ps-10 pe-4 h-10 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
        </label>
    </form>

    <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                        <th class="text-start font-medium px-4 py-3 w-12">#</th>
                        <th class="text-start font-medium px-4 py-3">العقار</th>
                        <th class="text-start font-medium px-4 py-3">النوع / المنطقة</th>
                        <th class="text-start font-medium px-4 py-3 whitespace-nowrap">المبنى / المالك</th>
                        <th class="text-start font-medium px-4 py-3 whitespace-nowrap" title="الحالة كما كانت وقت الأرشفة">الحالة</th>
                        <th class="text-start font-medium px-4 py-3 whitespace-nowrap">تاريخ الأرشفة</th>
                        <th class="text-start font-medium px-4 py-3 whitespace-nowrap">أرشفه</th>
                        <th class="text-start font-medium px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($properties as $p)
                        {{-- النقر على الصف يفتح كل بيانات العقار المؤرشف --}}
                        <tr class="hover:bg-gray-50/50 cursor-pointer" @click="window.location = '{{ route('dashboard.properties.show', $p) }}'">
                            <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $properties->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-11 h-11 rounded-lg bg-gray-100 overflow-hidden shrink-0 grid place-items-center text-gray-300">
                                        @if ($p->cover_url)<img src="{{ $p->cover_url }}" class="w-full h-full object-cover grayscale" alt="">
                                        @else <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/></svg>@endif
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-ink truncate">{{ $p->title }}</span>
                                        <span class="block text-xs text-gray-400" dir="ltr">#{{ $p->reference_code }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $p->unitType?->name ?: '—' }} · {{ $p->area?->name ?: '—' }}</td>
                            <td class="px-4 py-3 max-w-[200px]">
                                <span class="block truncate {{ $p->building_name ? 'font-semibold text-ink' : 'text-gray-300' }}">{{ $p->building_name ?: '—' }}</span>
                                @if ($p->owner)<span class="block text-xs text-gray-400 truncate">{{ $p->owner->name }}</span>@endif
                            </td>
                            {{-- الأرشفة مستقلة عن الحالة: تبقى كما كانت (متاح/مباع...) --}}
                            <td class="px-4 py-3">
                                @if ($p->status)
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap" style="color: {{ $p->status->color }}; background-color: {{ $p->status->color }}1a;"><span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $p->status->color }}"></span>{{ $p->status->name }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap tabular-nums" dir="ltr" title="{{ $p->deleted_at?->diffForHumans() }}">{{ $p->deleted_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $p->deletedBy?->name ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('dashboard.properties.restore', $p) }}" @click.stop>
                                    @csrf @method('PATCH')
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-success-soft text-success hover:brightness-95 font-semibold px-3.5 py-1.5 text-xs whitespace-nowrap transition" title="إرجاع العقار إلى القائمة والموقع">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/></svg>
                                        استرجاع
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400">{{ $search !== '' ? 'لا توجد عقارات مؤرشفة مطابقة للبحث.' : 'لا توجد عقارات مؤرشفة.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $properties->links() }}</div>
</div>
@endsection
