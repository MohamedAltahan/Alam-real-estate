@extends('layouts.dashboard')

@section('title', 'ميداني')
@section('page-title', 'ميداني')

@php
    use App\Support\FieldOwnerFields;

    $filterSelect = 'w-full rounded-field bg-white border border-gray-200 ps-3.5 pe-9 h-10 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15';
    $filterInput = 'w-full rounded-field bg-white border border-gray-200 px-3.5 h-10 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15';
    $filterLabel = 'block text-[11px] font-semibold text-gray-500 mb-1';

    $advancedKeys = ['city_id', 'area_id', 'contact_method', 'created_by', 'from', 'to'];
    $advancedActive = collect($filters)->only($advancedKeys)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $anyFilter = collect($filters)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

    $activeStage = (string) ($filters['stage'] ?? '');
    $stageUrl = fn ($stage) => route('dashboard.field-owners.index', array_filter(['stage' => $stage] + $filters, fn ($v) => $v !== null && $v !== ''));

    $pill = 'inline-flex items-center gap-2 rounded-full h-9 px-4 text-sm font-semibold whitespace-nowrap transition';
    $pillOn = $pill.' bg-primary-900 text-white';
    $pillOff = $pill.' text-gray-600 hover:bg-white hover:text-ink';
    $badgeOn = 'grid place-items-center min-w-5 h-5 px-1.5 rounded-full bg-white/20 text-white text-[11px] font-bold tabular-nums';
    $badgeOff = 'grid place-items-center min-w-5 h-5 px-1.5 rounded-full bg-gray-200 text-gray-600 text-[11px] font-bold tabular-nums';
@endphp

