@extends('layouts.dashboard')

@section('title', $property->exists ? 'تعديل عقار' : 'إضافة عقار')
@section('page-title', 'العقارات')

@php
    $t = fn ($field, $locale) => old("$field.$locale", $property->exists ? $property->getTranslation($field, $locale, false) : '');
    $selectedAmenities = old('amenities', $property->exists ? $property->amenities->pluck('id')->all() : []);
    $inputCls = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
@endphp

@section('content')
<div x-data="{ purpose: '{{ old('purpose', $property->purpose ?? 'sale') }}' }">
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
                <p class="text-sm text-gray-400" dir="ltr">{{ $property->reference_code ?? ($nextCode ?? '') }}</p>
            </div>
        </div>

        {{-- البيانات الأساسية --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-ink mb-4">بيانات العقار</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">العنوان (عربي) <span class="text-danger">*</span></label>
                    <input name="title[ar]" value="{{ $t('title', 'ar') }}" required class="{{ $inputCls }}">
                    @error('title.ar')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">العنوان (English)</label>
                    <input name="title[en]" value="{{ $t('title', 'en') }}" dir="ltr" class="{{ $inputCls }}">
                </div>

                <x-select label="المنطقة" name="area_id" required :options="$areas->pluck('name', 'id')" :selected="$property->area_id" />
                <x-select label="التصنيف" name="category_id" required :options="$categories->pluck('name', 'id')" :selected="$property->category_id" />
                <x-select label="نوع الوحدة" name="unit_type_id" required :options="$unitTypes->pluck('name', 'id')" :selected="$property->unit_type_id" />
                <x-select label="الحالة" name="status_id" required :options="$statuses->pluck('name', 'id')" :selected="$property->status_id" />

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الغرض <span class="text-danger">*</span></label>
                    <select name="purpose" x-model="purpose" required class="{{ $inputCls }}">
                        <option value="sale">بيع</option>
                        <option value="rent">إيجار</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">السعر (د.ك) <span class="text-danger">*</span></label>
                        <input name="price" type="number" step="0.001" min="0" value="{{ old('price', $property->price) }}" required class="{{ $inputCls }}">
                        @error('price')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="purpose === 'rent'" x-cloak>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">الدورة</label>
                        <select name="price_period" class="{{ $inputCls }}">
                            <option value="monthly" @selected(old('price_period', $property->price_period) === 'monthly')>شهري</option>
                            <option value="yearly" @selected(old('price_period', $property->price_period) === 'yearly')>سنوي</option>
                        </select>
                    </div>
                </div>

                <x-select label="المالك" name="owner_id" :options="$owners->pluck('name', 'id')" :selected="$property->owner_id" />
                <x-select label="الوكيل المسؤول" name="agent_id" :options="$agents->pluck('name', 'id')" :selected="$property->agent_id" />

                <div class="grid grid-cols-3 gap-2 sm:col-span-2">
                    <x-input label="غرف النوم" name="bedrooms" type="number" :value="$property->bedrooms" />
                    <x-input label="الحمامات" name="bathrooms" type="number" :value="$property->bathrooms" />
                    <x-input label="المساحة (م²)" name="area_size" type="number" :value="$property->area_size" />
                </div>

                <x-input label="القطعة" name="block" :value="$property->block" />
                <div class="grid grid-cols-2 gap-2">
                    <x-input label="الشارع" name="street" :value="$property->street" />
                    <x-input label="العمارة" name="building" :value="$property->building" />
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">وصف مختصر (عربي)</label>
                    <input name="short_description[ar]" value="{{ $t('short_description', 'ar') }}" class="{{ $inputCls }}">
                </div>
            </div>
        </div>

        {{-- الوصف والمواصفات --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6 space-y-4">
            <h3 class="font-bold text-ink">الوصف والمواصفات</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الوصف (عربي)</label><textarea name="description[ar]" rows="4" class="{{ $inputCls }}">{{ $t('description', 'ar') }}</textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الوصف (English)</label><textarea name="description[en]" rows="4" dir="ltr" class="{{ $inputCls }}">{{ $t('description', 'en') }}</textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">المواصفات (عربي)</label><textarea name="specifications[ar]" rows="3" class="{{ $inputCls }}">{{ $t('specifications', 'ar') }}</textarea></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">المواصفات (English)</label><textarea name="specifications[en]" rows="3" dir="ltr" class="{{ $inputCls }}">{{ $t('specifications', 'en') }}</textarea></div>
            </div>
        </div>

        {{-- المرافق --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-ink mb-4">المرافق والخدمات</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach ($amenities as $a)
                    <label class="flex items-center gap-2 rounded-field border border-gray-100 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="amenities[]" value="{{ $a->id }}" @checked(in_array($a->id, (array) $selectedAmenities)) class="rounded border-gray-300 text-primary-900 focus:ring-primary-500/30">
                        {{ $a->name }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- الوسائط --}}
        <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-ink mb-4">الوسائط</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">الصورة الرئيسية</label>
                    @if ($property->exists && $property->cover_image)<img src="{{ Storage::url($property->cover_image) }}" class="w-24 h-24 object-cover rounded-field mb-2" alt="">@endif
                    <input type="file" name="cover_image" accept="image/*" class="w-full text-sm text-gray-500 file:me-3 file:rounded-field file:border-0 file:bg-primary-50 file:text-primary-700 file:px-3 file:py-2 file:text-sm">
                    @error('cover_image')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">معرض الصور (متعدد)</label>
                    <input type="file" name="gallery[]" accept="image/*" multiple class="w-full text-sm text-gray-500 file:me-3 file:rounded-field file:border-0 file:bg-primary-50 file:text-primary-700 file:px-3 file:py-2 file:text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">رابط فيديو يوتيوب</label>
                    <input name="video_url" type="url" value="{{ old('video_url', $property->video_url) }}" dir="ltr" placeholder="https://www.youtube.com/watch?v=..." class="{{ $inputCls }}">
                    @error('video_url')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $property->is_featured)) class="rounded border-gray-300 text-primary-900 focus:ring-primary-500/30">
                    عقار مميّز (يظهر في المقدّمة)
                </label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('dashboard.properties.index') }}" class="rounded-field px-5 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</a>
            <button type="submit" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">{{ $property->exists ? 'حفظ التعديلات' : 'إضافة العقار' }}</button>
        </div>
    </form>
</div>
@endsection
