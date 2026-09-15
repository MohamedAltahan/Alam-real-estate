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

    $files = $client->filePayload();
    $fileTone = [
        'image' => 'bg-info-soft text-info', 'pdf' => 'bg-danger/10 text-danger',
        'word' => 'bg-primary-50 text-primary-700', 'excel' => 'bg-success-soft text-success', 'file' => 'bg-gray-100 text-gray-500',
    ];
    $kindOf = fn ($ext) => match ($ext) {
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg' => 'image',
        'pdf' => 'pdf', 'doc', 'docx' => 'word', 'xls', 'xlsx', 'csv' => 'excel', default => 'file',
    };
@endphp

@section('content')
<div x-data="{ editOpen: {{ $editOpen ? 'true' : 'false' }}, }">
    @if (session('success'))
        <div class="mb-4 rounded-field bg-success-soft text-success text-sm px-4 py-3">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-field bg-danger/10 text-danger text-sm px-4 py-3">{{ $errors->first() }}</div>
    @endif

    <a href="{{ route('dashboard.clients.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-700 mb-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>العودة للعملاء
    </a>

    <div class="space-y-5">
            {{-- ===== بطاقة العميل ===== --}}
            <section class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-4 min-w-0">
                        <span class="grid place-items-center w-14 h-14 rounded-full bg-primary-100 text-primary-700 font-bold text-xl shrink-0">{{ mb_substr($client->name, 0, 1) }}</span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-ink truncate flex items-center gap-2">
                                {{ $client->name }}
                                @if ($client->is_featured)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-accent-500/15 text-accent-600 px-2.5 py-0.5 text-xs font-bold shrink-0"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>طلب مميز</span>
                                @endif
                            </h2>
                            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                @if ($client->stage)<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" style="color: {{ $client->stage->color }}; background-color: {{ $client->stage->color }}1a"><span class="w-1.5 h-1.5 rounded-full" style="background: {{ $client->stage->color }}"></span>{{ $client->stage->name }}</span>@endif
                                @if ($client->type)<span class="text-xs text-gray-500 bg-gray-100 rounded-full px-2.5 py-1">{{ $client->type->name }}</span>@endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="$dispatch('open-modal', 'client-files')" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 hover:bg-gray-50 text-sm text-gray-700 px-4 py-2">
                            ملفات العميل
                            <span class="grid place-items-center min-w-5 h-5 px-1.5 rounded-full bg-primary-900 text-white text-[11px] font-bold">{{ count($files) }}</span>
                        </button>
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
                        <div><dt class="text-gray-400 text-xs mb-1">{{ $label }}</dt><dd class="text-ink font-medium break-words">@if ($ltr && filled($value))<bdi dir="ltr">{{ $value }}</bdi>@else{{ filled($value) ? $value : '—' }}@endif</dd></div>
                    @endforeach
                </dl>

                {{-- احتياج العقار --}}
                <div class="mt-5 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400 mb-2">احتياج العقار <span class="text-gray-300">(نوع العقار · نوع الوحدة · المحافظة · المنطقة · الغرف · المساحة)</span></p>
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
                                    <th class="text-start font-medium px-3 py-2">واتساب</th>
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
                                                        <a href="{{ $viewing->property ? route('dashboard.properties.show', $viewing->property) : '#' }}" class="block font-semibold text-ink hover:text-primary-700"><bdi dir="ltr">{{ $viewing->property?->reference_code ?: '—' }}</bdi>@if ($viewing->property->building_name)<span class="text-xs font-normal text-gray-500"> — مبنى: {{ $viewing->property->building_name }}</span>@endif</a>
                                                    @else
                                                        <span class="block font-semibold text-ink"><bdi dir="ltr">{{ $viewing->property?->reference_code ?: '—' }}</bdi>@if ($viewing->property->building_name)<span class="text-xs font-normal text-gray-500"> — مبنى: {{ $viewing->property->building_name }}</span>@endif</span>
                                                    @endcan
                                                    <span class="block text-xs text-gray-400 truncate max-w-[220px]">{{ $viewing->property?->title }}</span>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap"><bdi dir="ltr">{{ $viewing->scheduled_at?->format('Y-m-d h:i A') }}</bdi></td>
                                        <td class="px-3 py-2.5 text-gray-600">{{ $viewing->in_person ? 'نعم' : 'لا' }}</td>
                                        <td class="px-3 py-2.5">@include('dashboard.viewings._outcome', ['viewing' => $viewing])</td>
                                        <td class="px-3 py-2.5">@include('dashboard.viewings._wa', ['viewing' => $viewing])</td>
                                        <td class="px-3 py-2.5 max-w-[220px]">@include('dashboard.viewings._notes', ['viewing' => $viewing])</td>
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

    {{-- ===== مودال ملفات العميل ===== --}}
    <x-modal name="client-files" maxWidth="lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div><h3 class="font-bold text-ink">ملفات العميل</h3><p class="text-xs text-gray-400">{{ $client->name }}</p></div>
            <button type="button" @click="$dispatch('close-modal', 'client-files')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
        </div>
        <div class="p-6 max-h-[65vh] overflow-y-auto space-y-2">
            @forelse ($files as $file)
                @php $kind = $kindOf($file['ext']); @endphp
                <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-gray-100 hover:border-primary-200 hover:bg-primary-50/40 px-3 py-2.5 transition">
                    <span class="grid place-items-center w-10 h-10 shrink-0 rounded-lg text-[10px] font-bold uppercase {{ $fileTone[$kind] }}">{{ $file['ext'] ?: 'file' }}</span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-semibold text-ink truncate">{{ $file['name'] }}</span><span class="block text-[11px] text-gray-400">{{ $file['size'] > 1048576 ? number_format($file['size'] / 1048576, 1).' MB' : max(1, round($file['size'] / 1024)).' KB' }}</span></span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400 shrink-0"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
                </a>
            @empty
                <p class="text-center text-sm text-gray-400 py-10">لا توجد ملفات مرفوعة لهذا العميل.@can('clients.edit') أضفها من «تعديل البيانات».@endcan</p>
            @endforelse
        </div>
    </x-modal>

    @include('dashboard.viewings._wa-modal')
    @include('dashboard.viewings._notes-modal')

    @can('clients.edit')
        <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-3" role="dialog" @keydown.escape.window="editOpen = false">
            <div class="absolute inset-0 bg-primary-950/50" @click="editOpen = false"></div>
            <div class="relative w-full max-w-[min(96vw,88rem)] bg-white rounded-card shadow-2xl max-h-[94vh] overflow-y-auto">
                <div class="sticky top-0 z-10 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-100"><h3 class="font-bold text-ink">تعديل بيانات العميل</h3><button type="button" @click="editOpen = false" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
                <form method="POST" action="{{ route('dashboard.clients.update', $client) }}" enctype="multipart/form-data">@csrf @method('PUT')<div class="p-6">@include('dashboard.clients._form', ['client' => $client, 'form' => $form])</div><div class="sticky bottom-0 flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/95"><button type="button" @click="editOpen = false" class="rounded-full px-4 py-2.5 text-sm text-gray-600">إلغاء</button><button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">حفظ التعديلات</button></div></form>
            </div>
        </div>
    @endcan
</div>
@endsection
