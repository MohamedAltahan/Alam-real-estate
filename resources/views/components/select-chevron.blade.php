{{-- سهم صغير فوق قائمة منسدلة بلا مظهر افتراضي (appearance-none) — يُوضع داخل حاوية relative بجانب الـ select --}}
@props(['size' => 12, 'class' => 'end-2.5'])
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" class="pointer-events-none absolute top-1/2 -translate-y-1/2 opacity-70 {{ $class }}"><path d="m6 9 6 6 6-6"/></svg>
