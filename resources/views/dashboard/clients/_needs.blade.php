{{-- أسطر احتياج العقار: نوع العقار + نوع الوحدة (يتبع نوع العقار) + المحافظة + المنطقة (تعتمد على المحافظة) + المساحة + عدد الغرف --}}
<div x-data="clientNeeds({ rows: @js($form['needs']), cities: @js($form['cities']), areas: @js($form['areas']), unitTypes: @js($form['unitTypes']), errors: @js($rowErrors) })" class="space-y-3">
    <template x-for="(row, i) in rows" :key="row._key">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_0.75fr_0.75fr_auto] gap-3 items-start rounded-2xl border p-3"
             :class="hasErrors(i) ? 'border-danger/40 bg-danger/5' : 'border-gray-100 bg-gray-50/60'">
            <input type="hidden" :name="name(i, 'id')" :value="row.id">

            <div>
                <label class="{{ $label }}">نوع العقار</label>
                <select :name="name(i, 'category')" x-model="row.category" @change="onCategoryChange(row)" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach (\App\Models\UnitType::CATEGORIES as $key => $text)<option value="{{ $key }}">{{ $text }}</option>@endforeach
                </select>
                <p x-show="errorFor(i, 'category')" x-text="errorFor(i, 'category')" class="mt-1 text-xs text-danger"></p>
            </div>

            <div>
                <label class="{{ $label }}">نوع الوحدة</label>
                <select :name="name(i, 'unit_type_id')" x-model="row.unit_type_id" @change="onUnitTypeChange(row)" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    <template x-for="type in unitTypesFor(row)" :key="type.id">
                        <option :value="type.id" x-text="type.name" :selected="String(type.id) === String(row.unit_type_id)"></option>
                    </template>
                </select>
                <p x-show="errorFor(i, 'unit_type_id')" x-text="errorFor(i, 'unit_type_id')" class="mt-1 text-xs text-danger"></p>
            </div>

            <div>
                <label class="{{ $label }}">المحافظة</label>
                <select :name="name(i, 'city_id')" x-model="row.city_id" @change="onCityChange(row)" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    @foreach ($form['cities'] as $city)<option value="{{ $city['id'] }}">{{ $city['name'] }}</option>@endforeach
                </select>
                <p x-show="errorFor(i, 'city_id')" x-text="errorFor(i, 'city_id')" class="mt-1 text-xs text-danger"></p>
            </div>

            <div>
                <label class="{{ $label }}">المنطقة</label>
                <select :name="name(i, 'area_id')" x-model="row.area_id" @change="onAreaChange(row)" class="{{ $field }}">
                    <option value="">— اختر —</option>
                    <template x-for="area in areasFor(row)" :key="area.id">
                        <option :value="area.id" x-text="area.name" :selected="String(area.id) === String(row.area_id)"></option>
                    </template>
                </select>
                <p x-show="errorFor(i, 'area_id')" x-text="errorFor(i, 'area_id')" class="mt-1 text-xs text-danger"></p>
            </div>

            <div>
                <label class="{{ $label }}">المساحة (م²)</label>
                <input type="number" min="0" step="0.5" inputmode="decimal" :name="name(i, 'area_size')" x-model="row.area_size" placeholder="مثال 120" class="{{ $field }}" dir="ltr">
                <p x-show="errorFor(i, 'area_size')" x-text="errorFor(i, 'area_size')" class="mt-1 text-xs text-danger"></p>
            </div>

            <div>
                <label class="{{ $label }}">عدد الغرف</label>
                <input type="number" min="0" max="50" step="1" inputmode="numeric" :name="name(i, 'rooms')" x-model="row.rooms" placeholder="مثال 3" class="{{ $field }}" dir="ltr">
                <p x-show="errorFor(i, 'rooms')" x-text="errorFor(i, 'rooms')" class="mt-1 text-xs text-danger"></p>
            </div>

            <button type="button" @click="remove(i)" title="حذف السطر"
                    class="lg:mt-7 grid place-items-center w-9 h-9 rounded-full text-danger hover:bg-danger/10 transition justify-self-end">
                <x-icon.trash />
            </button>
        </div>
    </template>

    <p x-show="! rows.length" class="text-sm text-gray-400">لم تُضف احتياجات بعد.</p>

    <button type="button" @click="add()"
            class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-primary-300 text-primary-700 hover:bg-primary-50 px-4 py-2 text-sm font-semibold transition">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        إضافة احتياج
    </button>
</div>
