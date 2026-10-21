@props(['title' => '', 'close' => 'إغلاق', 'prev' => 'السابق', 'next' => 'التالي'])
{{--
    عارض الصور بملء الشاشة لمكوّن propertyGallery (app.js) — يوضع داخل نطاقه (الموقع ولوحة التحكم).
    أسهم على جانبي الصورة فوقها مباشرة + الكيبورد + شريط المصغّرات، بأقل هوامش جانبية حتى تأخذ الصورة أكبر مساحة.
--}}
<template x-teleport="body">
    <div x-cloak x-show="viewer" x-transition.opacity.duration.200ms x-ref="viewer" tabindex="-1"
         @keydown.escape.window="viewer && closeViewer()"
         @keydown.arrow-left.window="viewer && move(document.dir === 'rtl' ? 1 : -1)"
         @keydown.arrow-right.window="viewer && move(document.dir === 'rtl' ? -1 : 1)"
         class="fixed inset-0 z-[60] flex flex-col bg-primary-950/95 backdrop-blur-md outline-none">
        <div class="flex items-center gap-3 px-4 sm:px-6 py-3">
            <h3 class="min-w-0 flex-1 text-white font-bold text-sm sm:text-base truncate">{{ $title }}</h3>
            <span class="text-white/70 text-sm tabular-nums" dir="ltr" x-text="(current + 1) + ' / ' + images.length"></span>
            <button type="button" @click="closeViewer()" class="grid place-items-center w-10 h-10 shrink-0 rounded-full bg-white/10 border border-white/20 text-white hover:bg-white/20 transition" aria-label="{{ $close }}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="relative flex-1 min-h-0 px-1 sm:px-3" @click.self="closeViewer()">
            <img :src="src" class="w-full h-full object-contain select-none" alt="{{ $title }}">
            <template x-if="images.length > 1">
                <div>
                    <button type="button" @click="move(-1)" class="absolute top-1/2 -translate-y-1/2 start-2 sm:start-4 grid place-items-center w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-primary-950/55 backdrop-blur-md border border-white/25 text-white shadow-lg hover:bg-primary-950/75 transition" aria-label="{{ $prev }}">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="rtl:rotate-180"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <button type="button" @click="move(1)" class="absolute top-1/2 -translate-y-1/2 end-2 sm:end-4 grid place-items-center w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-primary-950/55 backdrop-blur-md border border-white/25 text-white shadow-lg hover:bg-primary-950/75 transition" aria-label="{{ $next }}">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="rtl:rotate-180"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
            </template>
        </div>
        {{-- w-max + mx-auto بدل justify-center حتى لا يُقصّ أول الشريط عند كثرة الصور --}}
        <div x-show="images.length > 1" class="overflow-x-auto px-4 py-3">
            <div class="flex gap-2 w-max mx-auto p-0.5">
                <template x-for="(img, i) in images" :key="i">
                    <button type="button" @click="select(i)" class="shrink-0 w-16 h-12 sm:w-20 sm:h-14 rounded-lg overflow-hidden transition"
                            :class="current === i ? 'ring-2 ring-accent-500 opacity-100' : 'opacity-50 hover:opacity-80'">
                        <img :src="img" class="w-full h-full object-cover" alt="" loading="lazy">
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
