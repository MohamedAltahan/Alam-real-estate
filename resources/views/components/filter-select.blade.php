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
                {{ $attributes->merge(['class' => 'w-full rounded-field bg-white border border-gray-200 ps-3.5 pe-9 h-10 text-sm text-ink cursor-pointer focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15']) }}>
            @isset($placeholder)<option value="">{{ $placeholder }}</option>@endisset
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
            @endforeach
            {{ $slot }}
        </select>
    </div>
</div>