@section('content')
<div x-data="{
        filtersOpen: {{ $advancedActive ? 'true' : 'false' }},
        delAction: '', delName: '',
        startDelete(action, name) { this.delAction = action; this.delName = name; this.$dispatch('open-modal', 'field-owner-delete'); },
     }">
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">ميداني</h2>
            <p class="text-sm text-gray-500">{{ number_format($records->total()) }} زيارة لملاك عقارات جدد</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            {{-- نموذج الفلترة الموحّد: كل عناصره تنضمّ له بالخاصية form="field-filters" --}}
            <form method="GET" id="field-filters" data-live-filters>
                <input type="hidden" name="stage" value="{{ $activeStage }}">
            </form>

            <div class="relative w-[260px] max-w-[55vw]">
                <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالاسم أو الهاتف أو رقم العقار..." autocomplete="off" form="field-filters"
                       class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
            </div>

            <button type="button" @click="filtersOpen = ! filtersOpen"
                    :class="filtersOpen ? 'bg-primary-50 border-primary-200 text-primary-800' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="inline-flex items-center gap-2 rounded-full border px-4 h-11 text-sm font-semibold transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                فلاتر
                @if ($advancedActive)<span class="w-2 h-2 rounded-full bg-accent-500"></span>@endif
            </button>

            @can('field_owners.create')
                <a href="{{ route('dashboard.field-owners.create') }}" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-5 h-11 text-sm transition shrink-0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    إضافة زيارة
                </a>
            @endcan
        </div>
    </div>

    {{-- ===== الفلاتر المتقدمة (خارج منطقة النتائج) ===== --}}
    <div x-show="filtersOpen" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0 -translate-y-2"
         class="rounded-card bg-white border border-gray-100 shadow-sm p-4 mb-4"
         x-data="{
            city: @js((string) ($filters['city_id'] ?? '')),
            area: @js((string) ($filters['area_id'] ?? '')),
            areas: @js($areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'city_id' => $a->city_id])->values()),
            areasFor() { return this.city ? this.areas.filter(a => String(a.city_id) === String(this.city)) : this.areas; }
         }">
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            <div>
                <label class="{{ $filterLabel }}">المحافظة</label>
                <select name="city_id" form="field-filters" x-model="city" @change="area = ''; $refs.areaSelect.value = ''" class="{{ $filterSelect }}">
                    <option value="">كل المحافظات</option>
                    @foreach ($cities as $city)<option value="{{ $city->id }}">{{ $city->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="{{ $filterLabel }}">المنطقة</label>
                <select name="area_id" form="field-filters" x-ref="areaSelect" x-model="area" class="{{ $filterSelect }}">
                    <option value="">كل المناطق</option>
                    <template x-for="a in areasFor()" :key="a.id">
                        <option :value="a.id" x-text="a.name" :selected="String(a.id) === String(area)"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="{{ $filterLabel }}">طريقة التواصل</label>
                <select name="contact_method" form="field-filters" class="{{ $filterSelect }}">
                    <option value="">الكل</option>
                    @foreach (FieldOwnerFields::CONTACT_METHODS as $value => $text)
                        <option value="{{ $value }}" @selected(($filters['contact_method'] ?? '') === $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="{{ $filterLabel }}">المندوب الميداني</label>
                <select name="created_by" form="field-filters" class="{{ $filterSelect }}">
                    <option value="">كل المندوبين</option>
                    @foreach ($reps as $u)<option value="{{ $u->id }}" @selected(($filters['created_by'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="{{ $filterLabel }}">من تاريخ</label>
                <input name="from" form="field-filters" data-datepicker value="{{ $filters['from'] ?? '' }}" placeholder="من" class="{{ $filterInput }}">
            </div>
            <div>
                <label class="{{ $filterLabel }}">إلى تاريخ</label>
                <input name="to" form="field-filters" data-datepicker value="{{ $filters['to'] ?? '' }}" placeholder="إلى" class="{{ $filterInput }}">
            </div>
        </div>
        <div class="flex justify-end mt-3">
            <a href="{{ route('dashboard.field-owners.index') }}" data-filters-reset="field-filters" class="text-sm text-gray-500 hover:text-danger">مسح كل الفلاتر</a>
        </div>
    </div>

    {{-- ===== منطقة النتائج: تُستبدَل وحدها عند الفلترة الحيّة ===== --}}
    <div data-results>
        {{-- صفّ المراحل --}}
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="inline-flex items-center gap-1 rounded-full bg-gray-100/70 border border-gray-100 p-1 max-w-full overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <a href="{{ $stageUrl(null) }}" data-filter-set="stage" data-filter-value="" class="{{ $activeStage === '' ? $pillOn : $pillOff }}">
                    كل الزيارات
                    <span class="{{ $activeStage === '' ? $badgeOn : $badgeOff }}">{{ $stageCounts['total'] }}</span>
                </a>
                @foreach (FieldOwnerFields::STAGES as $key => $text)
                    @php $on = $activeStage === $key; @endphp
                    <a href="{{ $stageUrl($key) }}" data-filter-set="stage" data-filter-value="{{ $key }}" class="{{ $on ? $pillOn : $pillOff }}">
                        {{ $text }}
                        <span class="{{ $on ? $badgeOn : $badgeOff }}">{{ $stageCounts['stages'][$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>

            @if ($anyFilter)
                <a href="{{ route('dashboard.field-owners.index') }}" class="text-sm text-gray-500 hover:text-danger whitespace-nowrap">مسح الفلاتر</a>
            @endif
        </div>

        {{-- الجدول --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 w-12">#</th>
                            <th class="text-start font-medium px-4 py-3">المسؤول</th>
                            <th class="text-start font-medium px-4 py-3">الهاتف</th>
                            <th class="text-start font-medium px-4 py-3">رقم العقار</th>
                            <th class="text-start font-medium px-4 py-3">المنطقة</th>
                            <th class="text-start font-medium px-4 py-3">المرحلة</th>
                            <th class="text-start font-medium px-4 py-3">طريقة التواصل</th>
                            <th class="text-start font-medium px-4 py-3">المندوب</th>
                            <th class="text-start font-medium px-4 py-3">التاريخ</th>
                            <th class="text-start font-medium px-4 py-3">التحويل</th>
                            <th class="text-start font-medium px-4 py-3">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($records as $r)
                            @php
                                $first = $r->contacts->first();
                                $extra = max(0, $r->contacts->count() - 1);
                            @endphp
                            {{-- النقر على الصف كله يفتح الزيارة --}}
                            <tr class="hover:bg-gray-50/50 transition cursor-pointer"
                                @click="window.location = '{{ route('dashboard.field-owners.show', $r) }}'">
                                <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $records->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="grid place-items-center w-9 h-9 rounded-full bg-accent-100 text-accent-700 font-bold">{{ mb_substr($r->name, 0, 1) }}</span>
                                        <div class="min-w-0">
                                            <span class="block font-semibold text-ink truncate">{{ $r->name }}</span>
                                            <span class="block text-xs text-gray-400 truncate">
                                                {{ $first?->role ?: '—' }}
                                                @if ($extra)<span class="ms-1 inline-flex items-center rounded-full bg-primary-50 text-primary-700 px-2 py-0.5 text-[11px] font-bold" title="مسؤولون إضافيون">+{{ $extra }}</span>@endif
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><span dir="ltr">{{ $r->full_phone ?: '—' }}</span></td>
                                <td class="px-4 py-3 text-gray-600"><span dir="ltr">{{ $r->property_number ?: '—' }}</span></td>
                                <td class="px-4 py-3 text-gray-600">{{ collect([$r->area?->name, $r->city?->name])->filter()->implode(' — ') ?: '—' }}</td>
                                <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap {{ FieldOwnerFields::stageTone($r->stage) }}">{{ FieldOwnerFields::stageLabel($r->stage) }}</span></td>
                                <td class="px-4 py-3 text-gray-600">{{ FieldOwnerFields::methodLabel($r->contact_method) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $r->creator?->name ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $r->created_at->format('Y/m/d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @if ($r->isOwnerConverted())
                                            <span title="تم الحفظ كمالك" class="grid place-items-center w-7 h-7 rounded-full bg-success-soft text-success"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="4.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg></span>
                                        @endif
                                        @if ($r->isPropertyConverted())
                                            <span title="تم الحفظ كعقار" class="grid place-items-center w-7 h-7 rounded-full bg-success-soft text-success"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/></svg></span>
                                        @endif
                                        @if (! $r->isOwnerConverted() && ! $r->isPropertyConverted())
                                            <span class="text-xs text-gray-300">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('dashboard.field-owners.show', $r) }}" @click.stop class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="عرض الزيارة"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                        @can('field_owners.edit')
                                            <a href="{{ route('dashboard.field-owners.edit', $r) }}" @click.stop class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="تعديل">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                            </a>
                                        @endcan
                                        @can('field_owners.delete')
                                            <button type="button" @click.stop="startDelete('{{ route('dashboard.field-owners.destroy', $r) }}', @js($r->name))"
                                                    class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف">
                                                <x-icon.trash />
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-4 py-16 text-center text-gray-400">لا توجد زيارات ميدانية بعد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $records->links() }}</div>
    </div>{{-- /منطقة النتائج --}}

    @can('field_owners.delete')
        <x-modal name="field-owner-delete" maxWidth="md">
            <div class="p-6 text-center">
                <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
                <h3 class="font-bold text-ink mb-1">حذف الزيارة</h3>
                <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف زيارة "<span x-text="delName" class="font-semibold text-ink"></span>"؟</p>
                <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                    @csrf @method('DELETE')
                    <button type="button" @click="$dispatch('close-modal', 'field-owner-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                    <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
                </form>
            </div>
        </x-modal>
    @endcan
</div>
@endsection
