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
        <div class="flex items-center gap-2">
            {{-- صفحة العقار على الموقع في تبويب جديد (زيارة الموظف لا تُحسب مشاهدة) --}}
            <a href="{{ route('site.property', $property) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 hover:bg-gray-50 text-sm text-gray-700 px-3 py-2">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg> عرض على الموقع
            </a>
            @can('properties.edit')
                <a href="{{ route('dashboard.properties.edit', $property) }}" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 hover:bg-gray-50 text-sm text-gray-700 px-3 py-2">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg> تعديل
                </a>
            @endcan
        </div>
    </div>

    {{--
        معرض الصور (propertyGallery في app.js — نفس مكوّن الموقع): أسهم على جانبي الصورة للتنقل،
        الضغط على مصغّرة يعرضها، والضغط على الصورة يفتح عارض ملء الشاشة بالأسهم والكيبورد.
    --}}
    @php
        $photos = $property->gallery_urls ?: array_filter([$property->cover_url]);
    @endphp
    <div x-data="propertyGallery(@js(array_values($photos)))" class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            {{-- الصورة والعنوان --}}
            <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
                {{-- ارتفاع ثابت: الصورة داخلها absolute فلا تمدّ الإطار مهما كان مقاسها؛ نسخة مموّهة منها تملأ الجانبين بدل الفراغ الرمادي --}}
                <div class="relative h-[320px] sm:h-[480px] lg:h-[560px] w-full overflow-clip bg-gray-100 grid place-items-center text-gray-300">
                    @if (count($photos))
                        <img src="{{ reset($photos) }}" :src="src" class="absolute inset-0 w-full h-full object-cover blur-2xl opacity-60 scale-110" alt="" aria-hidden="true">
                        <img src="{{ reset($photos) }}" :src="src" @click="openViewer()" class="absolute inset-0 w-full h-full object-contain cursor-zoom-in" alt="">
                        <button type="button" @click="openViewer()" title="عرض بملء الشاشة"
                                class="absolute top-4 end-4 grid place-items-center w-10 h-10 rounded-full bg-primary-950/55 backdrop-blur-md border border-white/25 text-white hover:bg-primary-950/75 transition">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                        </button>
                    @else <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg>@endif
                    @if (count($photos) > 1)
                        {{-- dir على bdi الداخلي لا على الشارة نفسها: وإلا تُحسب start من اليسار فتتداخل مع زر ملء الشاشة --}}
                        <span class="absolute top-4 start-4 rounded-full bg-primary-950/60 backdrop-blur-md text-white text-xs font-bold px-3 py-1.5 tabular-nums"><bdi dir="ltr" x-text="(current + 1) + ' / ' + images.length">1 / {{ count($photos) }}</bdi></span>
                        <x-gallery-arrows />
                    @endif
                </div>
                {{-- المعرض: سطر واحد قابل للتمرير تحت الصورة الكبيرة --}}
                @if (count($photos) > 1)
                    <div class="flex gap-2 overflow-x-auto px-4 pt-3 pb-2 border-b border-gray-100">
                        @foreach ($photos as $i => $url)
                            <button type="button" @click="select({{ $i }})" class="relative shrink-0 w-24 h-16 rounded-lg overflow-hidden bg-gray-100 hover:opacity-90 transition">
                                <img src="{{ $url }}" class="absolute inset-0 w-full h-full object-cover" alt="" loading="lazy">
                                <span class="absolute inset-0 rounded-lg" :class="current === {{ $i }} && 'ring-[3px] ring-inset ring-accent-500'"></span>
                            </button>
                        @endforeach
                    </div>
                @endif
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs text-gray-400">رقم <span dir="ltr" class="font-semibold text-ink">{{ $property->reference_code }}</span></span>
                        @if ($property->status)<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" style="color: {{ $property->status->color }}; background-color: {{ $property->status->color }}1a;">{{ $property->status->name }}</span>@endif
                        @if ($property->is_furnished)<span class="rounded-full bg-primary-50 text-primary-700 px-2.5 py-1 text-xs font-medium">مفروشة</span>@endif
                        @if ($property->is_featured)<span class="rounded-full bg-accent-100 text-accent-800 px-2.5 py-1 text-xs font-medium">مميّز</span>@endif
                        {{-- مشاهدات صفحة العقار على الموقع — للشركة فقط --}}
                        <span class="ms-auto inline-flex items-center gap-1.5 rounded-full bg-gray-100 text-gray-600 px-2.5 py-1 text-xs font-medium tabular-nums" title="مشاهدات صفحة العقار على الموقع (زائر واحد مرة في اليوم)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>{{ number_format($property->views_count) }} مشاهدة</span>
                    </div>
                    <h2 class="text-xl font-bold text-ink">{{ $property->title }}</h2>
                    <p class="text-primary-700 font-bold text-lg mt-1 tabular-nums">{{ \App\Support\PropertyFields::price($property->price) }} {{ auth()->user()->currencySymbol() }} <span class="text-sm text-gray-400 font-normal">{{ $property->purpose === 'rent' ? '/'.($property->price_period === 'yearly' ? 'سنة' : 'شهر') : 'للبيع' }}</span></p>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-5 pt-5 border-t border-gray-100 text-sm">
                        @if ($property->category?->key !== 'commercial')
                            <div><p class="text-gray-400 text-xs">غرف النوم</p><p class="font-semibold text-ink">{{ $property->bedrooms ?? '—' }}</p></div>
                        @endif
                        <div><p class="text-gray-400 text-xs">الحمامات</p><p class="font-semibold text-ink">{{ $property->bathrooms ?? '—' }}</p></div>
                        <div><p class="text-gray-400 text-xs">المساحة</p><p class="font-semibold text-ink">{{ $property->area_size ? $property->area_size.' م²' : '—' }}</p></div>
                        <div><p class="text-gray-400 text-xs">المنطقة</p><p class="font-semibold text-ink">{{ $property->area?->name ?? '—' }}@if ($property->city) <span class="text-xs text-gray-400 font-normal">({{ $property->city->name }})</span>@endif</p></div>
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

            @if (count($photos))
                <x-photo-viewer :title="$property->title" />
            @endif

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

        {{-- الجانب: المالك ومندوب المبيعات والموقع --}}
        <div class="space-y-5">
            <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-ink mb-3">المالك ومندوب المبيعات</h3>
                <div class="space-y-3 text-sm">
                    <div><p class="text-gray-400 text-xs">المالك</p>
                        @if ($property->owner && auth()->user()->can('property_owners.view'))
                            <a href="{{ route('dashboard.owners.show', $property->owner) }}" class="font-medium text-primary-800 hover:underline">{{ $property->owner->name }}</a>
                        @else
                            <p class="font-medium text-ink">{{ $property->owner?->name ?? '—' }}</p>
                        @endif
                    </div>
                    {{-- نسبة العمولة والموقع والحارس والمسؤولون ظاهرة دائماً لكل من يفتح العقار («—» عند عدم التحديد) --}}
                    <div><p class="text-gray-400 text-xs">نسبة العمولة من المالك</p><p class="font-medium text-ink tabular-nums"><bdi dir="ltr">{{ $property->owner_commission_rate !== null ? \App\Support\PropertyFields::percent($property->owner_commission_rate) : '—' }}</bdi></p></div>
                    <div><p class="text-gray-400 text-xs">مندوب المبيعات</p><p class="font-medium text-ink">{{ $property->agent?->name ?? '—' }}</p></div>
                    <div><p class="text-gray-400 text-xs">التصنيف / النوع</p><p class="font-medium text-ink">{{ $property->category?->name }} · {{ $property->unitType?->name }}</p></div>
                    <div><p class="text-gray-400 text-xs">وقت الإضافة</p><p class="font-medium text-ink tabular-nums"><bdi dir="ltr">{{ $property->created_at?->format('Y-m-d H:i') ?? '—' }}</bdi></p></div>
                </div>
            </div>

            @php
                $addr = collect([
                    $property->city?->name,
                    $property->area?->name,
                    $property->building_name ? 'مبنى '.$property->building_name : null,
                    $property->block ? 'قطعة '.$property->block : null,
                    $property->street ? 'شارع '.$property->street : null,
                    $property->building ? 'عمارة '.$property->building : null,
                ])->filter()->implode(' · ');
            @endphp
            <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-ink mb-3">الموقع</h3>
                <p class="text-sm {{ $addr ? 'text-gray-600' : 'text-gray-400' }}">{{ $addr ?: '—' }}</p>
                @if ($property->map_url)
                    <a href="{{ $property->map_url }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-2 rounded-full bg-primary-50 text-primary-800 hover:bg-primary-100 px-4 py-2 text-sm font-semibold transition">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        فتح على خرائط جوجل
                    </a>
                @else
                    <p class="mt-2 text-xs text-gray-400">لا يوجد رابط موقع على خرائط جوجل.</p>
                @endif
            </div>

            @php
                $waLink = fn (string $number) => '<a href="https://wa.me/'.e($number).'" target="_blank" rel="noopener" title="واتساب" class="grid place-items-center w-8 h-8 shrink-0 rounded-full bg-success-soft text-success hover:bg-success hover:text-white transition"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></a>';
            @endphp
            <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-ink mb-1">المسؤولون عن العقار</h3>
                <p class="text-xs text-gray-400 mb-3">من مسؤولي المالك — تُرسل إليهم (ومعهم الحارس) رسائل واتساب الخاصة بالمعاينات</p>
                <ul class="space-y-2.5 text-sm">
                    @forelse ($property->responsibles as $contact)
                        <li class="flex items-center justify-between gap-3">
                            <span class="min-w-0">
                                <span class="block font-medium text-ink truncate">{{ $contact->label($property->owner) }}</span>
                                <bdi dir="ltr" class="block text-xs text-gray-500">{{ $contact->full_phone }}</bdi>
                            </span>
                            {!! $waLink($contact->whatsapp_number) !!}
                        </li>
                    @empty
                        <li class="text-gray-400">لم يُختر مسؤول عن هذا العقار بعد — اختره من مسؤولي المالك في شاشة التعديل.</li>
                    @endforelse
                </ul>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400 mb-2">حارس العقار</p>
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <dl class="min-w-0 space-y-1">
                            <div class="flex items-baseline gap-2"><dt class="text-xs text-gray-400 shrink-0">الاسم:</dt><dd class="font-medium text-ink truncate">{{ $property->guard_name ?: '—' }}</dd></div>
                            <div class="flex items-baseline gap-2"><dt class="text-xs text-gray-400 shrink-0">رقم الحارس:</dt><dd class="font-medium text-ink"><bdi dir="ltr">{{ $property->hasGuard() ? $property->guard_full_phone : '—' }}</bdi></dd></div>
                        </dl>
                        @if ($property->hasGuard()){!! $waLink($property->guard_whatsapp_number) !!}@endif
                    </div>
                </div>
            </div>

            @if ($property->channels->count())
                <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                    <h3 class="font-bold text-ink mb-3">منشور على</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach ($property->channels as $channel)
                            <li class="flex items-center gap-3">
                                <span class="grid place-items-center w-8 h-8 shrink-0 rounded-lg bg-gray-50 border border-gray-100 overflow-hidden">
                                    @if ($channel->icon_url)<img src="{{ $channel->icon_url }}" class="w-full h-full object-contain" alt="">@else<span class="text-xs font-bold text-primary-800">{{ mb_substr($channel->name, 0, 1) }}</span>@endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-ink">{{ $channel->name }} <span class="text-[11px] text-gray-400">({{ $channel->kind === 'website' ? 'موقع' : 'سوشال' }})</span></span>
                                    @if ($channel->pivot->url)<a href="{{ $channel->pivot->url }}" target="_blank" rel="noopener" class="block text-[11px] text-primary-700 hover:underline truncate"><bdi dir="ltr">{{ $channel->pivot->url }}</bdi></a>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection
