@php
    $loc = app()->getLocale();
    $t = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $set = fn ($group, $key, $def = '') => \App\Models\Setting::get($group, $key, $def);
    $row = 'flex items-center gap-4 text-sm';
    $chip = 'grid place-items-center w-10 h-10 rounded-full bg-transparent border border-primary-100 text-primary-800 shrink-0';
    // إحداثيات السالمية، الكويت (تُستخدم لخريطة OpenStreetMap بدون مفتاح API)
    $lat = '29.3375';
    $lng = '48.0758';
@endphp

<div>
    <h2 class="text-xl sm:text-2xl font-bold text-ink mb-3">{{ $t('تواصل معنا للمزيد من المعلومات', 'Contact us for more information') }}</h2>
    <p class="text-sm text-gray-500 leading-relaxed mb-7">{{ $t('واصل معنا للحصول على المزيد من المعلومات، وسيكون فريق علم العقارية سعيداً بالإجابة عن جميع استفساراتك وتقديم الدعم الذي تحتاجه بكل احترافية.', 'Reach out for more information — the Alam team will gladly answer your questions and provide professional support.') }}</p>

    <ul class="space-y-5 mb-8">
        @if ($set('contact', 'phone'))
            <li class="{{ $row }}">
                <span class="{{ $chip }}"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                <a href="tel:{{ preg_replace('/\s/', '', $set('contact', 'phone')) }}" class="font-bold text-ink hover:text-primary-700 transition" dir="ltr">{{ $set('contact', 'phone') }}</a>
            </li>
        @endif
        @if ($set('contact', 'email'))
            <li class="{{ $row }}">
                <span class="{{ $chip }}"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg></span>
                <a href="mailto:{{ $set('contact', 'email') }}" class="font-bold text-ink hover:text-primary-700 transition" dir="ltr">{{ $set('contact', 'email') }}</a>
            </li>
        @endif
        @if ($set('contact', 'address'))
            <li class="{{ $row }}">
                <span class="{{ $chip }}"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                <span class="font-bold text-ink">{{ $set('contact', 'address') }}</span>
            </li>
        @endif
    </ul>

    {{-- الخريطة --}}
    <div class="rounded-2xl overflow-hidden border border-gray-100 bg-gray-50">
        <iframe
            title="{{ $t('موقعنا على الخريطة', 'Our location') }}"
            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $lng - 0.012 }}%2C{{ $lat - 0.008 }}%2C{{ $lng + 0.012 }}%2C{{ $lat + 0.008 }}&amp;layer=mapnik&amp;marker={{ $lat }}%2C{{ $lng }}"
            class="w-full h-[360px] border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
</div>
