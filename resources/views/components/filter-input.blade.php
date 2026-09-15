@props([
    'label' => null,
    'name',
    'value' => null,
    'type' => 'text',
    'placeholder' => null,
    'span' => null,
    'search' => false,       // أيقونة بحث داخل الحقل
    'datepicker' => false,   // منتقي تاريخ (flatpickr)
])

<div class="{{ $span }}">
    @if ($label)<label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ $label }}</label>@endif
    <div class="relative">
        @if ($search)
            <svg class="absolute inset-y-0 start-3.5 my-auto text-gray-400 pointer-events-none" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        @endif
        <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" autocomplete="off"
               @if ($placeholder) placeholder="{{ $placeholder }}" @endif
               @if ($datepicker) data-datepicker @endif
               {{ $attributes->merge(['class' => 'w-full rounded-field bg-white border border-gray-200 '.($search ? 'ps-10 pe-3.5' : 'px-3.5').' h-10 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15']) }}>
    </div>
</div>
