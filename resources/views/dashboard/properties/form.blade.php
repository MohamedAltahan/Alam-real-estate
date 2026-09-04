@extends('layouts.dashboard')

@section('title', $property->exists ? 'تعديل عقار' : 'إضافة عقار')
@section('page-title', 'العقارات')

@php
    $t = fn ($field, $locale) => old("$field.$locale", $property->exists ? $property->getTranslation($field, $locale, false) : '');
    $selectedAmenities = old('amenities', $property->exists ? $property->amenities->pluck('id')->all() : []);
    $inputCls = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
    $labelCls = 'block text-sm font-medium text-gray-700 mb-1.5';

    $formState = [
        'purpose' => old('purpose', $property->purpose ?? 'sale'),
        'category' => (string) old('category_id', $property->category_id ?? ''),
        'unitType' => (string) old('unit_type_id', $property->unit_type_id ?? ''),
        'city' => (string) old('city_id', $property->city_id ?? ''),
        'area' => (string) old('area_id', $property->area_id ?? ''),
        'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'key' => $c->key, 'name' => $c->name])->values(),
        'unitTypes' => $unitTypes->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'category' => $u->category])->values(),
        'areas' => $areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'city_id' => $a->city_id])->values(),
    ];
@endphp

@section('content')
<div x-data="propertyForm(@js($formState))">
    <x-flash />

    <a href="{{ route('dashboard.properties.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-700 mb-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg> العودة للعقارات
    </a>

    <form method="POST" action="{{ $property->exists ? route('dashboard.properties.update', $property) : route('dashboard.properties.store') }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @if ($property->exists) @method('PUT') @endif

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-ink">{{ $property->exists ? 'تعديل العقار' : 'إضافة عقار' }}</h2>
                <p class="text-sm text-gray-400">الرقم المرجعي: <span dir="ltr" class="font-semibold text-ink tabular-nums">{{ $property->reference_code ?? ($nextCode ?? '') }}</span></p>
            </div>
        </div>

        {{-- ===== البيانات الأساسية ===== --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-ink mb-4">بيانات العقار</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelCls }}">العنوان (عربي) <span class="text-danger">*</span></label>
                    <input name="title[ar]" value="{{ $t('title', 'ar') }}" required class="{{ $inputCls }}">
                    @error('title.ar')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">العنوان (English)</label>
                    <input name="title[en]" value="{{ $t('title', 'en') }}" dir="ltr" class="{{ $inputCls }}">
                </div>

                {{-- التصنيف → نوع الوحدة --}}
                <div>
                    <label class="{{ $labelCls }}">التصنيف <span class="text-danger">*</span></label>
                    <select name="category_id" x-model="category" @change="onCategoryChange()" required class="{{ $inputCls }}">
                        <option value="">— اختر —</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @error('category_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">نوع الوحدة <span class="text-danger">*</span></label>
                    <select name="unit_type_id" x-model="unitType" required class="{{ $inputCls }}">
                        <option value="">— اختر —</option>
                        <template x-for="type in unitTypesFor()" :key="type.id">
                            <option :value="type.id" x-text="type.name" :selected="String(type.id) === unitType"></option>
                        </template>
                    </select>
                    <p x-show="! category" class="mt-1 text-[11px] text-gray-400">اختر التصنيف أولًا لعرض أنواع الوحدات المناسبة.</p>
                    @error('unit_type_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                {{-- المحافظة → المنطقة --}}
                <div>
                    <label class="{{ $labelCls }}">المحافظة</label>
                    <select name="city_id" x-model="city" @change="onCityChange()" class="{{ $inputCls }}">
                        <option value="">— كل المحافظات —</option>
                        @foreach ($cities as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @error('city_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">المنطقة <span class="text-danger">*</span></label>
                    <select name="area_id" x-model="area" @change="onAreaChange()" required class="{{ $inputCls }}">
                        <option value="">— اختر —</option>
                        <template x-for="a in areasFor()" :key="a.id">
                            <option :value="a.id" x-text="a.name" :selected="String(a.id) === area"></option>
                        </template>
                    </select>
                    @error('area_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <x-select label="الحالة" name="status_id" required :options="$statuses->pluck('name', 'id')" :selected="$property->status_id" />

                <div>
                    <label class="{{ $labelCls }}">الغرض <span class="text-danger">*</span></label>
                    <select name="purpose" x-model="purpose" required class="{{ $inputCls }}">
                        <option value="sale">بيع</option>
                        <option value="rent">إيجار</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="{{ $labelCls }}">السعر ({{ auth()->user()->currencySymbol() }}) <span class="text-danger">*</span></label>
                        <input name="price" type="number" step="0.001" min="0" value="{{ old('price', $property->price) }}" required class="{{ $inputCls }}">
                        @error('price')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="purpose === 'rent'" x-cloak>
                        <label class="{{ $labelCls }}">الدورة</label>
                        <select name="price_period" class="{{ $inputCls }}">
                            <option value="monthly" @selected(old('price_period', $property->price_period) === 'monthly')>شهري</option>
                            <option value="yearly" @selected(old('price_period', $property->price_period) === 'yearly')>سنوي</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-[1fr_140px] gap-2">
                    <x-select label="المالك" name="owner_id" :options="$owners->pluck('name', 'id')" :selected="$property->owner_id" />
                    <x-input label="عمولة المالك (%)" name="owner_commission_rate" type="number" step="0.01" min="0" max="100" :value="$property->owner_commission_rate" placeholder="مثال: 2.5" />
                </div>
                <x-select label="مندوب المبيعات" name="agent_id" :options="$agents->pluck('name', 'id')" :selected="$property->agent_id" />

                <div class="grid grid-cols-3 gap-2 sm:col-span-2">
                    <div x-show="isResidential">
                        <x-input label="غرف النوم" name="bedrooms" type="number" min="0" :value="$property->bedrooms" />
                    </div>
                    <x-input label="الحمامات" name="bathrooms" type="number" min="0" :value="$property->bathrooms" />
                    <x-input label="المساحة (م²)" name="area_size" type="number" step="0.01" min="0" :value="$property->area_size" />
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer sm:col-span-2">
                    <input type="hidden" name="is_furnished" value="0">
                    <input type="checkbox" name="is_furnished" value="1" @checked(old('is_furnished', $property->is_furnished))>
                    مفروشة
                </label>

                <div class="sm:col-span-2">
                    <label class="{{ $labelCls }}">وصف مختصر (عربي)</label>
                    <input name="short_description[ar]" value="{{ $t('short_description', 'ar') }}" class="{{ $inputCls }}">
                </div>
            </div>
        </div>

        {{-- ===== الموقع ===== --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-ink mb-4">الموقع</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input label="إسم المبنى" name="building_name" :value="$property->building_name" />
                <x-input label="القطعة" name="block" :value="$property->block" />
                <x-input label="الشارع" name="street" :value="$property->street" />
                <x-input label="العمارة" name="building" :value="$property->building" />
                <div class="sm:col-span-2">
                    <label class="{{ $labelCls }}">رابط موقع العقار على خرائط جوجل</label>
                    <input name="map_url" type="url" value="{{ old('map_url', $property->map_url) }}" dir="ltr" placeholder="https://maps.app.goo.gl/..." class="{{ $inputCls }}">
                    @error('map_url')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <x-input label="حارس العقار" name="guard_name" :value="$property->guard_name" />
                <x-input label="رقم الحارس" name="guard_phone" :value="$property->guard_phone" dir="ltr" class="text-end" />
            </div>
        </div>

        {{-- ===== الوصف والمواصفات ===== --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6 space-y-4">
            <h3 class="font-bold text-ink">الوصف والمواصفات</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="{{ $labelCls }}">الوصف (عربي)</label><textarea name="description[ar]" rows="4" class="{{ $inputCls }}">{{ $t('description', 'ar') }}</textarea></div>
                <div><label class="{{ $labelCls }}">الوصف (English)</label><textarea name="description[en]" rows="4" dir="ltr" class="{{ $inputCls }}">{{ $t('description', 'en') }}</textarea></div>
                <div><label class="{{ $labelCls }}">المواصفات (عربي)</label><textarea name="specifications[ar]" rows="3" class="{{ $inputCls }}">{{ $t('specifications', 'ar') }}</textarea></div>
                <div><label class="{{ $labelCls }}">المواصفات (English)</label><textarea name="specifications[en]" rows="3" dir="ltr" class="{{ $inputCls }}">{{ $t('specifications', 'en') }}</textarea></div>
            </div>
        </div>

        {{-- ===== المرافق ===== --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-ink mb-4">المرافق والخدمات</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach ($amenities as $a)
                    <label class="flex items-center gap-2 rounded-field border border-gray-100 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="amenities[]" value="{{ $a->id }}" @checked(in_array($a->id, (array) $selectedAmenities))>
                        {{ $a->name }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- ===== الوسائط ===== --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-ink mb-4">الوسائط</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelCls }}">الصورة الرئيسية</label>
                    <x-cms.dropzone label="" name="cover" :media="$property->exists ? $property->getFirstMedia('cover') : null" />
                    @error('cover')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $labelCls }}">معرض الصور (بلا حد)</label>
                    <x-cms.dropzone label="" name="gallery" multiple :media="$property->exists ? $property->getMedia('gallery') : []" />
                </div>
                <div class="sm:col-span-2">
                    <label class="{{ $labelCls }}">رابط فيديو يوتيوب</label>
                    <input name="video_url" type="url" value="{{ old('video_url', $property->video_url) }}" dir="ltr" placeholder="https://www.youtube.com/watch?v=..." class="{{ $inputCls }}">
                    @error('video_url')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $property->is_featured))>
                    عقار مميّز (يظهر في المقدّمة)
                </label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('dashboard.properties.index') }}" class="rounded-full px-5 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</a>
            <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">{{ $property->exists ? 'حفظ التعديلات' : 'إضافة العقار' }}</button>
        </div>
    </form>
</div>
@endsection
