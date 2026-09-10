@extends('layouts.dashboard')

@section('title', 'الطلبات المميزة')
@section('page-title', 'طلبات التواصل')

@section('content')
<div x-data>
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">الطلبات المميزة</h2>
            <p class="text-sm text-gray-500">{{ number_format($clients->total()) }} طلب مميز · العملاء المعلَّمون «طلب مميز» في فورم العميل</p>
        </div>
        @include('dashboard.requests._tabs', ['tab' => 'featured', 'counts' => $tabCounts])
    </div>

    <x-filter-bar id="featured-filters" cols="xl:grid-cols-4" :reset="array_filter($filters) ? route('dashboard.requests.featured') : null">
        <x-filter-input label="بحث" name="search" :value="$filters['search'] ?? ''" type="search" search
                        placeholder="الاسم أو الهاتف أو البريد..." />
        <x-filter-select label="الحالة" name="stage_id" placeholder="كل الحالات"
                         :options="$stages->pluck('name', 'id')" :selected="$filters['stage_id'] ?? null" />
        <x-filter-select label="مندوب المبيعات" name="agent_id" placeholder="كل مندوبي المبيعات"
                         :options="$agents->pluck('name', 'id')" :selected="$filters['agent_id'] ?? null" />
    </x-filter-bar>

    <div data-results>
        <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-start">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 w-12">#</th>
                            <th class="text-start font-medium px-4 py-3">العميل</th>
                            <th class="text-start font-medium px-4 py-3">الهاتف</th>
                            <th class="text-start font-medium px-4 py-3">احتياج العقار</th>
                            <th class="text-start font-medium px-4 py-3">مندوب المبيعات</th>
                            <th class="text-start font-medium px-4 py-3">الحالة</th>
                            <th class="text-start font-medium px-4 py-3">تاريخ الإضافة</th>
                            <th class="text-start font-medium px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($clients as $c)
                            @php $firstNeed = $c->needs->first(); @endphp
                            {{-- النقر على الصف كله يفتح العميل --}}
                            <tr class="hover:bg-gray-50/50 transition @can('clients.view') cursor-pointer @endcan"
                                @can('clients.view') @click="window.location = '{{ route('dashboard.clients.show', $c) }}'" @endcan>
                                <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $clients->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid place-items-center w-9 h-9 rounded-full bg-accent-500/15 text-accent-600 font-bold shrink-0">{{ mb_substr($c->name, 0, 1) }}</span>
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-ink truncate">{{ $c->name }}</span>
                                            <span class="block text-xs text-gray-400 truncate"><span dir="ltr">{{ $c->email ?: '—' }}</span></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><span dir="ltr">{{ $c->full_phone ?: '—' }}</span></td>
                                <td class="px-4 py-3 max-w-[260px]">
                                    @if ($firstNeed)
                                        <span class="flex items-center gap-2">
                                            <span class="text-ink truncate">{{ $firstNeed->describe() }}</span>
                                            @if ($c->needs->count() > 1)
                                                <span class="rounded-full bg-primary-100 text-primary-700 text-[11px] font-bold px-2 py-0.5 shrink-0">+{{ $c->needs->count() - 1 }}</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $c->agent?->name ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($c->stage)
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap"
                                              style="color: {{ $c->stage->color }}; background-color: {{ $c->stage->color }}1a;">
                                            <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $c->stage->color }}"></span>
                                            {{ $c->stage->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap tabular-nums">{{ $c->created_at?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @if ($c->whatsapp_number)
                                            <a href="https://wa.me/{{ $c->whatsapp_number }}" target="_blank" @click.stop
                                               class="rounded-full bg-success/10 text-success hover:bg-success/20 text-xs font-medium px-3 py-1.5">واتساب</a>
                                        @endif
                                        @can('clients.view')
                                            <a href="{{ route('dashboard.clients.show', $c) }}" @click.stop
                                               class="rounded-full bg-primary-50 text-primary-800 hover:bg-primary-100 text-xs font-semibold px-3 py-1.5">فتح العميل</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-16 text-center text-gray-400">
                                    لا توجد طلبات مميزة — علّم «طلب مميز» عند إضافة العميل ليظهر هنا.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $clients->links() }}</div>
    </div>{{-- /منطقة النتائج --}}
</div>
@endsection
