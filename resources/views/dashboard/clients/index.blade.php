@extends('layouts.dashboard')

@section('title', 'إدارة العملاء')
@section('page-title', 'إدارة العملاء')

@php
    use App\Support\ClientFields;
    use App\Support\ClientFormData;

    $activeStage = $filters['stage_id'] ?? '';
    // رابط زر المرحلة = نفس الفلاتر الحالية مع تبديل stage_id فقط
    $stageUrl = fn($id) => route(
        'dashboard.clients.index',
        array_filter(array_merge($filters, ['stage_id' => $id]), fn($v) => $v !== null && $v !== ''),
    );

    $pill = 'inline-flex items-center gap-2 rounded-full h-9 px-3.5 text-sm font-semibold whitespace-nowrap transition';
    $pillOn = $pill . ' bg-primary-900 text-white';
    $pillOff = $pill . ' text-gray-600 hover:bg-white hover:text-ink';
    $badgeOn =
        'grid place-items-center min-w-5 h-5 px-1 rounded-full bg-white/20 text-white text-[11px] font-bold tabular-nums';
    $badgeOff =
        'grid place-items-center min-w-5 h-5 px-1 rounded-full bg-gray-200/80 text-gray-600 text-[11px] font-bold tabular-nums';

    $filterSelect =
        'w-full appearance-none rounded-field bg-white border border-gray-200 ps-3.5 pe-9 h-10 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15';
    $filterInput =
        'w-full rounded-field bg-white border border-gray-200 px-3.5 h-10 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15';
    $filterLabel = 'block text-[11px] font-semibold text-gray-500 mb-1';

    $advancedKeys = [
        'agent_id',
        'city_id',
        'area_id',
        'unit_type_id',
        'nationality',
        'social_status',
        'preferred_contact',
        'from',
        'to',
        'notes_q',
    ];
    $advancedActive = collect($filters)->only($advancedKeys)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
    $formOpen = ClientFormData::hasFormErrors($errors);
@endphp

