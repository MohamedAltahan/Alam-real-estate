@props(['prev' => 'الصورة السابقة', 'next' => 'الصورة التالية'])
{{-- سهمان على جانبي الصورة الكبيرة لمكوّن propertyGallery (app.js) — يوضعان داخل إطار الصورة (relative) --}}
<button type="button" @click="move(-1)" title="{{ $prev }}" aria-label="{{ $prev }}"
        class="absolute top-1/2 -translate-y-1/2 start-3 grid place-items-center w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-primary-950/55 backdrop-blur-md border border-white/25 text-white shadow-lg hover:bg-primary-950/75 transition">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="rtl:rotate-180"><path d="m15 18-6-6 6-6"/></svg>
</button>
<button type="button" @click="move(1)" title="{{ $next }}" aria-label="{{ $next }}"
        class="absolute top-1/2 -translate-y-1/2 end-3 grid place-items-center w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-primary-950/55 backdrop-blur-md border border-white/25 text-white shadow-lg hover:bg-primary-950/75 transition">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="rtl:rotate-180"><path d="m9 18 6-6-6-6"/></svg>
</button>
