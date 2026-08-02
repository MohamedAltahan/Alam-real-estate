@props([
    'name',
    'placeholder' => '',
    'items' => [],          // [['v' => 1, 'l' => 'السالمية'], ...]
    'value' => '',
    'label' => '',
    'searchLabel' => null,
])

@php
    $loc = app()->getLocale();
    $tt = fn ($ar, $en) => $loc === 'ar' ? $ar : $en;
    $cfg = ['value' => (string) $value, 'label' => (string) $label, 'items' => array_values($items)];
    $searchText = $searchLabel ?: $tt('ابحث...', 'Search...');
    $allText = $tt('الكل', 'All');
    $emptyText = $tt('لا توجد نتائج', 'No results');
@endphp

<div x-data="combobox(@js($cfg))" @click.outside="close()" @keydown.escape.window="close()"
     class="relative flex-1 min-w-[128px]">
    <input type="hidden" name="{{ $name }}" :value="value">

    {{-- الزر --}}
    <button type="button" @click="toggle()"
            class="w-full flex items-center justify-between gap-2 rounded-full glass px-4 py-3 text-sm text-start hover:bg-white/[0.16] focus:outline-none focus:ring-2 focus:ring-accent-500/40 transition">
        <span class="truncate" :class="label ? 'text-white' : 'text-white/60'" x-text="label || @js($placeholder)"></span>
        <svg class="shrink-0 text-white/60 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
             width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m6 9 6 6 6-6"/></svg>
    </button>

    {{-- اللوحة --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute z-50 mt-2 w-full min-w-[190px] rounded-2xl bg-white border border-gray-100 shadow-2xl p-2">
        {{-- حقل البحث --}}
        <div class="relative mb-2">
            <input x-ref="search" x-model="q" type="text" placeholder="{{ $searchText }}"
                   class="w-full rounded-full bg-gray-50 border border-gray-200 ps-9 pe-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-primary-500 focus:bg-white transition">
            <svg class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"
                 width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>

        {{-- الخيارات --}}
        <ul class="max-h-56 overflow-y-auto space-y-0.5">
            <li>
                <button type="button" @click="clear()"
                        class="w-full text-start rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-50 transition">{{ $allText }}</button>
            </li>
            <template x-for="item in filtered()" :key="item.v">
                <li>
                    <button type="button" @click="pick(item)" x-text="item.l"
                            class="w-full text-start rounded-lg px-3 py-2 text-sm transition"
                            :class="value === String(item.v) ? 'bg-primary-50 text-primary-800 font-semibold' : 'text-gray-700 hover:bg-gray-50'"></button>
                </li>
            </template>
            <li x-show="! filtered().length" class="px-3 py-3 text-sm text-gray-400 text-center">{{ $emptyText }}</li>
        </ul>
    </div>
</div>
