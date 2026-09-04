@props(['label' => null, 'name', 'options' => [], 'groups' => null, 'selected' => null, 'required' => false, 'placeholder' => '— اختر —'])

{{-- groups: ['اسم المجموعة' => [val => label, ...]] لعرض الخيارات داخل <optgroup> --}}
<div>
    @if ($label)
        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
    @endif
    <select name="{{ $name }}" @required($required)
            {{ $attributes->merge(['class' => 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white']) }}>
        @if ($placeholder !== false)
            <option value="">{{ $placeholder }}</option>
        @endif
        @if ($groups)
            @foreach ($groups as $group => $items)
                <optgroup label="{{ $group }}">
                    @foreach ($items as $val => $lbl)
                        <option value="{{ $val }}" @selected((string) old($name, $selected) === (string) $val)>{{ $lbl }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        @else
            @foreach ($options as $val => $lbl)
                <option value="{{ $val }}" @selected((string) old($name, $selected) === (string) $val)>{{ $lbl }}</option>
            @endforeach
        @endif
        {{ $slot }}
    </select>
    @error($name)<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
</div>
