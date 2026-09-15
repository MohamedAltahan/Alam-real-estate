@extends('layouts.dashboard')

@section('title', 'العقارات')
@section('page-title', 'العقارات')

@php
    use App\Models\PublishingChannel;

    // قنوات النشر لمودال «المواقع» و«السوشال ميديا»
    $channelPayload = collect($channels)->map(fn ($list) => $list->map(fn ($c) => [
        'id' => $c->id, 'name' => $c->name, 'url' => $c->url, 'icon' => $c->icon_url,
    ])->values());

    $kindIcons = [
        PublishingChannel::KIND_WEBSITE => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/>',
        PublishingChannel::KIND_SOCIAL => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/>',
    ];
@endphp

@section('content')
<div x-data="propertyChannels(@js($channelPayload))">
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
    <x-filter-bar id="properties-filters" cols="xl:grid-cols-5"
                  :reset="array_filter($filters) ? route('dashboard.properties.index') : null">
        <x-filter-input label="بحث" name="search" :value="$filters['search'] ?? ''" type="search" search
                        placeholder="باسم أو رقم العقار..." span="col-span-2 md:col-span-1" />
        <x-filter-select label="الغرض" name="purpose" placeholder="بيع وإيجار"
                         :options="['sale' => 'بيع', 'rent' => 'إيجار']" :selected="$filters['purpose'] ?? null" />
        <x-filter-select label="الحالة" name="status_id" placeholder="كل الحالات"
                         :options="$statuses->pluck('name', 'id')" :selected="$filters['status_id'] ?? null" />
        <x-filter-select label="نوع الوحدة" name="unit_type_id" placeholder="كل الأنواع"
                         :options="$unitTypes->pluck('name', 'id')" :selected="$filters['unit_type_id'] ?? null" />
        {{-- المنطقة تتبع المحافظة — نطاق Alpine واحد بلا كسر الشبكة (display:contents) --}}
        <div class="contents" x-data="{
                city: @js((string) ($filters['city_id'] ?? '')),
                area: @js((string) ($filters['area_id'] ?? '')),
                areas: @js($areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'city_id' => $a->city_id])->values()),
                areasFor() { return this.city ? this.areas.filter(a => String(a.city_id) === String(this.city)) : this.areas; }
             }">
            <x-filter-select label="المحافظة" name="city_id" placeholder="كل المحافظات"
                             :options="$cities->pluck('name', 'id')" x-model="city" @change="area = ''; $refs.areaSelect.value = ''" />
            <x-filter-select label="المنطقة" name="area_id" placeholder="كل المناطق" x-ref="areaSelect" x-model="area">
                <template x-for="a in areasFor()" :key="a.id">
                    <option :value="a.id" x-text="a.name" :selected="String(a.id) === String(area)"></option>
                </template>
            </x-filter-select>
        </div>
        <x-filter-select label="المواقع الإلكترونية" name="website_id" placeholder="كل المواقع"
                         :options="$channels[\App\Models\PublishingChannel::KIND_WEBSITE]->pluck('name', 'id')" :selected="$filters['website_id'] ?? null" />
        <x-filter-select label="السوشال ميديا" name="social_id" placeholder="كل القنوات"
                         :options="$channels[\App\Models\PublishingChannel::KIND_SOCIAL]->pluck('name', 'id')" :selected="$filters['social_id'] ?? null" />
        <x-filter-input label="مُضاف من تاريخ" name="from" :value="$filters['from'] ?? ''" datepicker placeholder="من" />
        <x-filter-input label="إلى تاريخ" name="to" :value="$filters['to'] ?? ''" datepicker placeholder="إلى" />
    </x-filter-bar>

    <div data-results>
    <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                        <th class="text-start font-medium px-4 py-3 w-12">#</th>
                        <th class="text-start font-medium px-4 py-3">العقار</th>
                        <th class="text-start font-medium px-4 py-3">النوع / المنطقة</th>
                        <th class="text-start font-medium px-4 py-3">السعر</th>
                        <th class="text-start font-medium px-4 py-3">الحالة</th>
                        <th class="text-start font-medium px-4 py-3">مندوب المبيعات</th>
                        <th class="text-center font-medium px-3 py-3">المواقع</th>
                        <th class="text-center font-medium px-3 py-3">السوشال ميديا</th>
                        <th class="text-start font-medium px-4 py-3">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($properties as $p)
                        @php
                            $linked = $p->channels->mapWithKeys(fn ($c) => [$c->id => $c->pivot->url])->all();
                            $payload = [
                                'id' => $p->id,
                                'reference' => $p->reference_code,
                                'title' => $p->title,
                                'action' => route('dashboard.properties.channels.update', $p),
                                'selected' => (object) $linked,
                            ];
                            $counts = [
                                PublishingChannel::KIND_WEBSITE => $p->channels->where('kind', PublishingChannel::KIND_WEBSITE)->count(),
                                PublishingChannel::KIND_SOCIAL => $p->channels->where('kind', PublishingChannel::KIND_SOCIAL)->count(),
                            ];
                        @endphp
                        {{-- النقر على الصف كله يفتح العقار --}}
                        <tr class="hover:bg-gray-50/50 cursor-pointer" @click="window.location = '{{ route('dashboard.properties.show', $p) }}'">
                            <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $properties->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-11 h-11 rounded-lg bg-gray-100 overflow-hidden shrink-0 grid place-items-center text-gray-300">
                                        @if ($p->cover_url)<img src="{{ $p->cover_url }}" class="w-full h-full object-cover" alt="">
                                        @else <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/></svg>@endif
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block font-semibold text-ink truncate">{{ $p->title }}</span>
                                        <span class="block text-xs text-gray-400"><span dir="ltr">#{{ $p->reference_code }}</span>@if ($p->is_furnished) · مفروشة @endif</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $p->unitType?->name }} · {{ $p->area?->name }}@if ($p->city) <span class="text-gray-400 text-xs">({{ $p->city->name }})</span>@endif</td>
                            <td class="px-4 py-3 text-ink font-semibold tabular-nums">{{ number_format($p->price, 3) }} {{ auth()->user()->currencySymbol() }}<span class="text-xs text-gray-400 font-normal">{{ $p->purpose === 'rent' ? '/'.($p->price_period === 'yearly' ? 'سنة' : 'شهر') : '' }}</span></td>
                            <td class="px-4 py-3">
                                @can('properties.edit')
                                    {{-- تغيير الحالة من الجدول مباشرة (property-status.js) — النقر لا يفتح العقار --}}
                                    <select data-property-status="{{ route('dashboard.properties.status', $p) }}" @click.stop title="تغيير حالة العقار"
                                            class="appearance-none rounded-full border-0 ps-3 pe-7 py-1 text-xs font-semibold cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                            style="color: {{ $p->status?->color ?? '#6B7280' }}; background-color: {{ ($p->status?->color ?? '#6B7280') }}1a;">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status->id }}" @selected((int) $p->status_id === (int) $status->id)>{{ $status->name }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    @if ($p->status)<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap" style="color: {{ $p->status->color }}; background-color: {{ $p->status->color }}1a;"><span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $p->status->color }}"></span>{{ $p->status->name }}</span>@endif
                                @endcan
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ $p->agent?->name ?: '—' }}</td>
                            @foreach ([PublishingChannel::KIND_WEBSITE, PublishingChannel::KIND_SOCIAL] as $kind)
                                <td class="px-3 py-3 text-center">
                                    <button type="button" @click.stop='openChannels(@json($kind), @json($payload))'
                                            class="relative inline-grid place-items-center w-9 h-9 rounded-full transition {{ $counts[$kind] ? 'bg-primary-50 text-primary-700 hover:bg-primary-100' : 'text-gray-400 hover:bg-gray-100 hover:text-primary-700' }}"
                                            title="{{ PublishingChannel::kindLabel($kind) }}">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $kindIcons[$kind] !!}</svg>
                                        @if ($counts[$kind])
                                            <span class="absolute -top-1 -end-1 grid place-items-center min-w-[18px] h-[18px] px-1 rounded-full bg-accent-500 text-primary-950 text-[10px] font-bold ring-2 ring-white">{{ $counts[$kind] }}</span>
                                        @endif
                                    </button>
                                </td>
                            @endforeach
                            <td class="px-4 py-3">
                                @can('properties.delete')
                                    <button @click.stop="delAction = '{{ route('dashboard.properties.destroy', $p) }}'; delName = @js($p->reference_code); $dispatch('open-modal', 'prop-delete')" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف"><x-icon.trash /></button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-16 text-center text-gray-400">لا توجد عقارات.@can('properties.create') <a href="{{ route('dashboard.properties.create') }}" class="text-primary-700 font-medium">أضف أول عقار</a>@endcan</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $properties->links() }}</div>
    </div>{{-- /منطقة النتائج --}}

    {{-- ===== مودال قنوات النشر (مواقع / سوشال) ===== --}}
    <x-modal name="prop-channels" maxWidth="lg">
        <form :action="current.action" method="POST">
            @csrf @method('PUT')
            <input type="hidden" name="kind" :value="kind">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h3 class="font-bold text-ink" x-text="kind === 'website' ? 'المواقع الإلكترونية' : 'السوشال ميديا'"></h3>
                    <p class="text-xs text-gray-400">العقار رقم <span dir="ltr" x-text="current.reference"></span> · <span x-text="current.title"></span></p>
                </div>
                <button type="button" @click="$dispatch('close-modal', 'prop-channels')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 space-y-2 max-h-[65vh] overflow-y-auto">
                <p class="text-xs text-gray-500 mb-3">علّم القنوات التي نُشر عليها العقار وأضف رابط الإعلان لكل قناة.</p>
                <template x-for="c in list" :key="c.id">
                    <div class="rounded-2xl border p-3 transition" :class="form[c.id]?.on ? 'border-primary-200 bg-primary-50/40' : 'border-gray-100'">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" :name="'channels[' + c.id + '][on]'" value="1" x-model="form[c.id].on" @disabled(! auth()->user()->can('properties.edit'))>
                            <span class="grid place-items-center w-9 h-9 shrink-0 rounded-lg bg-white border border-gray-100 overflow-hidden">
                                <template x-if="c.icon"><img :src="c.icon" class="w-full h-full object-contain" alt=""></template>
                                <template x-if="! c.icon"><span class="text-sm font-bold text-primary-800" x-text="c.name.charAt(0)"></span></template>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-ink" x-text="c.name"></span>
                                <a x-show="c.url" :href="c.url" target="_blank" rel="noopener" @click.stop class="block text-[11px] text-gray-400 truncate hover:text-primary-700"><bdi dir="ltr" x-text="c.url"></bdi></a>
                            </span>
                        </label>
                        <div x-show="form[c.id]?.on" class="mt-2">
                            <input type="url" :name="'channels[' + c.id + '][url]'" x-model="form[c.id].url" :disabled="! form[c.id]?.on"
                                   placeholder="رابط الإعلان على هذه القناة (اختياري)" dir="ltr"
                                   class="w-full rounded-field border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 disabled:bg-gray-50"
                                   @disabled(! auth()->user()->can('properties.edit'))>
                        </div>
                    </div>
                </template>
                <p x-show="! list.length" class="text-center text-sm text-gray-400 py-8" x-text="kind === 'website' ? 'لم تُضف مواقع إلكترونية بعد — أضفها من شاشة المواقع الإلكترونية.' : 'لم تُضف قنوات سوشال ميديا بعد — أضفها من شاشة السوشال ميديا.'"></p>
            </div>
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal', 'prop-channels')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إغلاق</button>
                @can('properties.edit')
                    <button type="submit" x-show="list.length" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">حفظ</button>
                @endcan
            </div>
        </form>
    </x-modal>

    <x-modal name="prop-delete" maxWidth="md">
        <div class="p-6 text-center">
            <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
            <h3 class="font-bold text-ink mb-1">حذف العقار</h3>
            <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف العقار رقم "<span x-text="delName" class="font-semibold text-ink" dir="ltr"></span>"؟</p>
            <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                @csrf @method('DELETE')
                <button type="button" @click="$dispatch('close-modal', 'prop-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
            </form>
        </div>
    </x-modal>
</div>

<script>
    /** مودال قنوات النشر: قائمة القنوات حسب النوع + حالة كل قناة (معلَّمة/رابط) للعقار المفتوح */
    function propertyChannels(channels) {
        return {
            channels,
            kind: 'website',
            current: { id: null, reference: '', title: '', action: '', selected: {} },
            form: {},
            delAction: '',
            delName: '',

            get list() {
                return this.channels[this.kind] ?? [];
            },

            init() {
                this.resetForm({});
            },

            /** حالة كل قناة (من النوعين) حتى لا يتعطل x-model قبل فتح المودال */
            resetForm(selected) {
                const form = {};
                Object.values(this.channels).flat().forEach((c) => {
                    const on = Object.prototype.hasOwnProperty.call(selected, String(c.id));
                    form[c.id] = { on, url: on ? (selected[String(c.id)] ?? '') : '' };
                });
                this.form = form;
            },

            openChannels(kind, property) {
                this.kind = kind;
                this.current = property;
                this.resetForm(property.selected || {});
                this.$dispatch('open-modal', 'prop-channels');
            },
        };
    }
</script>
@endsection
