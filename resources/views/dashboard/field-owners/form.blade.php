@extends('layouts.dashboard')

@section('title', $record->exists ? 'تعديل زيارة ميدانية' : 'زيارة ميدانية جديدة')
@section('page-title', 'ميداني')

@php
    use App\Support\FieldOwnerFields;
    use App\Support\FieldOwnerFormData;

    $field = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
    $label = 'block text-sm font-medium text-gray-700 mb-1.5';
    $state = FieldOwnerFormData::state($record, $errors, $areas, $countries);
    $backUrl = $record->exists ? route('dashboard.field-owners.show', $record) : route('dashboard.field-owners.index');
@endphp

@section('content')
<div x-data="fieldOwnerForm(@js($state))">
    <x-flash />

    <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-700 mb-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        {{ $record->exists ? 'العودة للزيارة' : 'العودة لقائمة ميداني' }}
    </a>

    <form method="POST" action="{{ $record->exists ? route('dashboard.field-owners.update', $record) : route('dashboard.field-owners.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @if ($record->exists) @method('PUT') @endif

        <div>
            <h2 class="text-xl font-bold text-ink">{{ $record->exists ? 'تعديل الزيارة الميدانية' : 'زيارة ميدانية جديدة' }}</h2>
            <p class="text-sm text-gray-400">سجّل بيانات المالك الجديد وعقاره أثناء الزيارة، ثم احفظه كمالك وكعقار من صفحة الزيارة.</p>
        </div>

        {{-- ===== (أ) المسؤولون ===== --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="font-bold text-ink">المسؤولون <span class="text-danger">*</span></h3>
                <p class="text-xs text-gray-400">الاسم · صفته · رقم الهاتف — أول سطر هو المالك الأساسي</p>
            </div>
            @error('contacts')<p class="mb-2 text-xs text-danger">{{ $message }}</p>@enderror
            <div class="space-y-3">
                <template x-for="(row, i) in rows" :key="row._key">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1.2fr_1fr_1.4fr_auto] gap-3 items-start rounded-2xl border p-3"
                         :class="hasErrors(i) ? 'border-danger/40 bg-danger/5' : 'border-gray-100 bg-gray-50/60'">
                        <input type="hidden" :name="name(i, 'id')" :value="row.id">
                        <div>
                            <label class="{{ $label }}">الاسم <span class="text-danger">*</span></label>
                            <input :name="name(i, 'name')" x-model="row.name" class="{{ $field }}">
                            <p x-show="errorFor(i, 'name')" x-text="errorFor(i, 'name')" class="mt-1 text-xs text-danger"></p>
                        </div>
                        <div>
                            <label class="{{ $label }}">صفته</label>
                            <input :name="name(i, 'role')" x-model="row.role" placeholder="المالك · الوكيل · المدير" list="field-contact-roles" class="{{ $field }}">
                            <p x-show="errorFor(i, 'role')" x-text="errorFor(i, 'role')" class="mt-1 text-xs text-danger"></p>
                        </div>
                        <div>
                            <label class="{{ $label }}">رقم الهاتف <span class="text-danger">*</span></label>
                            <x-phone-field dynamic countries="countries" code="row.phone_code" national="row.phone"
                                           code-name="name(i, 'phone_code')" phone-name="name(i, 'phone')" :show-errors="false"
                                           x-init="$watch('code', v => row.phone_code = v); $watch('national', v => row.phone = v)" />
                            <p x-show="errorFor(i, 'phone')" x-text="errorFor(i, 'phone')" class="mt-1 text-xs text-danger"></p>
                        </div>
                        <button type="button" @click="removeContact(i)" :disabled="rows.length <= 1" title="حذف السطر"
                                class="lg:mt-7 grid place-items-center w-9 h-9 rounded-full text-danger hover:bg-danger/10 disabled:text-gray-300 disabled:hover:bg-transparent disabled:cursor-not-allowed transition justify-self-end">
                            <x-icon.trash />
                        </button>
                    </div>
                </template>
            </div>
            <datalist id="field-contact-roles">
                <option value="المالك"></option><option value="الوكيل"></option><option value="المدير"></option>
                <option value="الحارس"></option><option value="قريب المالك"></option><option value="المحامي"></option>
            </datalist>
            <button type="button" @click="add()"
                    class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-dashed border-primary-300 text-primary-700 hover:bg-primary-50 px-4 py-2 text-sm font-semibold transition">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                إضافة مسؤول
            </button>
        </div>

        {{-- ===== (ب) المتابعة ===== --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-4 sm:p-6">
            <h3 class="font-bold text-ink mb-4">المتابعة</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">طريقة التواصل <span class="text-danger">*</span></label>
                    <select name="contact_method" required class="{{ $field }}">
                        @foreach (FieldOwnerFields::CONTACT_METHODS as $value => $text)
                            <option value="{{ $value }}" @selected(old('contact_method', $record->contact_method) === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    @error('contact_method')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $label }}">المرحلة <span class="text-danger">*</span></label>
                    <select name="stage" required class="{{ $field }}">
                        @foreach (FieldOwnerFields::STAGES as $value => $text)
                            <option value="{{ $value }}" @selected(old('stage', $record->stage) === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                    @error('stage')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="{{ $label }}">ملاحظات</label>
                    <textarea name="notes" rows="3" class="{{ $field }}">{{ old('notes', $record->notes) }}</textarea>
                    @error('notes')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- ===== (ج) العقار ===== --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-4 sm:p-6">
            <h3 class="font-bold text-ink mb-4">العقار</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $label }}">المحافظة</label>
                    <select name="city_id" x-model="city" @change="onCityChange()" class="{{ $field }}">
                        <option value="">— اختر —</option>
                        @foreach ($cities as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @error('city_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $label }}">المنطقة</label>
                    <select name="area_id" x-model="area" @change="onAreaChange()" class="{{ $field }}">
                        <option value="">— اختر —</option>
                        <template x-for="a in areasFor()" :key="a.id">
                            <option :value="a.id" x-text="a.name" :selected="String(a.id) === area"></option>
                        </template>
                    </select>
                    @error('area_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $label }}">رقم العقار</label>
                    <input name="property_number" value="{{ old('property_number', $record->property_number) }}" dir="ltr" placeholder="الرقم الآلي / رقم القسيمة" class="{{ $field }} text-end">
                    @error('property_number')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $label }}">العنوان</label>
                    <input name="address" value="{{ old('address', $record->address) }}" placeholder="القطعة · الشارع · المبنى" class="{{ $field }}">
                    @error('address')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                {{-- الموقع على الخريطة --}}
                <div class="sm:col-span-2 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="{{ $label }} mb-0">الموقع على الخريطة</p>
                            <p class="text-xs text-gray-400">اضغط على الخريطة أو اسحب الدبوس، أو استخدم موقعك الحالي من الموبايل.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="locateMe()" :disabled="geoBusy"
                                    class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 disabled:opacity-60 text-white font-semibold px-4 h-10 text-sm transition">
                                <svg x-show="! geoBusy" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                                <svg x-show="geoBusy" x-cloak class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.2-8.6"/></svg>
                                موقعي الحالي
                            </button>
                            <button type="button" x-show="lat || lng" x-cloak @click="clearLocation()"
                                    class="rounded-full border border-gray-200 text-gray-600 hover:bg-gray-100 px-4 h-10 text-sm transition">مسح الموقع</button>
                        </div>
                    </div>
                    <div x-ref="map" dir="ltr" class="h-72 sm:h-80 w-full rounded-2xl border border-gray-200 overflow-hidden bg-gray-100"></div>
                    <p x-show="geoError" x-cloak x-text="geoError" class="text-xs text-danger"></p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">خط العرض</label>
                            <input name="latitude" type="number" step="any" min="-90" max="90" x-model="lat" @change="moveFromInputs()" dir="ltr" class="{{ $field }}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">خط الطول</label>
                            <input name="longitude" type="number" step="any" min="-180" max="180" x-model="lng" @change="moveFromInputs()" dir="ltr" class="{{ $field }}">
                        </div>
                    </div>
                    @error('latitude')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                    @error('longitude')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- ===== (د) الصور ===== --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-4 sm:p-6">
            <h3 class="font-bold text-ink mb-4">صور العقار</h3>
            <x-cms.dropzone label="" name="photos" remove-name="photos_removed[]" multiple :media="$record->exists ? $record->getMedia('photos') : []" />
            @error('photos')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            @foreach ($errors->get('photos.*') as $messages)<p class="mt-1 text-xs text-danger">{{ $messages[0] }}</p>@endforeach
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ $backUrl }}" class="rounded-full px-5 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</a>
            <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">{{ $record->exists ? 'حفظ التعديلات' : 'حفظ الزيارة' }}</button>
        </div>
    </form>
</div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpush

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@endpush
