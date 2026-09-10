{{-- تبويبا شاشة طلبات التواصل: صندوق الوارد · الطلبات المميزة (روابط — كل تبويب بفلاتره) --}}
@php
    $pill = 'inline-flex items-center gap-2 rounded-full h-9 px-4 text-sm font-semibold whitespace-nowrap transition';
    $pillOn = $pill.' bg-primary-900 text-white';
    $pillOff = $pill.' text-gray-600 hover:bg-white hover:text-ink';
    $badgeOn = 'grid place-items-center min-w-5 h-5 px-1 rounded-full bg-white/20 text-white text-[11px] font-bold tabular-nums';
    $badgeOff = 'grid place-items-center min-w-5 h-5 px-1 rounded-full bg-gray-200/80 text-gray-600 text-[11px] font-bold tabular-nums';
    $tabs = [
        'inbox' => ['label' => 'صندوق الوارد', 'url' => route('dashboard.requests.index')],
        'featured' => ['label' => 'الطلبات المميزة', 'url' => route('dashboard.requests.featured')],
    ];
@endphp

<div class="inline-flex items-center gap-1 rounded-full bg-gray-100/70 border border-gray-100 p-1 max-w-full overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist">
    @foreach ($tabs as $key => $item)
        @php $on = $tab === $key; @endphp
        <a href="{{ $item['url'] }}" role="tab" aria-selected="{{ $on ? 'true' : 'false' }}" class="{{ $on ? $pillOn : $pillOff }}">
            @if ($key === 'featured')
                <svg width="14" height="14" viewBox="0 0 24 24" fill="{{ $on ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" stroke-linejoin="round" class="shrink-0 {{ $on ? '' : 'text-accent-500' }}"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            @endif
            {{ $item['label'] }}
            <span class="{{ $on ? $badgeOn : $badgeOff }}">{{ number_format($counts[$key] ?? 0) }}</span>
        </a>
    @endforeach
</div>
