@extends('layouts.dashboard')

@section('title', $record->name)
@section('page-title', 'ميداني')

@php
    use App\Support\FieldOwnerFields;

    $photos = $record->getMedia('photos');
    $canConvertOwner = ! $record->isOwnerConverted() && auth()->user()->can('property_owners.create');
    $canConvertProperty = ! $record->isPropertyConverted() && auth()->user()->can('properties.create');
    $mapState = ['lat' => (string) ($record->latitude ?? ''), 'lng' => (string) ($record->longitude ?? '')];

    $btnWhite = 'inline-flex items-center gap-2 rounded-full bg-white text-primary-900 hover:bg-accent-100 font-bold px-4 h-10 text-sm transition';
    $btnGold = 'inline-flex items-center gap-2 rounded-full bg-accent-500 text-primary-900 hover:bg-accent-500/90 font-bold px-4 h-10 text-sm transition';
    $btnOutline = 'inline-flex items-center gap-2 rounded-full border border-white/40 hover:bg-white/10 text-white font-semibold px-4 h-10 text-sm transition';
    $doneBadge = 'inline-flex items-center gap-1.5 rounded-full bg-white/15 text-white px-3 h-10 text-sm font-semibold';
@endphp

@section('content')
<div x-data="{ useExisting: false }">
    <x-flash />

    <a href="{{ route('dashboard.field-owners.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-700 mb-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        العودة لقائمة ميداني
    </a>

    {{-- ===== بطاقة الزيارة ===== --}}
    <section class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden mb-6">
        <div class="navy-gradient px-6 py-5 text-white flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs text-white/60 mb-1">زيارة ميدانية #{{ $record->id }}</p>
                <h2 class="text-2xl font-bold truncate">{{ $record->name }}</h2>
                <div class="flex flex-wrap items-center gap-2 mt-2 text-xs">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 font-semibold {{ FieldOwnerFields::stageTone($record->stage) }}">{{ FieldOwnerFields::stageLabel($record->stage) }}</span>
                    <span class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-1">{{ FieldOwnerFields::methodLabel($record->contact_method) }}</span>
                    <span class="text-white/70">المندوب: {{ $record->creator?->name ?: '—' }} · {{ $record->created_at->format('Y/m/d') }}</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @can('field_owners.edit')
                    <a href="{{ route('dashboard.field-owners.edit', $record) }}" class="{{ $btnOutline }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                        تعديل
                    </a>
                @endcan

                {{-- المالك --}}
                @if ($record->isOwnerConverted())
                    @can('property_owners.view')
                        <a href="{{ route('dashboard.owners.show', $record->converted_owner_id) }}" class="{{ $btnWhite }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                            عرض المالك
                        </a>
                    @else
                        <span class="{{ $doneBadge }}">تم الحفظ كمالك</span>
                    @endcan
                @elseif ($canConvertOwner)
                    <button type="button" @click="$dispatch('open-modal', 'convert-owner')" class="{{ $btnWhite }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="4.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>
                        حفظ كمالك
                    </button>
                @endif

                {{-- العقار --}}
                @if ($record->isPropertyConverted())
                    @can('properties.view')
                        <a href="{{ route('dashboard.properties.show', $record->converted_property_id) }}" class="{{ $btnGold }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                            عرض العقار
                        </a>
                    @else
                        <span class="{{ $doneBadge }}">تم الحفظ كعقار</span>
                    @endcan
                @elseif ($canConvertProperty)
                    <a href="{{ route('dashboard.properties.create', ['field_owner' => $record->id]) }}" class="{{ $btnGold }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01"/></svg>
                        حفظ كعقار
                    </a>
                @endif

                @can('field_owners.delete')
                    <button type="button" @click="$dispatch('open-modal', 'field-owner-delete')" title="حذف الزيارة"
                            class="grid place-items-center w-10 h-10 rounded-full border border-white/40 text-white/80 hover:bg-danger hover:border-danger hover:text-white transition">
                        <x-icon.trash />
                    </button>
                @endcan
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-5 text-sm">
            <div class="md:col-span-2">
                <p class="text-xs text-gray-400 mb-2">المسؤولون</p>
                <ul class="space-y-2">
                    @forelse ($record->contacts as $contact)
                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="font-semibold text-ink">{{ $contact->name ?: '—' }}</span>
                            @if ($contact->role)<span class="text-xs rounded-full bg-gray-100 text-gray-600 px-2 py-0.5">{{ $contact->role }}</span>@endif
                            <a href="https://wa.me/{{ $contact->whatsapp_number }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline tabular-nums" dir="ltr">{{ $contact->full_phone }}</a>
                        </li>
                    @empty
                        <li class="font-semibold text-ink"><bdi dir="ltr">{{ $record->full_phone ?: '—' }}</bdi></li>
                    @endforelse
                </ul>
            </div>
            <div><p class="text-xs text-gray-400 mb-1">طريقة التواصل</p><p class="font-semibold text-ink">{{ FieldOwnerFields::methodLabel($record->contact_method) }}</p></div>
            <div><p class="text-xs text-gray-400 mb-1">المرحلة</p><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ FieldOwnerFields::stageTone($record->stage) }}">{{ FieldOwnerFields::stageLabel($record->stage) }}</span></div>
            <div><p class="text-xs text-gray-400 mb-1">رقم العقار</p><p class="font-semibold text-ink"><bdi dir="ltr">{{ $record->property_number ?: '—' }}</bdi></p></div>
            <div><p class="text-xs text-gray-400 mb-1">المنطقة</p><p class="font-semibold text-ink">{{ collect([$record->area?->name, $record->city?->name])->filter()->implode(' — ') ?: '—' }}</p></div>
            <div class="md:col-span-2"><p class="text-xs text-gray-400 mb-1">العنوان</p><p class="font-semibold text-ink">{{ $record->address ?: '—' }}</p></div>
            <div class="md:col-span-2 xl:col-span-4"><p class="text-xs text-gray-400 mb-1">الملاحظات</p><p class="text-ink whitespace-pre-line">{{ $record->notes ?: '—' }}</p></div>
        </div>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        {{-- ===== الموقع ===== --}}
        <section class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="font-bold text-ink">الموقع على الخريطة</h3>
                @if ($record->hasLocation())
                    <a href="{{ $record->mapsUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm text-primary-700 hover:underline">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        افتح في خرائط جوجل
                    </a>
                @endif
            </div>
            @if ($record->hasLocation())
                <div x-data="fieldOwnerMap(@js($mapState))" dir="ltr" class="h-72 rounded-2xl border border-gray-200 overflow-hidden bg-gray-100"></div>
                <p class="text-xs text-gray-400 mt-2 tabular-nums"><bdi dir="ltr">{{ $record->latitude }}, {{ $record->longitude }}</bdi></p>
            @else
                <p class="text-sm text-gray-400 py-10 text-center">لم يُحدَّد موقع على الخريطة.</p>
            @endif
        </section>

        {{-- ===== الصور ===== --}}
        <section class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
            <h3 class="font-bold text-ink mb-4">صور العقار <span class="text-sm font-normal text-gray-400">({{ $photos->count() }})</span></h3>
            @if ($photos->isNotEmpty())
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach ($photos as $media)
                        <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener" class="block aspect-[4/3] rounded-xl overflow-hidden border border-gray-100 bg-gray-100">
                            <img src="{{ $media->hasGeneratedConversion('web') ? $media->getUrl('web') : $media->getUrl() }}" class="w-full h-full object-cover" alt="">
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 py-10 text-center">لا توجد صور.</p>
            @endif
        </section>
    </div>

    {{-- ===== حفظ كمالك ===== --}}
    @if ($canConvertOwner)
        <x-modal name="convert-owner">
            <form action="{{ route('dashboard.field-owners.convert-owner', $record) }}" method="POST">
                @csrf
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-ink">حفظ كمالك في شاشة الملاك</h3>
                    <button type="button" @click="$dispatch('close-modal', 'convert-owner')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>

                <div class="p-6 space-y-5">
                    <div class="rounded-field bg-gray-50 border border-gray-100 p-4 space-y-1.5">
                        <p class="font-bold text-ink">{{ $record->name }}</p>
                        <p class="text-xs text-gray-500"><span dir="ltr">{{ $record->full_phone }}</span> · {{ $record->contacts->count() }} مسؤول</p>
                        @if ($record->area || $record->address)
                            <p class="text-xs text-gray-500">{{ collect([$record->area?->name, $record->address])->filter()->implode(' — ') }}</p>
                        @endif
                    </div>

                    @if ($duplicate)
                        <div class="rounded-field bg-warning-soft border border-warning/20 p-4">
                            <p class="text-sm font-bold text-warning mb-1">يوجد مالك مسجَّل بنفس رقم الهاتف</p>
                            <p class="text-xs text-gray-600 mb-3">{{ $duplicate->name }} — <span dir="ltr">{{ $duplicate->full_phone }}</span></p>
                            <label class="flex items-center gap-2 text-sm text-ink cursor-pointer select-none">
                                <input type="checkbox" x-model="useExisting">
                                اربط الزيارة بالمالك الموجود بدل إنشاء مالك جديد
                            </label>
                            <input type="hidden" name="existing_owner_id" :value="useExisting ? '{{ $duplicate->id }}' : ''">
                        </div>
                    @endif

                    <p class="text-xs text-gray-500 leading-relaxed" x-show="! useExisting">
                        سيُنشأ مالك جديد باسم أول مسؤول ورقمه، وتُنقل بقية المسؤولين كأرقام تواصل،
                        مع المنطقة والعنوان والملاحظات — ويظهر فورًا في شاشة ملاك العقارات.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                    <button type="button" @click="$dispatch('close-modal', 'convert-owner')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                    <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm"
                            x-text="useExisting ? 'اربط بالمالك الموجود' : 'إنشاء المالك'"></button>
                </div>
            </form>
        </x-modal>
    @endif

    {{-- ===== حذف الزيارة ===== --}}
    @can('field_owners.delete')
        <x-modal name="field-owner-delete" maxWidth="md">
            <div class="p-6 text-center">
                <span class="grid place-items-center w-12 h-12 rounded-full bg-danger/10 text-danger mx-auto mb-4"><x-icon.trash size="24" /></span>
                <h3 class="font-bold text-ink mb-1">حذف الزيارة</h3>
                <p class="text-sm text-gray-500 mb-6">هل أنت متأكد من حذف زيارة "<span class="font-semibold text-ink">{{ $record->name }}</span>"؟ المالك أو العقار المحوَّلان لن يُحذفا.</p>
                <form action="{{ route('dashboard.field-owners.destroy', $record) }}" method="POST" class="flex items-center justify-center gap-3">
                    @csrf @method('DELETE')
                    <button type="button" @click="$dispatch('close-modal', 'field-owner-delete')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 border border-gray-200 hover:bg-gray-100">إلغاء</button>
                    <button type="submit" class="rounded-full bg-danger hover:bg-danger/90 text-white font-semibold px-5 py-2.5 text-sm">نعم، احذف</button>
                </form>
            </div>
        </x-modal>
    @endcan
</div>
@endsection

@if ($record->hasLocation())
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    @endpush

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endpush
@endif
