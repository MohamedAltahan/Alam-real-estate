{{--
| أسهم التنقّل لأي شريط أفقي — تعتمد على نطاق Alpine الخاص بالمكوّن carousel
| (nav / canScroll) الموجود على القسم الحاوي.
|
| توضع في سطر عنوان القسم فوق البطاقات (على كل المقاسات)، وأصناف الإظهار
| والتموضع تُمرَّر عبر class.
--}}
@props(['label' => 'التنقّل بين العناصر'])

@php
    $btn = 'grid place-items-center w-9 h-9 rounded-full bg-white border border-gray-200 text-gray-500 transition';
    $state = "canScroll ? 'hover:bg-gray-50 hover:text-primary-800' : 'opacity-40 cursor-not-allowed'";
@endphp

<div {{ $attributes->merge(['class' => 'items-center gap-2 shrink-0']) }} role="group" aria-label="{{ $label }}">
    <button type="button" @click="nav(-1)" :disabled="! canScroll" :class="{{ $state }}" class="{{ $btn }}" aria-label="prev">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
    </button>
    <button type="button" @click="nav(1)" :disabled="! canScroll" :class="{{ $state }}" class="{{ $btn }}" aria-label="next">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
    </button>
</div>
