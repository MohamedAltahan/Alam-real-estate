@extends('site.layouts.app')

@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $field = 'w-full rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 text-sm text-ink placeholder:text-gray-400 focus:outline-none focus:border-primary-400 focus:bg-white transition';
    $label = 'block text-sm font-bold text-ink mb-2';
    $req = '<span class="text-danger">*</span>';
    $chev = '<svg class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m6 9 6 6 6-6"/></svg>';
@endphp

@section('title', $t('طلب عرض العقار', 'List Your Property'))

@section('content')
{{-- ===================== هيرو ===================== --}}
<section class="relative isolate text-white bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950 overflow-hidden">
    <x-site.page-hero-bg />
    <div class="absolute -top-16 -start-16 w-72 h-72 rounded-full bg-accent-500/10"></div>
    <div class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-white/70 to-transparent"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-28 sm:pt-32 pb-16 sm:pb-20 text-center">
        <span class="inline-block rounded-full bg-accent-500/20 border border-accent-500/40 text-accent-300 px-4 py-1 text-xs font-bold mb-5">{{ $t('طلب عرض العقار', 'List a property') }}</span>
        <h1 class="font-display text-2xl sm:text-3xl font-bold leading-tight mb-4">{{ $t('طلب عرض العقار', 'List your property') }}</h1>
        <p class="text-white/70 text-sm leading-relaxed">{{ $t('تقدَّم بطلبك لعرض العقار وسنتولى تنسيق موعد المعاينة في الوقت الذي يناسبك.', 'Submit your listing request and we will arrange a viewing at a time that suits you.') }}</p>
    </div>
</section>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
    <div class="grid lg:grid-cols-2 gap-10 lg:gap-14 items-start">
        {{-- معلومات التواصل + الخريطة (يمين) --}}
        <x-site.contact-aside />

        {{-- النموذج (شمال) --}}
        <div class="rounded-3xl bg-white border border-gray-100 shadow-sm p-6 sm:p-8">
            @if (session('sent'))
                <div class="flex items-center gap-3 rounded-xl bg-success/10 border border-success/20 text-success px-4 py-3 mb-6 text-sm">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 6 9 17l-5-5"/></svg>
                    {{ $t('تم استلام طلبك بنجاح! سيتواصل معك فريقنا لتأكيد تفاصيل العقار.', 'Your request has been received! Our team will contact you to confirm the details.') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl bg-danger/10 border border-danger/20 text-danger px-4 py-3 mb-6 text-sm">
                    {{ $t('يرجى تصحيح الحقول المطلوبة.', 'Please correct the required fields.') }}
                </div>
            @endif

            <h2 class="text-xl sm:text-2xl font-bold text-ink mb-2">{{ $t('أرسل لنا بيانات عقارك', 'Send us your property details') }}</h2>
            <p class="text-sm text-gray-500 mb-7">{{ $t('املأ النموذج وسنتواصل معك في أقرب وقت ممكن.', 'Fill in the form and we will get back to you shortly.') }}</p>

            <form method="POST" action="{{ route('site.list-property.store') }}" class="space-y-5">
                @csrf
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">{{ $t('الاسم الكامل', 'Full name') }} {!! $req !!}</label>
                        <input name="name" value="{{ old('name') }}" required placeholder="{{ $t('أدخل اسمك الكامل', 'Your full name') }}" class="{{ $field }}">
                        @error('name')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ $t('رقم الجوال', 'Phone') }} {!! $req !!}</label>
                        <input name="phone" value="{{ old('phone') }}" required dir="ltr" placeholder="05XXXXXXXX" class="{{ $field }} text-start">
                        @error('phone')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">{{ $t('البريد الإلكتروني', 'Email') }}</label>
                        <input name="email" type="email" value="{{ old('email') }}" dir="ltr" placeholder="email@example.com" class="{{ $field }} text-start">
                        @error('email')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ $t('نوع الوحدة', 'Unit type') }}</label>
                        <div class="relative">
                            <select name="unit_type_id" class="{{ $field }} appearance-none pe-10 cursor-pointer">
                                <option value="">{{ $t('اختر نوع الوحدة', 'Choose unit type') }}</option>
                                @foreach ($unitTypes as $u)
                                    <option value="{{ $u->id }}" @selected(old('unit_type_id') == $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                            {!! $chev !!}
                        </div>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">{{ $t('المنطقة', 'Area') }}</label>
                        <div class="relative">
                            <select name="area_id" class="{{ $field }} appearance-none pe-10 cursor-pointer">
                                <option value="">{{ $t('اختر المنطقة', 'Choose area') }}</option>
                                @foreach ($areas as $a)
                                    <option value="{{ $a->id }}" @selected(old('area_id') == $a->id)>{{ $a->name }}</option>
                                @endforeach
                            </select>
                            {!! $chev !!}
                        </div>
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ $t('السعر المطلوب', 'Asking price') }}</label>
                        <div class="relative">
                            <input name="price" value="{{ old('price') }}" placeholder="0" class="{{ $field }} pe-14">
                            <span class="absolute end-4 top-1/2 -translate-y-1/2 text-xs text-gray-400">{{ $t('د.ك', 'KWD') }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="{{ $label }}">{{ $t('تفاصيل العقار', 'Property details') }} {!! $req !!}</label>
                    <textarea name="details" rows="6" required placeholder="{{ $t('المساحة، عدد الغرف، الحالة، أي مميزات إضافية...', 'Area, rooms, condition, extra features...') }}" class="{{ $field }} resize-y">{{ old('details') }}</textarea>
                    @error('details')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-full navy-gradient hover:brightness-125 text-white font-bold px-8 py-3.5 text-sm transition">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4 20-7z"/></svg>
                    {{ $t('إرسال الطلب', 'Send request') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
