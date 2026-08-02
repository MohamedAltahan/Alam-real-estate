@extends('layouts.dashboard')

@section('title', 'إدارة العملاء')
@section('page-title', 'إدارة العملاء')

@php
    $ratingTone = fn ($r) => is_null($r) ? 'text-gray-400 bg-gray-100'
        : ($r >= 70 ? 'text-success bg-success-soft' : ($r >= 40 ? 'text-warning bg-warning-soft' : 'text-danger bg-danger-soft'));

    $activeStage = $filters['stage_id'] ?? '';
    // رابط زر المرحلة = نفس الفلاتر الحالية مع تبديل stage_id فقط
    $stageUrl = fn ($id) => route('dashboard.clients.index', array_filter(array_merge($filters, ['stage_id' => $id]), fn ($v) => $v !== null && $v !== ''));

    $pill = 'inline-flex items-center gap-2 rounded-full h-9 px-3.5 text-sm font-semibold whitespace-nowrap transition';
    $pillOn = $pill.' bg-primary-900 text-white';
    $pillOff = $pill.' text-gray-600 hover:bg-white hover:text-ink';
    $badgeOn = 'grid place-items-center min-w-5 h-5 px-1 rounded-full bg-white/20 text-white text-[11px] font-bold tabular-nums';
    $badgeOff = 'grid place-items-center min-w-5 h-5 px-1 rounded-full bg-gray-200/80 text-gray-600 text-[11px] font-bold tabular-nums';
@endphp

@section('content')
<div x-data="{ addOpen: {{ $errors->any() ? 'true' : 'false' }}, delOpen: false, delAction: '', delName: '' }">

    @if (session('success'))
        <div class="mb-4 rounded-field bg-success-soft text-success text-sm px-4 py-3 flex items-center gap-2">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- الترويسة + البحث + إضافة --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">إدارة العملاء</h2>
            <p class="text-sm text-gray-500">{{ number_format($stageCounts['total']) }} عميل إجمالاً</p>
        </div>

        <div class="flex items-center gap-3">
            {{-- نموذج الفلترة الموحّد: كل عناصره تنضمّ له بالخاصية form="clients-filters" --}}
            <form method="GET" id="clients-filters" data-live-filters></form>
            <input type="hidden" name="stage_id" value="{{ $activeStage }}" form="clients-filters">

            <div class="relative w-[260px] max-w-[55vw]">
                <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث..." autocomplete="off" form="clients-filters"
                       class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
            </div>

            @can('clients.create')
                <button @click="addOpen = true"
                        class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-5 h-11 text-sm transition shrink-0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    إضافة عميل
                </button>
            @endcan
        </div>
    </div>

    {{-- ===== منطقة النتائج: تُستبدَل وحدها عند الفلترة الحيّة ===== --}}
    <div data-results>

    {{-- صفّ الفلاتر: أزرار المراحل + فلتر الوكيل — داخل النتائج لأن أعداد المراحل تتغيّر مع الفلاتر --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="inline-flex items-center gap-1 rounded-full bg-gray-100/70 border border-gray-100 p-1 max-w-full overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <a href="{{ $stageUrl(null) }}" data-filter-set="stage_id" data-filter-value="" class="{{ $activeStage === '' ? $pillOn : $pillOff }}">
                كل العملاء
                <span class="{{ $activeStage === '' ? $badgeOn : $badgeOff }}">{{ $stageCounts['total'] }}</span>
            </a>
            @foreach ($stages as $s)
                @php $on = (string) $activeStage === (string) $s->id; @endphp
                <a href="{{ $stageUrl($s->id) }}" data-filter-set="stage_id" data-filter-value="{{ $s->id }}" class="{{ $on ? $pillOn : $pillOff }}">
                    {{ $s->name }}
                    <span class="{{ $on ? $badgeOn : $badgeOff }}">{{ $stageCounts['stages'][$s->id] ?? 0 }}</span>
                </a>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <div class="relative">
                <select name="agent_id" form="clients-filters"
                        class="appearance-none rounded-full bg-white border border-gray-200 ps-4 pe-10 h-11 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
                    <option value="">كل الوكلاء</option>
                    @foreach ($agents as $u)
                        <option value="{{ $u->id }}" @selected(($filters['agent_id'] ?? '') == $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                <svg class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
            </div>
            @if (array_filter($filters))
                <a href="{{ route('dashboard.clients.index') }}" class="text-sm text-gray-500 hover:text-danger whitespace-nowrap">مسح الفلاتر</a>
            @endif
        </div>
    </div>

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
                        {{-- النقر على الصف كله يفتح العميل --}}
                        <tr class="hover:bg-gray-50/50 transition cursor-pointer"
                            @click="window.location = '{{ route('dashboard.clients.show', $c) }}'">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid place-items-center w-9 h-9 rounded-full bg-primary-100 text-primary-700 font-bold">{{ mb_substr($c->name, 0, 1) }}</span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-ink truncate">{{ $c->name }}</span>
                                        <span class="block text-xs text-gray-400 truncate"><span dir="ltr">{{ $c->email ?: '—' }}</span></span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><span dir="ltr">{{ $c->phone }}</span></td>
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
                                @can('clients.delete')
                                    <button @click.stop="delOpen = true; delAction = '{{ route('dashboard.clients.destroy', $c) }}'; delName = @js($c->name)"
                                            class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف">
                                        <x-icon.trash />
                                    </button>
                                @endcan
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
    </div>{{-- /منطقة النتائج --}}

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
                    <button type="button" @click="addOpen = false" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                    <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">إضافة العميل</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== مودال تأكيد الحذف ===== --}}
    <div x-show="delOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
        <div class="absolute inset-0 bg-primary-950/50" @click="delOpen = false"></div>
        <div class="relative w-full max-w-md bg-white rounded-card shadow-2xl p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4">
                <x-icon.trash size="24" />
            </span>
            <h3 class="font-bold text-ink mb-1">حذف العميل</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف العميل "<span x-text="delName" class="font-semibold text-ink"></span>"؟ لا يمكن التراجع.</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="delOpen = false" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100 border border-gray-200">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </div>
</div>
@endsection
