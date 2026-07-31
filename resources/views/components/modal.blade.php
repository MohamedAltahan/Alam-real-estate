@props(['name', 'title' => null, 'maxWidth' => '2xl'])

@php
    $mw = match ($maxWidth) {
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
        default => 'max-w-2xl',
    };
@endphp

<div x-data="{ open: false }"
     x-on:open-modal.window="$event.detail === '{{ $name }}' && (open = true)"
     x-on:close-modal.window="$event.detail === '{{ $name }}' && (open = false)"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-primary-950/50" @click="open = false"></div>
    <div class="relative w-full {{ $mw }} bg-white rounded-card shadow-2xl max-h-[90vh] overflow-y-auto" x-transition.opacity>
        @if ($title)
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="font-bold text-ink">{{ $title }}</h3>
                <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-700">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
        @endif
        {{ $slot }}
    </div>
</div>
