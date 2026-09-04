@extends('layouts.dashboard')

@section('title', 'ملاك العقارات')
@section('page-title', 'ملاك العقارات')

@php
    use App\Support\OwnerFormData;

    $months = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    $filterSelect = 'w-full appearance-none rounded-field bg-white border border-gray-200 ps-3.5 pe-9 h-10 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15';
    $filterInput = 'w-full rounded-field bg-white border border-gray-200 px-3.5 h-10 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15';
    $filterLabel = 'block text-[11px] font-semibold text-gray-500 mb-1';

    $advancedKeys = ['city_id', 'area_id', 'agent_id', 'has_properties', 'from', 'to', 'notes_q'];
    $advancedActive = collect($filters)->only($advancedKeys)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@section('content')
<div x-data="ownerForm({
        storeUrl: @js(route('dashboard.owners.store')),
        updateBase: @js(url('dashboard/owners')),
        countries: @js($countries),
        errors: @js(OwnerFormData::errorMap($errors)),
        reopen: @js(OwnerFormData::reopen($errors)),
        filtersOpen: {{ $advancedActive ? 'true' : 'false' }},
     })">
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">ملاك العقارات</h2>
            <p class="text-sm text-gray-500">{{ number_format($owners->total()) }} مالك</p>
        </div>

        <div class="flex items-center gap-3">
            {{-- نموذج الفلترة الموحّد: كل عناصره تنضمّ له بالخاصية form="owners-filters" --}}
            <form method="GET" id="owners-filters" data-live-filters></form>

            <div class="relative w-[260px] max-w-[55vw]">
                <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="بحث بالاسم أو الهاتف أو البريد..." autocomplete="off" form="owners-filters"
                       class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
            </div>

            <button type="button" @click="filtersOpen = ! filtersOpen"
                    :class="filtersOpen ? 'bg-primary-50 border-primary-200 text-primary-800' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="inline-flex items-center gap-2 rounded-full border px-4 h-11 text-sm font-semibold transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                فلاتر
                @if ($advancedActive)<span class="w-2 h-2 rounded-full bg-accent-500"></span>@endif
            </button>

            @can('property_owners.create')
                <button type="button" @click="startAdd()" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-5 h-11 text-sm transition shrink-0">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    إضافة مالك
                </button>
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
                <select name="city_id" form="owners-filters" x-model="city" @change="area = ''; $refs.areaSelect.value = ''" class="{{ $filterSelect }}">
                    <option value="">كل المحافظات</option>
                    @foreach ($cities as $city)<option value="{{ $city->id }}">{{ $city->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="{{ $filterLabel }}">المنطقة</label>
                <select name="area_id" form="owners-filters" x-ref="areaSelect" x-model="area" class="{{ $filterSelect }}">
                    <option value="">كل المناطق</option>
                    <template x-for="a in areasFor()" :key="a.id">
                        <option :value="a.id" x-text="a.name" :selected="String(a.id) === String(area)"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="{{ $filterLabel }}">مندوب المبيعات</label>
                <select name="agent_id" form="owners-filters" class="{{ $filterSelect }}">
                    <option value="">كل مندوبي المبيعات</option>
                    @foreach ($agents as $u)<option value="{{ $u->id }}" @selected(($filters['agent_id'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="{{ $filterLabel }}">العقارات</label>
                <select name="has_properties" form="owners-filters" class="{{ $filterSelect }}">
                    <option value="">الكل</option>
                    <option value="1" @selected(($filters['has_properties'] ?? '') === '1')>يملك عقارات</option>
                    <option value="0" @selected(($filters['has_properties'] ?? '') === '0')>بدون عقارات</option>
                </select>
            </div>
            <div>
                <label class="{{ $filterLabel }}">مسجّل من تاريخ</label>
                <input name="from" form="owners-filters" data-datepicker value="{{ $filters['from'] ?? '' }}" placeholder="من" class="{{ $filterInput }}">
            </div>
            <div>
                <label class="{{ $filterLabel }}">إلى تاريخ</label>
                <input name="to" form="owners-filters" data-datepicker value="{{ $filters['to'] ?? '' }}" placeholder="إلى" class="{{ $filterInput }}">
            </div>
            <div class="col-span-2 md:col-span-3 xl:col-span-2">
                <label class="{{ $filterLabel }}">بحث في الملاحظات</label>
                <input type="search" name="notes_q" form="owners-filters" data-min-length="{{ (int) config('clients.notes_min_length', 3) }}"
                       value="{{ $filters['notes_q'] ?? '' }}" placeholder="3 أحرف على الأقل" autocomplete="off" class="{{ $filterInput }}">
            </div>
        </div>
        <div class="flex items-center justify-between gap-3 mt-3">
            <p class="text-[11px] text-gray-400">الفلاتر تُطبَّق تلقائيًا فور الاختيار.</p>
            <a href="{{ route('dashboard.owners.index') }}" data-filters-reset="owners-filters" class="text-sm text-gray-500 hover:text-danger">مسح كل الفلاتر</a>
        </div>
    </div>

    {{-- ===== منطقة النتائج ===== --}}
    <div data-results>
    <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                        <th class="text-start font-medium px-4 py-3 w-12">#</th>
                        <th class="text-start font-medium px-4 py-3">المالك</th>
                        <th class="text-start font-medium px-4 py-3">الهاتف</th>
                        <th class="text-start font-medium px-4 py-3">عدد العقارات</th>
                        <th class="text-start font-medium px-4 py-3">مندوب المبيعات</th>
                        <th class="text-start font-medium px-4 py-3">الملاحظات</th>
                        <th class="text-start font-medium px-4 py-3">تاريخ الانضمام</th>
                        <th class="text-start font-medium px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($owners as $o)
                        @php
                            $editData = OwnerFormData::editPayload($o);
                            $propertiesData = $o->properties->map(fn ($p) => OwnerFormData::propertyPayload($p))->values();
                            $extraContacts = max(0, $o->contacts->count() - 1);
                        @endphp
                        {{-- النقر على الصف كله يفتح ملف المالك --}}
                        <tr class="hover:bg-gray-50/50 transition cursor-pointer"
                            @click="window.location = '{{ route('dashboard.owners.show', $o) }}'">
                            <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $owners->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid place-items-center w-9 h-9 rounded-full bg-accent-100 text-accent-700 font-bold">{{ mb_substr($o->name, 0, 1) }}</span>
                                    <div class="min-w-0">
                                        <span class="block font-semibold text-ink truncate">{{ $o->name }}</span>
                                        <span class="block text-xs text-gray-400 truncate"><span dir="ltr">{{ $o->email ?: '—' }}</span></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                <span dir="ltr">{{ $o->full_phone ?: '—' }}</span>
                                @if ($extraContacts)
                                    <span class="ms-1 inline-flex items-center rounded-full bg-primary-50 text-primary-700 px-2 py-0.5 text-[11px] font-bold" title="أرقام إضافية">+{{ $extraContacts }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <button type="button" @click.stop='openProperties(@json($o->name), @json($propertiesData))' class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-primary-700 hover:bg-primary-50">
                                    <span class="font-bold text-ink tabular-nums">{{ $o->properties_count }}</span>
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="text-gray-400"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/></svg>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $o->latestProperty?->agent?->name ?: '—' }}</td>
                            <td class="px-4 py-3 max-w-[220px]">
                                @if (filled($o->notes))
                                    <span class="block text-xs text-gray-600 truncate" title="{{ $o->notes }}">{{ $o->notes }}</span>
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $months[(int) $o->created_at->format('n')] }} {{ $o->created_at->format('Y') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('dashboard.owners.show', $o) }}" @click.stop class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="عرض المالك"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                    @can('property_owners.edit')
                                        <button type="button" @click.stop='startEdit(@json($editData))'
                                                class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50" title="تعديل">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                        </button>
                                    @endcan
                                    @can('property_owners.delete')
                                        <button type="button" @click.stop="startDelete('{{ route('dashboard.owners.destroy', $o) }}', @js($o->name))"
                                                class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف">
                                            <x-icon.trash />
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400">لا يوجد ملّاك.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $owners->links() }}</div>
    </div>{{-- /منطقة النتائج --}}

    @include('dashboard.owners._form-modal')

    {{-- ملخص عقارات المالك --}}
    <x-modal name="owner-properties" maxWidth="3xl">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100"><div><h3 class="font-bold text-ink">عقارات المالك</h3><p class="text-xs text-gray-400" x-text="propertiesOwner"></p></div><button type="button" @click="$dispatch('close-modal', 'owner-properties')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
        <div class="p-6 max-h-[70vh] overflow-y-auto space-y-3">
            <template x-for="property in ownerProperties" :key="property.id">
                <a :href="property.url || null" :class="property.url ? 'hover:border-primary-200 hover:bg-primary-50/40' : 'cursor-default'" class="flex gap-4 rounded-2xl border border-gray-100 p-3 transition">
                    <span class="w-28 h-24 shrink-0 rounded-xl bg-gray-100 overflow-hidden grid place-items-center text-gray-300">
                        <template x-if="property.cover"><img :src="property.cover" class="w-full h-full object-cover" alt=""></template>
                        <template x-if="! property.cover"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg></template>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-3">
                            <strong class="text-ink" dir="ltr" x-text="property.reference"></strong>
                            <span class="text-xs rounded-full px-2.5 py-1 font-semibold" :style="`color:${property.status_color};background-color:${property.status_color}1a`" x-text="property.status || 'بدون حالة'"></span>
                        </span>
                        <span class="block text-sm font-semibold text-gray-700 mt-1 truncate" x-text="property.title"></span>
                        <span class="block text-xs text-gray-400 mt-1">
                            <span x-text="property.type || 'بدون نوع'"></span> · <span x-text="[property.area, property.city].filter(Boolean).join(' — ') || 'بدون منطقة'"></span> · <span x-text="property.purpose"></span>
                        </span>
                        <span class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 mt-2">
                            <span x-show="property.bedrooms !== null" x-text="property.bedrooms + ' غرف'"></span>
                            <span x-show="property.bathrooms !== null" x-text="property.bathrooms + ' حمام'"></span>
                            <span x-show="property.area_size" x-text="property.area_size + ' م²'"></span>
                            <span x-show="property.agent" x-text="'مندوب المبيعات: ' + property.agent"></span>
                            <span class="ms-auto text-sm font-bold text-primary-800" x-text="property.price"></span>
                        </span>
                    </span>
                </a>
            </template>
            <p x-show="ownerProperties.length === 0" class="text-center text-sm text-gray-400 py-10">لا توجد عقارات مسجلة لهذا المالك.</p>
        </div>
    </x-modal>
</div>
@endsection
