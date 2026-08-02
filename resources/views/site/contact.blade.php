@extends('site.layouts.app')

@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $field = 'w-full rounded-xl bg-gray-50 border border-gray-100 px-4 py-3 text-sm text-ink placeholder:text-gray-400 focus:outline-none focus:border-primary-400 focus:bg-white transition';
    $label = 'block text-sm font-bold text-ink mb-2';
    $req = '<span class="text-danger">*</span>';
@endphp

@section('title', $t('تواصل معنا', 'Contact Us'))

@section('content')
{{-- ===================== هيرو ===================== --}}
<section class="relative isolate text-white bg-gradient-to-br from-primary-800 via-primary-900 to-primary-950 overflow-hidden">
    <x-site.page-hero-bg />
    <div class="absolute -top-16 -end-16 w-72 h-72 rounded-full bg-accent-500/10"></div>
    <div class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-white/70 to-transparent"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-28 sm:pt-32 pb-16 sm:pb-20 text-center">
        <span class="inline-block rounded-full bg-accent-500/20 border border-accent-500/40 text-accent-300 px-4 py-1 text-xs font-bold mb-5">{{ $t('تواصل معنا', 'Contact us') }}</span>
        <h1 class="font-display text-2xl sm:text-3xl font-bold leading-tight mb-4">{{ $t('نحن هنا لمساعدتك', 'We are here to help') }}</h1>
        <p class="text-white/70 text-sm leading-relaxed">{{ $t('فريقنا جاهز للإجابة عن استفساراتك وتقديم الدعم اللازم، لتستمتع بتجربة عقارية سهلة، موثوقة، ومصممة لتلبية احتياجاتك.', 'Our team is ready to answer your questions and support you with an easy, trusted property experience.') }}</p>
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
                    {{ $t('تم إرسال رسالتك بنجاح! سيتواصل معك فريقنا قريباً.', 'Your message has been sent! Our team will contact you soon.') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl bg-danger/10 border border-danger/20 text-danger px-4 py-3 mb-6 text-sm">
                    {{ $t('يرجى تصحيح الحقول المطلوبة.', 'Please correct the required fields.') }}
                </div>
            @endif

            <h2 class="text-xl sm:text-2xl font-bold text-ink mb-2">{{ $t('أرسل لنا رسالة', 'Send us a message') }}</h2>
            <p class="text-sm text-gray-500 mb-7">{{ $t('املأ النموذج وسنتواصل معك في أقرب وقت ممكن.', 'Fill in the form and we will get back to you shortly.') }}</p>

            <form method="POST" action="{{ route('site.contact.store') }}" class="space-y-5">
                @csrf
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $label }}">{{ $t('الاسم الكامل', 'Full name') }} {!! $req !!}</label>
                        <input name="name" value="{{ old('name') }}" required placeholder="{{ $t('أدخل اسمك الكامل', 'Your full name') }}" class="{{ $field }}">
                        @error('name')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $label }}">{{ $t('رقم الجوال', 'Phone') }}</label>
                        <input name="phone" value="{{ old('phone') }}" dir="ltr" placeholder="05XXXXXXXX" class="{{ $field }} text-start">
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
                        <label class="{{ $label }}">{{ $t('نوع الطلب', 'Request type') }}</label>
                        <div class="relative">
                            <select name="request_type_id" class="{{ $field }} appearance-none pe-10 cursor-pointer">
                                <option value="">{{ $t('اختر نوع الطلب', 'Choose a type') }}</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->id }}" @selected(old('request_type_id') == $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                            <svg class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m6 9 6 6 6-6"/></svg>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="{{ $label }}">{{ $t('الموضوع', 'Subject') }}</label>
                    <input name="subject" value="{{ old('subject') }}" placeholder="{{ $t('موضوع الرسالة', 'Message subject') }}" class="{{ $field }}">
                </div>

                <div>
                    <label class="{{ $label }}">{{ $t('الرسالة', 'Message') }} {!! $req !!}</label>
                    <textarea name="message" rows="6" required placeholder="{{ $t('اكتب رسالتك هنا...', 'Write your message here...') }}" class="{{ $field }} resize-y">{{ old('message') }}</textarea>
                    @error('message')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
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
