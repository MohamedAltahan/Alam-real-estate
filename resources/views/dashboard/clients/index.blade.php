@extends('layouts.dashboard')

@section('title', 'إدارة العملاء')
@section('page-title', 'إدارة العملاء')

@php
    $ratingTone = fn ($r) => is_null($r) ? 'text-gray-400 bg-gray-100'
        : ($r >= 70 ? 'text-success bg-success-soft' : ($r >= 40 ? 'text-warning bg-warning-soft' : 'text-danger bg-danger-soft'));
@endphp

@section('content')
<div x-data="{ addOpen: {{ $errors->any() ? 'true' : 'false' }}, delOpen: false, delAction: '', delName: '' }">

    @if (session('success'))
        <div class="mb-4 rounded-field bg-success-soft text-success text-sm px-4 py-3 flex items-center gap-2">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- الترويسة --}}
    <div class="flex items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">العملاء</h2>
            <p class="text-sm text-gray-500">{{ number_format($clients->total()) }} عميل مسجّل</p>
        </div>
        @can('clients.create')
            <button @click="addOpen = true"
                    class="inline-flex items-center gap-2 rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2.5 text-sm transition">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة عميل
            </button>
        @endcan
    </div>

    {{-- بحث وفلاتر --}}
    <form method="GET" class="flex flex-wrap items-center gap-3 mb-4">
        <div class="relative flex-1 min-w-[220px]">
            <svg class="absolute inset-y-0 start-3 my-auto text-gray-400" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالاسم أو الهاتف أو البريد..."
                   class="w-full rounded-field bg-white border border-gray-200 ps-10 pe-4 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
        </div>
        <select name="stage_id" class="rounded-field bg-white border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:border-primary-500">
            <option value="">كل المراحل</option>
            @foreach ($stages as $s)
                <option value="{{ $s->id }}" @selected(($filters['stage_id'] ?? '') == $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
        <select name="agent_id" class="rounded-field bg-white border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:border-primary-500">
            <option value="">كل الوكلاء</option>
            @foreach ($agents as $u)
                <option value="{{ $u->id }}" @selected(($filters['agent_id'] ?? '') == $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
        <button class="rounded-field bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-4 py-2.5 text-sm">تصفية</button>
        @if (array_filter($filters))
            <a href="{{ route('dashboard.clients.index') }}" class="text-sm text-gray-500 hover:text-danger">مسح</a>
        @endif
    </form>

    {{-- الجدول --}}
    <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-start">
                <thead>
                    <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                        <th class="text-start font-medium px-4 py-3">العميل</th>
                        <th class="text-start font-medium px-4 py-3">الهاتف</th>
                        <th class="text-start font-medium px-4 py-3">المنطقة</th>
                        <th class="text-start font-medium px-4 py-3">الوكيل</th>
                        <th class="text-start font-medium px-4 py-3">المرحلة</th>
                        <th class="text-start font-medium px-4 py-3">التقييم</th>
                        <th class="text-start font-medium px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($clients as $c)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-4 py-3">
                                <a href="{{ route('dashboard.clients.show', $c) }}" class="flex items-center gap-3 group">
                                    <span class="grid place-items-center w-9 h-9 rounded-full bg-primary-100 text-primary-700 font-bold">{{ mb_substr($c->name, 0, 1) }}</span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-ink group-hover:text-primary-700 truncate">{{ $c->name }}</span>
                                        <span class="block text-xs text-gray-400 truncate" dir="ltr">{{ $c->email ?: '—' }}</span>
                                    </span>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600" dir="ltr">{{ $c->phone }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $c->area?->name ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $c->agent?->name ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($c->stage)
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                          style="color: {{ $c->stage->color }}; background-color: {{ $c->stage->color }}1a;">
                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $c->stage->color }}"></span>
                                        {{ $c->stage->name }}
                                    </span>
                                @else <span class="text-gray-300">—</span> @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full px-2.5 py-1 text-xs font-semibold tabular-nums {{ $ratingTone($c->rating) }}">
                                    {{ is_null($c->rating) ? '—' : $c->rating . '%' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('dashboard.clients.show', $c) }}" class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="عرض">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    @can('clients.delete')
                                        <button @click="delOpen = true; delAction = '{{ route('dashboard.clients.destroy', $c) }}'; delName = @js($c->name)"
                                                class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-danger hover:bg-danger/10" title="حذف">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-16 text-center text-gray-400">لا يوجد عملاء مطابقون.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $clients->links() }}</div>

    {{-- ===== مودال إضافة عميل ===== --}}
    <div x-show="addOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
        <div class="absolute inset-0 bg-primary-950/50" @click="addOpen = false"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-card shadow-2xl max-h-[90vh] overflow-y-auto"
             x-transition.opacity>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink">إضافة عميل جديد</h3>
                <button @click="addOpen = false" class="text-gray-400 hover:text-gray-700">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('dashboard.clients.store') }}">
                @csrf
                <div class="p-6">
                    @include('dashboard.clients._form', ['client' => null])
                </div>
                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                    <button type="button" @click="addOpen = false" class="rounded-field px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                    <button type="submit" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">إضافة العميل</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== مودال تأكيد الحذف ===== --}}
    <div x-show="delOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
        <div class="absolute inset-0 bg-primary-950/50" @click="delOpen = false"></div>
        <div class="relative w-full max-w-md bg-white rounded-card shadow-2xl p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/></svg>
            </span>
            <h3 class="font-bold text-ink mb-1">حذف العميل</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف العميل "<span x-text="delName" class="font-semibold text-ink"></span>"؟ لا يمكن التراجع.</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="delOpen = false" class="rounded-field px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100 border border-gray-200">إلغاء</button>
                <button type="submit" class="rounded-field bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </div>
</div>
@endsection
