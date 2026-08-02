@extends('layouts.dashboard')

@section('title', $property->reference_code)
@section('page-title', 'تفاصيل العقار')

@section('content')
<div>
    <x-flash />

    <div class="flex items-center justify-between gap-4 mb-4">
        <a href="{{ route('dashboard.properties.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-700">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg> العودة للعقارات
        </a>
        @can('properties.edit')
            <a href="{{ route('dashboard.properties.edit', $property) }}" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 hover:bg-gray-50 text-sm text-gray-700 px-3 py-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg> تعديل
            </a>
        @endcan
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            {{-- الصورة والعنوان --}}
            <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
                <div class="aspect-[16/9] bg-gray-100 grid place-items-center text-gray-300">
                    @if ($property->cover_url)<img src="{{ $property->cover_url }}" class="w-full h-full object-cover" alt="">
                    @else <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg>@endif
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs text-gray-400"><span dir="ltr">{{ $property->reference_code }}</span></span>
                        @if ($property->status)<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" style="color: {{ $property->status->color }}; background-color: {{ $property->status->color }}1a;">{{ $property->status->name }}</span>@endif
                        @if ($property->is_featured)<span class="rounded-full bg-accent-100 text-accent-800 px-2.5 py-1 text-xs font-medium">مميّز</span>@endif
                    </div>
                    <h2 class="text-xl font-bold text-ink">{{ $property->title }}</h2>
                    <p class="text-primary-700 font-bold text-lg mt-1 tabular-nums">{{ number_format($property->price, 3) }} د.ك <span class="text-sm text-gray-400 font-normal">{{ $property->purpose === 'rent' ? '/'.($property->price_period === 'yearly' ? 'سنة' : 'شهر') : 'للبيع' }}</span></p>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5 pt-5 border-t border-gray-100 text-sm">
                        <div><p class="text-gray-400 text-xs">غرف النوم</p><p class="font-semibold text-ink">{{ $property->bedrooms ?? '—' }}</p></div>
                        <div><p class="text-gray-400 text-xs">الحمامات</p><p class="font-semibold text-ink">{{ $property->bathrooms ?? '—' }}</p></div>
                        <div><p class="text-gray-400 text-xs">المساحة</p><p class="font-semibold text-ink">{{ $property->area_size ? $property->area_size.' م²' : '—' }}</p></div>
                        <div><p class="text-gray-400 text-xs">المنطقة</p><p class="font-semibold text-ink">{{ $property->area?->name ?? '—' }}</p></div>
                    </div>

                    @if ($property->getTranslation('description', app()->getLocale(), false))
                        <div class="mt-5 pt-5 border-t border-gray-100"><h4 class="font-semibold text-ink mb-1">الوصف</h4><p class="text-sm text-gray-600 whitespace-pre-line">{{ $property->description }}</p></div>
                    @endif

                    @if ($property->amenities->count())
                        <div class="mt-5 pt-5 border-t border-gray-100">
                            <h4 class="font-semibold text-ink mb-2">المرافق والخدمات</h4>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($property->amenities as $a)<span class="rounded-full bg-gray-100 text-gray-600 px-3 py-1 text-xs">{{ $a->name }}</span>@endforeach
                            </div>
                        </div>
                    @endif

                    @if ($property->video_url)
                        <div class="mt-5 pt-5 border-t border-gray-100"><a href="{{ $property->video_url }}" target="_blank" class="inline-flex items-center gap-2 text-sm text-primary-700 hover:underline"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg> فيديو العقار على يوتيوب</a></div>
                    @endif
                </div>
            </div>

            {{-- التقييمات --}}
            <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-ink mb-4">تقييمات العقار
                    @if ($property->reviews->count())<span class="text-gray-400 font-normal text-sm">({{ number_format($property->rating, 1) }} ★ · {{ $property->reviews->count() }})</span>@endif
                </h3>

                @can('properties.edit')
                    <form method="POST" action="{{ route('dashboard.properties.reviews.store', $property) }}" class="rounded-field bg-gray-50 border border-gray-100 p-4 mb-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @csrf
                        <input name="reviewer_name" placeholder="اسم العميل" required class="rounded-field border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500">
                        <select name="rating" class="rounded-field border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500">
                            @for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} نجوم</option>@endfor
                        </select>
                        <button class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2 text-sm">إضافة تقييم</button>
                        <textarea name="comment" rows="2" placeholder="التعليق (اختياري)" class="sm:col-span-3 rounded-field border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500"></textarea>
                    </form>
                @endcan

                <div class="space-y-3">
                    @forelse ($property->reviews as $rev)
                        <div class="border-b border-gray-50 last:border-0 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm text-ink">{{ $rev->reviewer_name }}</span>
                                <span class="text-accent-500 text-xs">{{ str_repeat('★', $rev->rating) }}{{ str_repeat('☆', 5 - $rev->rating) }}</span>
                                <span class="text-xs text-gray-400 ms-auto">{{ $rev->created_at->format('Y-m-d') }}</span>
                            </div>
                            @if ($rev->comment)<p class="text-sm text-gray-600 mt-1">{{ $rev->comment }}</p>@endif
                        </div>
                    @empty
                        <p class="text-center text-sm text-gray-400 py-4">لا توجد تقييمات بعد.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- الجانب: المالك والوكيل والموقع --}}
        <div class="space-y-5">
            <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-ink mb-3">المالك والوكيل</h3>
                <div class="space-y-3 text-sm">
                    <div><p class="text-gray-400 text-xs">المالك</p><p class="font-medium text-ink">{{ $property->owner?->name ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">الوكيل المسؤول</p><p class="font-medium text-ink">{{ $property->agent?->name ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">التصنيف / النوع</p><p class="font-medium text-ink">{{ $property->category?->name }} · {{ $property->unitType?->name }}</p></div>
                </div>
            </div>

            @if ($property->block || $property->street || $property->building)
                @php
                    $addr = collect([
                        $property->area?->name,
                        $property->block ? 'قطعة '.$property->block : null,
                        $property->street ? 'شارع '.$property->street : null,
                        $property->building ? 'عمارة '.$property->building : null,
                    ])->filter()->implode(' · ');
                @endphp
                <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                    <h3 class="font-bold text-ink mb-3">الموقع</h3>
                    <p class="text-sm text-gray-600">{{ $addr }}</p>
                </div>
            @endif

            @if (count($property->gallery_urls))
                <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                    <h3 class="font-bold text-ink mb-3">المعرض ({{ count($property->gallery_urls) }})</h3>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach ($property->gallery_urls as $url)<img src="{{ $url }}" class="aspect-square object-cover rounded-lg" alt="">@endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
