@if (session('success'))
    <div class="mb-4 rounded-field bg-success-soft text-success text-sm px-4 py-3 flex items-center gap-2">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-4 rounded-field bg-danger-soft text-danger text-sm px-4 py-3">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-field bg-danger-soft text-danger text-sm px-4 py-3">
        <p class="font-semibold mb-1">فيه أخطاء محتاجة تصحيح:</p>
        <ul class="list-disc ps-5 space-y-0.5">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif
