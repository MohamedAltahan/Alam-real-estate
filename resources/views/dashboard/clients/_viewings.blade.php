{{-- أسطر المعاينات: العقار (بحث) + الموعد (تقويم) + حضوري + النتيجة + ملاحظة --}}
<div x-data="clientViewings({ rows: @js($form['viewings']), errors: @js($rowErrors), lookupUrl: @js($form['lookupUrl']) })" class="space-y-3">
    <template x-for="(row, i) in rows" :key="row._key">
        <div class="rounded-2xl border p-3" :class="hasErrors(i) ? 'border-danger/40 bg-danger/5' : 'border-gray-100 bg-gray-50/60'">
            <input type="hidden" :name="name(i, 'id')" :value="row.id">
            <input type="hidden" :name="name(i, 'property_id')" :value="row.property_id">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-start">
                {{-- العقار: بحث بالرقم المرجعي أو الاسم --}}
                <div class="lg:col-span-4" x-data="propertyLookup({ url: lookupUrl, row })" @click.outside="open = false">
                    <label class="{{ $label }}">العقار <span class="text-danger">*</span></label>
                    <div class="relative">
                        <input x-ref="input" x-model="q" @input="search()" @keydown="onKey($event)" @blur="onBlur()"
                               @focus="q.trim().length >= 2 && results.length && (open = true)"
                               placeholder="ابحث بالرقم أو اسم العقار..." autocomplete="off" class="{{ $field }} pe-9">
                        <button type="button" x-show="row.property_id" @click="clear()" title="مسح"
                                class="absolute end-2 top-1/2 -translate-y-1/2 grid place-items-center w-6 h-6 rounded-full text-gray-400 hover:text-danger hover:bg-danger/10">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                        <svg x-show="! row.property_id && ! loading" class="absolute end-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <span x-show="loading" class="absolute end-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 rounded-full border-2 border-primary-300 border-t-primary-800 animate-spin"></span>

                        <div x-show="open" x-cloak
                             class="absolute z-40 mt-1 w-full min-w-[280px] rounded-2xl bg-white border border-gray-100 shadow-2xl p-1.5 max-h-64 overflow-y-auto">
                            <template x-for="(item, j) in results" :key="item.id">
                                <button type="button" @mousedown.prevent="pick(item)" @mouseenter="hi = j"
                                        :class="hi === j ? 'bg-primary-50' : ''"
                                        class="w-full text-start rounded-xl px-3 py-2 text-sm transition">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <strong class="text-ink" dir="ltr" x-text="item.reference_code"></strong>
                                            <span x-show="item.building" x-text="'— ' + item.building" class="text-xs text-gray-500 truncate"></span>
                                            {{-- شارة حالة العقار (غير متاح) — للعلم فقط ولا تمنع الاختيار --}}
                                            <span x-show="item.badge" x-text="item.badge" class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                                  :style="item.badge_color ? `color:${item.badge_color};background-color:${item.badge_color}1a` : ''"></span>
                                        </span>
                                        <span class="text-[11px] text-gray-400" x-text="item.area || ''"></span>
                                    </span>
                                    <span class="block text-xs text-gray-500 truncate" x-text="item.title || ''"></span>
                                </button>
                            </template>
                            <p x-show="! results.length" class="px-3 py-3 text-sm text-gray-400 text-center">لا توجد نتائج مطابقة</p>
                        </div>
                    </div>
                    <p x-show="row.property_id && ! open" class="mt-1 text-[11px] text-success font-semibold">تم اختيار <span dir="ltr" x-text="row.property_label"></span></p>
                    <p x-show="errorFor(i, 'property_id')" x-text="errorFor(i, 'property_id')" class="mt-1 text-xs text-danger"></p>
                </div>

                {{-- موعد المعاينة --}}
                <div class="lg:col-span-3">
                    <label class="{{ $label }}">موعد المعاينة <span class="text-danger">*</span></label>
                    <input x-datetime x-model="row.scheduled_at" :name="name(i, 'scheduled_at')" placeholder="اختر التاريخ والوقت" class="{{ $field }}" dir="ltr">
                    <p x-show="errorFor(i, 'scheduled_at')" x-text="errorFor(i, 'scheduled_at')" class="mt-1 text-xs text-danger"></p>
                </div>

                {{-- حضوري --}}
                <div class="lg:col-span-2">
                    <label class="{{ $label }}">حضوري</label>
                    <select :name="name(i, 'in_person')" x-model="row.in_person" class="{{ $field }}">
                        <option value="1">نعم</option>
                        <option value="0">لا</option>
                    </select>
                </div>

                {{-- النتيجة --}}
                <div class="lg:col-span-2">
                    <label class="{{ $label }}">النتيجة</label>
                    <select :name="name(i, 'outcome')" x-model="row.outcome" class="{{ $field }}">
                        @foreach (\App\Support\ClientFields::OUTCOMES as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach
                    </select>
                    <p x-show="errorFor(i, 'outcome')" x-text="errorFor(i, 'outcome')" class="mt-1 text-xs text-danger"></p>
                </div>

                <div class="lg:col-span-1 flex justify-end lg:mt-7">
                    <button type="button" @click="remove(i)" title="حذف المعاينة"
                            class="grid place-items-center w-9 h-9 rounded-full text-danger hover:bg-danger/10 transition">
                        <x-icon.trash />
                    </button>
                </div>
            </div>

            <div class="mt-3">
                <input :name="name(i, 'notes')" x-model="row.notes" placeholder="ملاحظة على المعاينة (اختياري)" class="{{ $field }}">
                <p x-show="errorFor(i, 'notes')" x-text="errorFor(i, 'notes')" class="mt-1 text-xs text-danger"></p>
            </div>
        </div>
    </template>

    <p x-show="! rows.length" class="text-sm text-gray-400">لا توجد معاينات مضافة.</p>

    <button type="button" @click="add()"
            class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-primary-300 text-primary-700 hover:bg-primary-50 px-4 py-2 text-sm font-semibold transition">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        إضافة معاينة
    </button>
</div>