@section('content')
    <div x-data="{
        addOpen: {{ $formOpen ? 'true' : 'false' }},
        filtersOpen: {{ $advancedActive ? 'true' : 'false' }},
        delOpen: false,
        delAction: '',
        delName: '',
        notesOpen: false,
        notesClient: null,
        targetOpen: false,
        targetName: '',
        targetUrl: '',
        targetHtml: '',
        targetLoading: false,
        copyDone: false,
        /** محتوى النافذة يُجلب عند الفتح فقط (بدل حمولة لكل عميل في الصفحة) */
        async openTargets(name, url) {
            this.targetName = name;
            this.targetUrl = url;
            this.targetOpen = true;
            await this.loadTargets();
        },
        async loadTargets() {
            this.targetLoading = true;
            this.targetHtml = '';
            try {
                const response = await fetch(this.targetUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (! response.ok) throw new Error('HTTP ' + response.status);
                this.targetHtml = await response.text();
            } catch (e) {
                this.targetHtml = '<p class=\'py-8 text-center text-sm text-danger\'>تعذّر تحميل العقارات المستهدفة.</p>';
            }
            this.targetLoading = false;
        },
        /** تغيير نتيجة المعاينة من داخل النافذة دون إغلاقها */
        async saveOutcome(event) {
            const form = event.target.closest('form');
            if (! form) return;
            this.targetLoading = true;
            try {
                await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    redirect: 'manual',
                });
            } catch (e) { /* نعيد التحميل على أي حال فيظهر الوضع الحقيقي */ }
            await this.loadTargets();
        },
        async copyClient(text) {
            try { await navigator.clipboard.writeText(text) } catch (e) {
                const area = document.createElement('textarea');
                area.value = text;
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }
            this.copyDone = true;
            setTimeout(() => this.copyDone = false, 1800);
        }
    }">
        @if (session('success'))
            <div class="mb-4 rounded-field bg-success-soft text-success text-sm px-4 py-3 flex items-center gap-2">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6 9 17l-5-5" />
                </svg>
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-field bg-danger/10 text-danger text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif
        <div x-show="copyDone" x-cloak x-transition
            class="fixed top-20 start-1/2 -translate-x-1/2 z-[70] rounded-full bg-primary-950 text-white px-5 py-2.5 text-sm shadow-xl">
            تم نسخ بيانات العميل</div>

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
                    <svg class="absolute inset-y-0 start-4 my-auto text-gray-400" width="17" height="17"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                        placeholder="بحث بالاسم أو الهاتف أو البريد..." autocomplete="off" form="clients-filters"
                        class="w-full rounded-full bg-white border border-gray-200 ps-11 pe-4 h-11 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15">
                </div>

                <button type="button" @click="filtersOpen = ! filtersOpen"
                    :class="filtersOpen ? 'bg-primary-50 border-primary-200 text-primary-800' :
                        'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                    class="inline-flex items-center gap-2 rounded-full border px-4 h-11 text-sm font-semibold transition">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" />
                    </svg>
                    فلاتر
                    @if ($advancedActive)
                        <span class="w-2 h-2 rounded-full bg-accent-500"></span>
                    @endif
                </button>

                @can('clients.create')
                    <button @click="addOpen = true"
                        class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-5 h-11 text-sm transition shrink-0">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.4" stroke-linecap="round">
                            <path d="M12 5v14M5 12h14" />
                        </svg>
                        إضافة عميل
                    </button>
                @endcan
            </div>
        </div>

        {{-- ===== الفلاتر المتقدمة (خارج منطقة النتائج حتى لا تُستبدَل مع كل فلترة) ===== --}}
        <div x-show="filtersOpen" x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="rounded-card bg-white border border-gray-100 shadow-sm p-4 mb-4" x-data="{
                city: @js((string) ($filters['city_id'] ?? '')),
                area: @js((string) ($filters['area_id'] ?? '')),
                areas: @js($areas->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'city_id' => $a->city_id])->values()),
                areasFor() { return this.city ? this.areas.filter(a => String(a.city_id) === String(this.city)) : this.areas; }
            }">
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
                <div>
                    <label class="{{ $filterLabel }}">مندوب المبيعات</label>
                    <select name="agent_id" form="clients-filters" class="{{ $filterSelect }}">
                        <option value="">كل مندوبي المبيعات</option>
                        @foreach ($agents as $u)
                            <option value="{{ $u->id }}" @selected(($filters['agent_id'] ?? '') == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $filterLabel }}">المحافظة المطلوبة</label>
                    <select name="city_id" form="clients-filters" x-model="city"
                        @change="area = ''; $refs.areaSelect.value = ''" class="{{ $filterSelect }}">
                        <option value="">كل المحافظات</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $filterLabel }}">المنطقة المطلوبة</label>
                    <select name="area_id" form="clients-filters" x-ref="areaSelect" x-model="area"
                        class="{{ $filterSelect }}">
                        <option value="">كل المناطق</option>
                        <template x-for="a in areasFor()" :key="a.id">
                            <option :value="a.id" x-text="a.name" :selected="String(a.id) === String(area)">
                            </option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="{{ $filterLabel }}">نوع الوحدة المطلوبة</label>
                    <select name="unit_type_id" form="clients-filters" class="{{ $filterSelect }}">
                        <option value="">كل الأنواع</option>
                        @foreach ($unitTypes as $type)
                            <option value="{{ $type->id }}" @selected(($filters['unit_type_id'] ?? '') == $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $filterLabel }}">الجنسية</label>
                    <input name="nationality" form="clients-filters" list="filter-nationalities"
                        value="{{ $filters['nationality'] ?? '' }}" placeholder="الكل" autocomplete="off"
                        class="{{ $filterInput }}">
                    <datalist id="filter-nationalities">
                        @foreach ($nationalities as $n)
                            <option value="{{ $n }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label class="{{ $filterLabel }}">الحالة الاجتماعية</label>
                    <select name="social_status" form="clients-filters" class="{{ $filterSelect }}">
                        <option value="">الكل</option>
                        @foreach (ClientFields::SOCIAL_STATUSES as $value => $text)
                            <option value="{{ $value }}" @selected(($filters['social_status'] ?? '') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $filterLabel }}">طريقة التواصل</label>
                    <select name="preferred_contact" form="clients-filters" class="{{ $filterSelect }}">
                        <option value="">الكل</option>
                        @foreach (ClientFields::CONTACT_METHODS as $value => $text)
                            <option value="{{ $value }}" @selected(($filters['preferred_contact'] ?? '') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $filterLabel }}">مسجّل من تاريخ</label>
                    <input name="from" form="clients-filters" data-datepicker value="{{ $filters['from'] ?? '' }}"
                        placeholder="من" class="{{ $filterInput }}">
                </div>
                <div>
                    <label class="{{ $filterLabel }}">إلى تاريخ</label>
                    <input name="to" form="clients-filters" data-datepicker value="{{ $filters['to'] ?? '' }}"
                        placeholder="إلى" class="{{ $filterInput }}">
                </div>
                <div>
                    <label class="{{ $filterLabel }}">بحث في الملاحظات</label>
                    <input type="search" name="notes_q" form="clients-filters"
                        data-min-length="{{ (int) config('clients.notes_min_length', 3) }}"
                        value="{{ $filters['notes_q'] ?? '' }}" placeholder="3 أحرف على الأقل" autocomplete="off"
                        class="{{ $filterInput }}">
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 mt-3">
                <p class="text-[11px] text-gray-400">الفلاتر تُطبَّق تلقائيًا فور الاختيار.</p>
                <a href="{{ route('dashboard.clients.index') }}" data-filters-reset="clients-filters"
                    class="text-sm text-gray-500 hover:text-danger">مسح كل الفلاتر</a>
            </div>
        </div>

        {{-- ===== منطقة النتائج: تُستبدَل وحدها عند الفلترة الحيّة ===== --}}
        <div data-results>

            {{-- صفّ المراحل --}}
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div
                    class="inline-flex items-center gap-1 rounded-full bg-gray-100/70 border border-gray-100 p-1 max-w-full overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <a href="{{ $stageUrl(null) }}" data-filter-set="stage_id" data-filter-value=""
                        class="{{ $activeStage === '' ? $pillOn : $pillOff }}">
                        كل العملاء
                        <span class="{{ $activeStage === '' ? $badgeOn : $badgeOff }}">{{ $stageCounts['total'] }}</span>
                    </a>
                    @foreach ($stages as $s)
                        @php $on = (string) $activeStage === (string) $s->id; @endphp
                        <a href="{{ $stageUrl($s->id) }}" data-filter-set="stage_id"
                            data-filter-value="{{ $s->id }}" class="{{ $on ? $pillOn : $pillOff }}">
                            {{ $s->name }}
                            <span
                                class="{{ $on ? $badgeOn : $badgeOff }}">{{ $stageCounts['stages'][$s->id] ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>

                @if (array_filter($filters, fn($v) => $v !== null && $v !== ''))
                    <a href="{{ route('dashboard.clients.index') }}"
                        class="text-sm text-gray-500 hover:text-danger whitespace-nowrap">مسح الفلاتر</a>
                @endif
            </div>

            {{-- الجدول --}}
            <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-start">
                        <thead>
                            <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                                <th class="text-start font-medium px-4 py-3 w-12">#</th>
                                <th class="text-start font-medium px-4 py-3">العميل</th>
                                <th class="text-start font-medium px-4 py-3">الهاتف</th>
                                <th class="text-start font-medium px-4 py-3">العقار المستهدف</th>
                                <th class="text-start font-medium px-4 py-3">مندوب المبيعات</th>
                                <th class="text-start font-medium px-4 py-3">المرحلة</th>
                                <th class="text-start font-medium px-4 py-3">الملاحظات</th>
                                <th class="text-start font-medium px-4 py-3">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse ($clients as $c)
                                @php
                                    $firstViewing = $c->viewings->first();
                                @endphp
                                {{-- النقر على الصف كله يفتح العميل --}}
                                <tr class="hover:bg-gray-50/50 transition cursor-pointer"
                                    @click="window.location = '{{ route('dashboard.clients.show', $c) }}'">
                                    <td class="px-4 py-3 text-gray-400 tabular-nums">
                                        {{ $clients->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="grid place-items-center w-9 h-9 rounded-full bg-primary-100 text-primary-700 font-bold">{{ mb_substr($c->name, 0, 1) }}</span>
                                            <span class="min-w-0">
                                                <span
                                                    class="block font-semibold text-ink truncate">{{ $c->name }}</span>
                                                <span class="block text-xs text-gray-400 truncate"><span
                                                        dir="ltr">{{ $c->email ?: '—' }}</span></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><span
                                            dir="ltr">{{ $c->full_phone ?: '—' }}</span></td>
                                    <td class="px-4 py-3 max-w-[230px]">
                                        <button type="button"
                                            @click.stop="openTargets(@js($c->name), @js(route('dashboard.clients.viewings', $c)))"
                                            class="w-full text-start rounded-xl px-2.5 py-2 hover:bg-primary-50 transition">
                                            @if ($firstViewing)
                                                <span class="flex items-center gap-2">
                                                    <span class="font-semibold text-ink"
                                                        dir="ltr">{{ $firstViewing->property?->reference_code ?: '—' }}</span>
                                                    @if ($c->viewings->count() > 1)
                                                        <span
                                                            class="rounded-full bg-primary-100 text-primary-700 text-[11px] font-bold px-2 py-0.5">+{{ $c->viewings->count() - 1 }}</span>
                                                    @endif
                                                </span>
                                                <span
                                                    class="block text-xs text-gray-500 truncate">{{ $firstViewing->property?->title }}</span>
                                                <span class="block text-[11px] mt-0.5"><span
                                                        class="rounded-full px-2 py-0.5 {{ ClientFields::outcomeTone($firstViewing->outcome) }}">{{ ClientFields::outcomeLabel($firstViewing->outcome) }}</span></span>
                                            @else
                                                <span class="block text-gray-400">لا يوجد عقار مستهدف</span>
                                                @if ($c->needs->isNotEmpty())
                                                    <span
                                                        class="block text-[11px] text-primary-600 mt-0.5 truncate">{{ $c->needs->first()->describe() }}</span>
                                                @endif
                                            @endif
                                        </button>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $c->agent?->name ?: '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if ($c->stage)
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap"
                                                style="color: {{ $c->stage->color }}; background-color: {{ $c->stage->color }}1a;">
                                                <span class="w-1.5 h-1.5 rounded-full"
                                                    style="background-color: {{ $c->stage->color }}"></span>
                                                {{ $c->stage->name }}
                                            </span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 max-w-[230px]">
                                        <button type="button"
                                            @click.stop="notesClient = @js([
    'name' => $c->name,
    'notes' => $c->notes,
    'interactions' => $c->interactions
        ->map(
            fn($interaction) => [
                'type' => ClientFields::enumLabel('type', $interaction->type),
                'notes' => $interaction->notes,
                'stage' => $interaction->stage?->name,
                'user' => $interaction->user?->name,
                'date' => $interaction->occurred_at?->format('Y-m-d H:i'),
            ],
        )
        ->values(),
]); notesOpen = true"
                                            class="w-full text-start rounded-xl px-2.5 py-2 hover:bg-primary-50 transition">
                                            <span
                                                class="block truncate text-gray-600">{{ $c->notes ?: 'فتح سجل التواصل' }}</span>
                                            <span
                                                class="block text-[11px] text-primary-600 mt-0.5">{{ $c->interactions->count() }}
                                                تواصل مسجل</span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1">
                                            <button type="button" @click.stop="copyClient(@js($c->shareText()))"
                                                class="grid place-items-center w-8 h-8 rounded-full text-primary-700 hover:bg-primary-100 transition"
                                                title="نسخ كل بيانات العميل">
                                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <rect x="9" y="9" width="13" height="13" rx="2" />
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                                </svg>
                                            </button>
                                            @can('clients.delete')
                                                <button
                                                    @click.stop="delOpen = true; delAction = '{{ route('dashboard.clients.destroy', $c) }}'; delName = @js($c->name)"
                                                    class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition"
                                                    title="حذف">
                                                    <x-icon.trash />
                                                </button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-16 text-center text-gray-400">لا يوجد عملاء مطابقون.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $clients->links() }}</div>
        </div>{{-- /منطقة النتائج --}}

        {{-- ===== مودال إضافة عميل ===== --}}
        <div x-show="addOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog"
            @keydown.escape.window="addOpen = false">
            <div class="absolute inset-0 bg-primary-950/50" @click="addOpen = false"></div>
            <div class="relative w-full max-w-6xl bg-white rounded-card shadow-2xl max-h-[90vh] overflow-y-auto"
                x-transition.opacity>
                <div
                    class="sticky top-0 z-10 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-ink">إضافة عميل جديد</h3>
                    <button @click="addOpen = false" class="text-gray-400 hover:text-gray-700">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <form method="POST" action="{{ route('dashboard.clients.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="p-6">
                        @include('dashboard.clients._form', ['client' => null, 'form' => $form])
                    </div>
                    <div
                        class="sticky bottom-0 flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/95">
                        <button type="button" @click="addOpen = false"
                            class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                        <button type="submit"
                            class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">إضافة
                            العميل</button>
                    </div>
                </form>
            </div>
        </div>

        @include('dashboard.viewings._wa-modal')

        {{-- ===== العقارات المستهدفة (المعاينات) ===== --}}
        <div x-show="targetOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog"
            @keydown.escape.window="targetOpen = false">
            <div class="absolute inset-0 bg-primary-950/50" @click="targetOpen = false"></div>
            <div class="relative w-full max-w-2xl bg-white rounded-card shadow-2xl max-h-[85vh] overflow-y-auto">
                <div
                    class="sticky top-0 bg-white z-10 flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="font-bold text-ink">العقارات المستهدفة</h3>
                        <p class="text-xs text-gray-400" x-text="targetName"></p>
                    </div>
                    <button type="button" @click="targetOpen = false"
                        class="grid place-items-center w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500"><svg
                            width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg></button>
                </div>
                <div class="p-6">
                    <div x-show="targetLoading" class="py-10 flex items-center justify-center">
                        <span class="w-7 h-7 rounded-full border-2 border-primary-200 border-t-primary-800 animate-spin"></span>
                    </div>
                    <div x-show="! targetLoading" x-html="targetHtml"></div>
                </div>
            </div>
        </div>

        {{-- سجل الملاحظات والتواصل --}}
        <div x-show="notesOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog"
            @keydown.escape.window="notesOpen = false">
            <div class="absolute inset-0 bg-primary-950/50" @click="notesOpen = false"></div>
            <div class="relative w-full max-w-2xl bg-white rounded-card shadow-2xl max-h-[85vh] overflow-y-auto">
                <div
                    class="sticky top-0 bg-white z-10 flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div>
                        <h3 class="font-bold text-ink">سجل التواصل</h3>
                        <p class="text-xs text-gray-400" x-text="notesClient?.name"></p>
                    </div>
                    <button type="button" @click="notesOpen = false"
                        class="grid place-items-center w-9 h-9 rounded-full hover:bg-gray-100 text-gray-500"><svg
                            width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg></button>
                </div>
                <div class="p-6">
                    <div class="rounded-2xl bg-gray-50 px-4 py-3 mb-5">
                        <p class="text-xs text-gray-400 mb-1">ملاحظات العميل</p>
                        <p class="text-sm text-ink whitespace-pre-line"
                            x-text="notesClient?.notes || 'لا توجد ملاحظات عامة.'"></p>
                    </div>
                    <div class="space-y-3">
                        <template x-for="(item, index) in (notesClient?.interactions || [])" :key="index">
                            <article class="rounded-2xl border border-gray-100 p-4">
                                <div class="flex items-center justify-between gap-3 mb-2">
                                    <span class="font-semibold text-sm text-ink" x-text="item.type"></span>
                                    <span class="text-xs text-gray-400" dir="ltr" x-text="item.date"></span>
                                </div>
                                <p class="text-sm text-gray-600 whitespace-pre-line"
                                    x-text="item.notes || 'بدون ملاحظات'"></p>
                                {{-- محاذاة لليسار (المستند RTL) بطلب المستخدم --}}
                                <p class="text-xs text-primary-600 mt-2 text-left"><span
                                        x-text="item.stage || 'لم تتغير الحالة'"></span><span x-show="item.user"> · بواسطة
                                        <span x-text="item.user"></span></span></p>
                            </article>
                        </template>
                        <p x-show="!(notesClient?.interactions || []).length"
                            class="py-8 text-center text-sm text-gray-400">لا يوجد تواصل مسجل مع هذا العميل حتى الآن.</p>
                    </div>
                </div>
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
                <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف العميل "<span x-text="delName"
                        class="font-semibold text-ink"></span>"؟ لا يمكن التراجع.</p>
                <form :action="delAction" method="POST" class="flex items-center justify-center gap-3">
                    @csrf @method('DELETE')
                    <button type="button" @click="delOpen = false"
                        class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100 border border-gray-200">إلغاء</button>
                    <button type="submit"
                        class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم،
                        احذف</button>
                </form>
            </div>
        </div>
    </div>
@endsection
