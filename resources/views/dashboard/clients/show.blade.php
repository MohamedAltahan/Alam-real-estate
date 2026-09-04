@extends('layouts.dashboard')

@section('title', $client->name)
@section('page-title', 'ملف العميل')

@php
    use App\Support\ClientFields;
    use App\Support\ClientFormData;

    $typeMeta = [
        'call' => ['label' => 'مكالمة', 'icon' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>'],
        'meeting' => ['label' => 'مقابلة', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
        'whatsapp' => ['label' => 'واتساب', 'icon' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>'],
        'email' => ['label' => 'بريد', 'icon' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>'],
        'note' => ['label' => 'ملاحظة', 'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h6"/>'],
    ];
    $editOpen = ClientFormData::hasFormErrors($errors);
@endphp

@section('content')
<div x-data="{ editOpen: {{ $editOpen ? 'true' : 'false' }}, propertySearch: '', selectedProperty: null }">
    @if (session('success'))
        <div class="mb-4 rounded-field bg-success-soft text-success text-sm px-4 py-3">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-field bg-danger/10 text-danger text-sm px-4 py-3">{{ $errors->first() }}</div>
    @endif

    <a href="{{ route('dashboard.clients.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-700 mb-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>العودة للعملاء
    </a>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 space-y-5">
            {{-- ===== بطاقة العميل ===== --}}
            <section class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-4 min-w-0">
                        <span class="grid place-items-center w-14 h-14 rounded-full bg-primary-100 text-primary-700 font-bold text-xl shrink-0">{{ mb_substr($client->name, 0, 1) }}</span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-ink truncate">{{ $client->name }}</h2>
                            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                @if ($client->stage)<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" style="color: {{ $client->stage->color }}; background-color: {{ $client->stage->color }}1a"><span class="w-1.5 h-1.5 rounded-full" style="background: {{ $client->stage->color }}"></span>{{ $client->stage->name }}</span>@endif
                                @if ($client->type)<span class="text-xs text-gray-500 bg-gray-100 rounded-full px-2.5 py-1">{{ $client->type->name }}</span>@endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @can('clients.edit')<button type="button" @click="editOpen = true" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 hover:bg-gray-50 text-sm text-gray-700 px-4 py-2">تعديل البيانات</button>@endcan
                        @if ($client->phone)<a href="https://wa.me/{{ $client->whatsapp_number }}" target="_blank" rel="noopener" class="rounded-full bg-success/10 text-success hover:bg-success/20 text-sm px-4 py-2 font-medium">واتساب</a>@endif
                    </div>
                </div>

                <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-5 mt-6 pt-5 border-t border-gray-100 text-sm">
                    @foreach ([
                        ['الهاتف', $client->full_phone, true], ['البريد', $client->email, true],
                        ['طريقة التواصل', ClientFields::enumLabel('preferred_contact', $client->preferred_contact), false],
                        ['الجنسية', $client->nationality, false],
                        ['الحالة الاجتماعية', ClientFields::enumLabel('social_status', $client->social_status), false],
                        ['عدد الأفراد', $client->household_size, false], ['مكان العمل', $client->workplace, false],
                        ['مندوب المبيعات', $client->agent?->name, false],
                        ['المصدر', $client->source?->name, false], ['سجّل البيانات', $client->recordedBy?->name, false],
                        ['تاريخ التسجيل', $client->created_at?->format('Y-m-d'), true],
                    ] as [$label, $value, $ltr])
                        <div><dt class="text-gray-400 text-xs mb-1">{{ $label }}</dt><dd class="text-ink font-medium break-words" @if ($ltr && filled($value)) dir="ltr" @endif>{{ filled($value) ? $value : '—' }}</dd></div>
                    @endforeach
                </dl>

                {{-- احتياج العقار --}}
                <div class="mt-5 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400 mb-2">احتياج العقار <span class="text-gray-300">(نوع الوحدة المطلوبة · المحافظة · المنطقة)</span></p>
                    @forelse ($client->needs as $need)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 text-primary-800 text-xs font-medium px-3 py-1.5 me-1.5 mb-1.5">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            {{ $need->describe() }}
                        </span>
                    @empty
                        <p class="text-sm text-gray-400">لم تُحدَّد احتياجات بعد.</p>
                    @endforelse
                </div>

                @if ($client->notes)<div class="mt-4 pt-4 border-t border-gray-100"><p class="text-xs text-gray-400 mb-1">ملاحظات</p><p class="text-sm text-gray-600 whitespace-pre-line">{{ $client->notes }}</p></div>@endif
            </section>

            {{-- ===== المعاينات ===== --}}
            <section class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h3 class="font-bold text-ink">المعاينات <span class="text-gray-400 font-normal text-sm">({{ $client->viewings->count() }})</span></h3>
                    @can('clients.edit')<button type="button" @click="editOpen = true" class="text-sm text-primary-700 font-semibold hover:underline">إضافة / تعديل المعاينات</button>@endcan
                </div>
                @if ($client->viewings->isEmpty())
                    <p class="text-center text-sm text-gray-400 py-6">لا توجد معاينات مسجلة.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-gray-500 text-xs border-b border-gray-100">
                                    <th class="text-start font-medium px-3 py-2 w-10">#</th>
                                    <th class="text-start font-medium px-3 py-2">العقار</th>
                                    <th class="text-start font-medium px-3 py-2">الموعد</th>
                                    <th class="text-start font-medium px-3 py-2">حضوري</th>
                                    <th class="text-start font-medium px-3 py-2">النتيجة</th>
                                    <th class="text-start font-medium px-3 py-2">ملاحظة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($client->viewings as $viewing)
                                    <tr>
                                        <td class="px-3 py-2.5 text-gray-400 tabular-nums">{{ $loop->iteration }}</td>
                                        <td class="px-3 py-2.5">
                                            <div class="flex items-center gap-2.5">
                                                <span class="w-11 h-9 rounded-lg bg-gray-100 overflow-hidden shrink-0">@if ($viewing->property?->cover_url)<img src="{{ $viewing->property->cover_url }}" class="w-full h-full object-cover" alt="">@endif</span>
                                                <span class="min-w-0">
                                                    @can('properties.view')
                                                        <a href="{{ $viewing->property ? route('dashboard.properties.show', $viewing->property) : '#' }}" class="block font-semibold text-ink hover:text-primary-700" dir="ltr">{{ $viewing->property?->reference_code ?: '—' }}</a>
                                                    @else
                                                        <span class="block font-semibold text-ink" dir="ltr">{{ $viewing->property?->reference_code ?: '—' }}</span>
                                                    @endcan
                                                    <span class="block text-xs text-gray-400 truncate max-w-[220px]">{{ $viewing->property?->title }}</span>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap" dir="ltr">{{ $viewing->scheduled_at?->format('Y-m-d h:i A') }}</td>
                                        <td class="px-3 py-2.5 text-gray-600">{{ $viewing->in_person ? 'نعم' : 'لا' }}</td>
                                        <td class="px-3 py-2.5">
                                            @can('clients.edit')
                                                <form method="POST" action="{{ route('dashboard.viewings.outcome', $viewing) }}">
                                                    @csrf @method('PATCH')
                                                    <select name="outcome" onchange="this.form.requestSubmit()" class="appearance-none rounded-full border-0 ps-3 pe-8 py-1 text-xs font-semibold cursor-pointer {{ ClientFields::outcomeTone($viewing->outcome) }}">
                                                        @foreach (ClientFields::OUTCOMES as $value => $text)<option value="{{ $value }}" @selected($viewing->outcome === $value)>{{ $text }}</option>@endforeach
                                                    </select>
                                                </form>
                                            @else
                                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ ClientFields::outcomeTone($viewing->outcome) }}">{{ ClientFields::outcomeLabel($viewing->outcome) }}</span>
                                            @endcan
                                        </td>
                                        <td class="px-3 py-2.5 text-xs text-gray-500 max-w-[200px]"><span class="block truncate" title="{{ $viewing->notes }}">{{ $viewing->notes ?: '—' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- ===== سجل التواصل ===== --}}
            <section class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-ink mb-4">سجل التواصل <span class="text-gray-400 font-normal text-sm">({{ $client->interactions->count() }})</span></h3>
                @can('clients.edit')
                    <form method="POST" action="{{ route('dashboard.clients.interactions.store', $client) }}" class="rounded-field bg-gray-50 border border-gray-100 p-4 mb-5">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div><label class="block text-xs font-medium text-gray-600 mb-1">نوع التواصل</label><select name="type" class="w-full rounded-field border border-gray-200 bg-white px-3 py-2 text-sm">@foreach (['call', 'meeting', 'whatsapp', 'email'] as $key)<option value="{{ $key }}">{{ $typeMeta[$key]['label'] }}</option>@endforeach</select></div>
                            <div><label class="block text-xs font-medium text-gray-600 mb-1">غيّر الحالة إلى</label><select name="stage_id" class="w-full rounded-field border border-gray-200 bg-white px-3 py-2 text-sm"><option value="">— بدون تغيير —</option>@foreach ($stages as $stage)<option value="{{ $stage->id }}">{{ $stage->name }}</option>@endforeach</select></div>
                        </div>
                        <textarea name="notes" rows="2" placeholder="تفاصيل المكالمة أو المقابلة..." class="w-full mt-3 rounded-field border border-gray-200 bg-white px-3 py-2 text-sm"></textarea>
                        <div class="flex justify-end mt-3"><button class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2 text-sm">تسجيل التواصل</button></div>
                    </form>
                @endcan
                <div class="space-y-4">
                    @forelse ($client->interactions as $interaction)
                        @php $meta = $typeMeta[$interaction->type] ?? $typeMeta['call']; @endphp
                        <article class="flex gap-3">
                            <span class="grid place-items-center w-9 h-9 rounded-full bg-primary-50 text-primary-700 shrink-0"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $meta['icon'] !!}</svg></span>
                            <div class="flex-1 min-w-0 pb-4 border-b border-gray-50">
                                <div class="flex items-center gap-2 flex-wrap"><strong class="text-sm text-ink">{{ $meta['label'] }}</strong>@if ($interaction->stage)<span class="text-xs rounded-full px-2 py-0.5" style="color: {{ $interaction->stage->color }}; background-color: {{ $interaction->stage->color }}1a">{{ $interaction->stage->name }}</span>@endif<span class="text-xs text-gray-400 ms-auto">{{ $interaction->occurred_at?->format('Y-m-d H:i') }}</span></div>
                                @if ($interaction->notes)<p class="text-sm text-gray-600 mt-1 whitespace-pre-line">{{ $interaction->notes }}</p>@endif
                                <p class="text-xs text-gray-400 mt-1">بواسطة {{ $interaction->user?->name ?? 'النظام' }}</p>
                            </div>
                        </article>
                    @empty
                        <p class="text-center text-sm text-gray-400 py-6">لا يوجد سجل تواصل بعد.</p>
                    @endforelse
                </div>
            </section>

            {{-- ===== سجل التعديلات ===== --}}
            @include('dashboard.partials.audit', ['auditLogs' => $auditLogs])
        </div>

        <aside>
            <section class="rounded-card bg-white border border-gray-100 shadow-sm p-5 xl:sticky xl:top-24">
                <h3 class="font-bold text-ink mb-4">عقارات العميل <span class="text-gray-400 font-normal text-sm">({{ $client->properties->count() }})</span></h3>
                <div class="space-y-2 mb-5">
                    @forelse ($client->properties as $property)
                        <div class="flex items-center gap-3 rounded-2xl border border-gray-100 p-3">
                            <span class="w-12 h-11 rounded-xl bg-gray-100 overflow-hidden shrink-0">@if ($property->cover_url)<img src="{{ $property->cover_url }}" class="w-full h-full object-cover" alt="">@endif</span>
                            <div class="flex-1 min-w-0">
                                @can('properties.view')
                                    <a href="{{ route('dashboard.properties.show', $property) }}" class="text-sm font-semibold text-ink hover:text-primary-700" dir="ltr">{{ $property->reference_code }}</a>
                                @else
                                    <span class="text-sm font-semibold text-ink" dir="ltr">{{ $property->reference_code }}</span>
                                @endcan
                                <p class="text-xs text-gray-400 truncate">
                                    {{ ClientFields::RELATIONS[$property->pivot->relation] ?? 'مرتبط' }} · {{ $property->status?->name ?: 'بدون حالة' }}
                                </p>
                            </div>
                            @can('clients.edit')
                                <div class="flex flex-col items-end gap-1.5 shrink-0">
                                    <form method="POST" action="{{ route('dashboard.clients.properties.detach', [$client, $property]) }}" onsubmit="return confirm('هل تريد إزالة العقار من سجل العميل؟')">@csrf @method('DELETE')<button class="grid place-items-center w-8 h-8 rounded-full text-gray-300 hover:text-danger hover:bg-danger/10" title="إلغاء الربط"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></form>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <p class="text-center text-sm text-gray-400 py-4">لا توجد عقارات مرتبطة.</p>
                    @endforelse
                </div>

                @can('clients.edit')
                    <form method="POST" action="{{ route('dashboard.clients.properties.attach', $client) }}" class="border-t border-gray-100 pt-4 space-y-3">
                        @csrf
                        <label class="block text-xs font-semibold text-gray-600">ابحث واختر العقار</label>
                        <div class="relative"><svg class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><input type="search" x-model="propertySearch" placeholder="الرقم، العنوان، المنطقة..." class="w-full rounded-field border border-gray-200 bg-gray-50 ps-9 pe-3 py-2.5 text-sm"></div>
                        <input type="hidden" name="property_id" :value="selectedProperty?.id || ''" required>
                        <div class="max-h-80 overflow-y-auto space-y-2 pe-1">
                            @foreach ($linkable as $property)
                                @php
                                    $alreadyLinked = $client->properties->contains('id', $property->id);
                                    $blockedReason = $alreadyLinked
                                        ? 'مضاف بالفعل لهذا العميل'
                                        : ($property->status?->key === 'sold' ? 'لا يمكن الإضافة — العقار مباع' : null);
                                    $searchText = mb_strtolower(collect([$property->reference_code, $property->title, $property->area?->name, $property->unitType?->name])->filter()->implode(' '));
                                @endphp
                                <button type="button" x-show="!propertySearch || @js($searchText).includes(propertySearch.toLowerCase())" @if (! $blockedReason) @click="selectedProperty = @js(['id' => $property->id, 'reference' => $property->reference_code])" @endif @disabled($blockedReason)
                                        :class="selectedProperty?.id === {{ $property->id }} ? 'border-primary-700 bg-primary-50 ring-1 ring-primary-700' : 'border-gray-100 bg-white'" class="w-full flex items-center gap-3 rounded-2xl border p-2.5 text-start transition disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span class="w-14 h-12 rounded-xl bg-gray-100 overflow-hidden shrink-0">@if ($property->cover_url)<img src="{{ $property->cover_url }}" class="w-full h-full object-cover" alt="">@endif</span>
                                    <span class="min-w-0 flex-1"><span class="flex justify-between gap-2"><strong class="text-sm text-ink" dir="ltr">{{ $property->reference_code }}</strong><span class="text-xs font-semibold text-primary-800">{{ number_format((float) $property->price) }} د.ك</span></span><span class="block text-xs text-gray-500 truncate">{{ $property->title }} · {{ $property->unitType?->name ?: 'بدون نوع' }}</span><span class="block text-[11px] {{ $blockedReason ? 'text-danger font-semibold' : 'text-gray-400' }}">{{ $blockedReason ?: (($property->area?->name ?: 'بدون منطقة').' · '.($property->status?->name ?: 'بدون حالة')) }}</span></span>
                                </button>
                            @endforeach
                        </div>
                        <p x-show="selectedProperty" class="text-xs text-success font-semibold">تم اختيار <span dir="ltr" x-text="selectedProperty?.reference"></span></p>
                        @error('property_id')<p class="text-xs text-danger font-semibold">{{ $message }}</p>@enderror
                        <select name="relation" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><option value="interested">مهتم</option><option value="viewed">تمت معاينة العقار</option></select>
                        <button :disabled="!selectedProperty" class="w-full rounded-full bg-primary-900 hover:bg-primary-800 disabled:bg-gray-200 disabled:text-gray-400 text-white font-medium py-2.5 text-sm">إضافة العقار للعميل</button>
                    </form>
                @endcan
            </section>
        </aside>
    </div>

    @can('clients.edit')
        <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" @keydown.escape.window="editOpen = false">
            <div class="absolute inset-0 bg-primary-950/50" @click="editOpen = false"></div>
            <div class="relative w-full max-w-6xl bg-white rounded-card shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="sticky top-0 z-10 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-100"><h3 class="font-bold text-ink">تعديل بيانات العميل</h3><button type="button" @click="editOpen = false" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
                <form method="POST" action="{{ route('dashboard.clients.update', $client) }}">@csrf @method('PUT')<div class="p-6">@include('dashboard.clients._form', ['client' => $client, 'form' => $form])</div><div class="sticky bottom-0 flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/95"><button type="button" @click="editOpen = false" class="rounded-full px-4 py-2.5 text-sm text-gray-600">إلغاء</button><button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">حفظ التعديلات</button></div></form>
            </div>
        </div>
    @endcan
</div>
@endsection
