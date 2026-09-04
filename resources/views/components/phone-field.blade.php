@props([
    'countries' => [],
    'code' => '+965',
    'national' => '',
    'field' => '',
    'required' => true,
    'codeName' => 'phone_code',   // اسم حقل مفتاح الدولة
    'phoneName' => 'phone',       // اسم حقل الرقم المحلي
    'dynamic' => false,           // true: الأسماء تعبيرات Alpine (داخل أسطر متكررة)
    'showErrors' => true,
])

@php
    // في الوضع الديناميكي تُمرَّر تعبيرات Alpine كما هي (countries, row.phone_code, row.phone)
    $js = fn ($value) => \Illuminate\Support\Js::from($value)->toHtml();
    $countriesExpr = is_string($countries) ? $countries : $js($countries);
    $codeExpr = $dynamic ? $code : $js($code ?: '+965');
    $nationalExpr = $dynamic ? $national : $js((string) $national);
@endphp

{{-- حقل الهاتف: مفتاح دولة قابل للبحث (الكويت افتراضياً) + رقم محلي --}}
<div x-data="phoneField({ countries: {!! $countriesExpr !!}, code: {!! $codeExpr !!}, national: {!! $nationalExpr !!} })"
     {{ $attributes->only('x-init') }}
     @click.outside="close()" class="relative">
    @if ($dynamic)
        <input type="hidden" :name="{{ $codeName }}" :value="code">
    @else
        <input type="hidden" name="{{ $codeName }}" :value="code">
    @endif

    <div class="flex rounded-field border border-gray-200 bg-gray-50 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/15 focus-within:bg-white transition" dir="ltr">
        <button type="button" @click="toggle()" :title="current.ar"
                class="flex items-center gap-1.5 px-3 border-e border-gray-200 text-sm shrink-0 hover:bg-gray-100/70 rounded-s-field">
            <span x-text="flag(current.iso)" class="text-lg leading-none"></span>
            <span x-text="current.code" class="font-semibold text-ink tabular-nums"></span>
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="text-gray-400 transition-transform" :class="open && 'rotate-180'"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <input x-ref="national" @if ($dynamic) :name="{{ $phoneName }}" @else name="{{ $phoneName }}" @endif x-model="national" @input="sanitize()" @required($required)
               inputmode="numeric" autocomplete="tel-national" placeholder="5XXXXXXX"
               class="flex-1 min-w-0 bg-transparent px-3 py-2.5 text-sm focus:outline-none">
    </div>

    <div x-show="open" x-cloak @keydown="onKey($event)"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1"
         class="absolute z-40 mt-2 w-full min-w-[300px] rounded-2xl bg-white border border-gray-100 shadow-2xl p-2">
        <div class="relative">
            <input x-ref="search" x-model="q" type="text" placeholder="ابحث بالدولة أو المفتاح..." autocomplete="off"
                   class="w-full rounded-full bg-gray-50 border border-gray-200 ps-9 pe-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:bg-white">
            <svg class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
        <ul class="max-h-60 overflow-y-auto mt-2 space-y-0.5">
            <template x-for="(country, index) in filtered()" :key="country.iso + country.code">
                <li>
                    <button type="button" @click="pick(country)" @mouseenter="hi = index"
                            :class="country.code === code ? 'bg-primary-50 text-primary-800 font-semibold' : (hi === index ? 'bg-gray-50' : 'text-gray-700')"
                            class="w-full flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-start transition">
                        <span x-text="flag(country.iso)" class="text-base leading-none"></span>
                        <span x-text="country.ar" class="flex-1 truncate"></span>
                        <span x-text="country.code" dir="ltr" class="text-xs text-gray-400 tabular-nums"></span>
                    </button>
                </li>
            </template>
            <li x-show="! filtered().length" class="px-3 py-3 text-sm text-gray-400 text-center">لا توجد نتائج</li>
        </ul>
    </div>

    @if ($showErrors)
        @error('phone')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        @error('phone_code')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    @endif
</div>
