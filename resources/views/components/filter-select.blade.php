@props([
    'label' => null,
    'name',
    'options' => [],        // [value => label]
    'selected' => null,
    'placeholder' => null,  // خيار «الكل» في أعلى القائمة
    'span' => null,
])

<div class="{{ $span }}">
    @if ($label)<label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ $label }}</label>@endif
    <div class="relative">
        <select name="{{ $name }}"
                {{ $attributes->merge(['class' => 'w-full appearance-none rounded-field bg-white border border-gray-200 ps-3.5 pe-9 h-10 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15']) }}>
            @isset($placeholder)<option value="">{{ $placeholder }}</option>@endisset
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
            @endforeach
            {{ $slot }}
        </select>
        <svg class="absolute end-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m6 9 6 6 6-6"/></svg>
    </div>
</div>
