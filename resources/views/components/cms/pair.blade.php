@props(['label', 'group', 'field', 'ar' => '', 'en' => '', 'type' => 'input', 'rows' => 2])

@php
    $cls = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
@endphp

<div class="grid grid-cols-2 gap-3">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">{{ $label }} (عربي)</label>
        @if ($type === 'textarea')
            <textarea name="{{ $group }}[{{ $field }}_ar]" rows="{{ $rows }}" class="{{ $cls }}">{{ $ar }}</textarea>
        @else
            <input name="{{ $group }}[{{ $field }}_ar]" value="{{ $ar }}" class="{{ $cls }}">
        @endif
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">{{ $label }} (English)</label>
        @if ($type === 'textarea')
            <textarea name="{{ $group }}[{{ $field }}_en]" rows="{{ $rows }}" dir="ltr" class="{{ $cls }}">{{ $en }}</textarea>
        @else
            <input name="{{ $group }}[{{ $field }}_en]" value="{{ $en }}" dir="ltr" class="{{ $cls }}">
        @endif
    </div>
</div>
