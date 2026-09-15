{{-- تبويبا تقارير التحول: تحول المعاينات · تحول العملاء (روابط — كل تبويب بفلاتره) --}}
@php
    $pill = 'inline-flex items-center gap-2 rounded-full h-9 px-4 text-sm font-semibold whitespace-nowrap transition';
    $pillOn = $pill.' bg-primary-900 text-white';
    $pillOff = $pill.' text-gray-600 hover:bg-white hover:text-ink';
    $tabs = [
        'viewings' => ['label' => 'تحول المعاينات', 'url' => route('dashboard.reports.conversion'), 'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
        'clients' => ['label' => 'تحول العملاء', 'url' => route('dashboard.reports.clients-conversion'), 'icon' => '<circle cx="9" cy="8" r="4"/><path d="M17 21a8 8 0 0 0-16 0"/><path d="m16 11 2 2 4-4"/>'],
    ];
@endphp

<div class="inline-flex items-center gap-1 rounded-full bg-gray-100/70 border border-gray-100 p-1 max-w-full overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist">
    @foreach ($tabs as $key => $item)
        @php $on = $tab === $key; @endphp
        <a href="{{ $item['url'] }}" role="tab" aria-selected="{{ $on ? 'true' : 'false' }}" class="{{ $on ? $pillOn : $pillOff }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">{!! $item['icon'] !!}</svg>
            {{ $item['label'] }}
        </a>
    @endforeach
</div>
