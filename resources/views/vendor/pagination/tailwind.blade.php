@php
    $btn = 'grid place-items-center min-w-9 h-9 px-2 rounded-full text-sm font-bold transition';
    $idle = $btn.' border border-gray-200 bg-white text-gray-600 hover:bg-gray-50';
    $active = $btn.' bg-primary-900 text-white';
    $disabled = $btn.' border border-gray-100 bg-white text-gray-300 cursor-not-allowed';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="ترقيم الصفحات"
         class="flex flex-wrap items-center justify-between gap-4 mt-5">

        {{-- ملخّص النتائج --}}
        <p class="text-xs text-gray-500">
            عرض <span class="font-bold text-ink tabular-nums">{{ $paginator->firstItem() }}</span>
            إلى <span class="font-bold text-ink tabular-nums">{{ $paginator->lastItem() }}</span>
            من <span class="font-bold text-ink tabular-nums">{{ $paginator->total() }}</span> نتيجة
        </p>

        <div class="flex items-center gap-1.5">
            {{-- السابق --}}
            @if ($paginator->onFirstPage())
                <span class="{{ $disabled }}" aria-disabled="true" aria-label="السابق">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $idle }}" aria-label="السابق">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
            @endif

            {{-- أرقام الصفحات --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="{{ $disabled }}">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $active }}" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="{{ $idle }}" aria-label="صفحة {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- التالي --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $idle }}" aria-label="التالي">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </a>
            @else
                <span class="{{ $disabled }}" aria-disabled="true" aria-label="التالي">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </span>
            @endif
        </div>
    </nav>
@endif
