@extends('layouts.dashboard')

@section('title', $client->name)
@section('page-title', 'ملف العميل')

@php
    $ratingTone = is_null($client->rating) ? 'text-gray-400 bg-gray-100'
        : ($client->rating >= 70 ? 'text-success bg-success-soft' : ($client->rating >= 40 ? 'text-warning bg-warning-soft' : 'text-danger bg-danger-soft'));
    $typeMeta = [
        'call' => ['label' => 'مكالمة', 'icon' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>'],
        'meeting' => ['label' => 'مقابلة', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
        'whatsapp' => ['label' => 'واتساب', 'icon' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>'],
        'email' => ['label' => 'بريد', 'icon' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>'],
    ];
@endphp

@section('content')
<div x-data="{ editOpen: {{ $errors->hasAny(['name','phone','email','rating','area_id','type_id','agent_id','source_id']) ? 'true' : 'false' }} }">

    @if (session('success'))
        <div class="mb-4 rounded-field bg-success-soft text-success text-sm px-4 py-3 flex items-center gap-2">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <a href="{{ route('dashboard.clients.index') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-primary-700 mb-4">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        العودة للعملاء
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- العمود الرئيسي --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- بطاقة بيانات العميل --}}
            <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-4 min-w-0">
                        <span class="grid place-items-center w-14 h-14 rounded-full bg-primary-100 text-primary-700 font-bold text-xl shrink-0">{{ mb_substr($client->name, 0, 1) }}</span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-ink truncate">{{ $client->name }}</h2>
                            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                @if ($client->stage)
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium" style="color: {{ $client->stage->color }}; background-color: {{ $client->stage->color }}1a;">
                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $client->stage->color }}"></span>{{ $client->stage->name }}
                                    </span>
                                @endif
                                <span class="inline-block rounded-full px-2.5 py-1 text-xs font-semibold {{ $ratingTone }}">التقييم {{ is_null($client->rating) ? '—' : $client->rating.'%' }}</span>
                                @if ($client->type)<span class="text-xs text-gray-500 bg-gray-100 rounded-full px-2.5 py-1">{{ $client->type->name }}</span>@endif
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @can('clients.edit')
                            <button @click="editOpen = true" class="inline-flex items-center gap-1.5 rounded-field border border-gray-200 hover:bg-gray-50 text-sm text-gray-700 px-3 py-2">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg>
                                تعديل
                            </button>
                        @endcan
                        @if ($client->phone)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $client->phone) }}" target="_blank"
                               class="inline-flex items-center gap-1.5 rounded-field bg-success/10 text-success hover:bg-success/20 text-sm px-3 py-2 font-medium">واتساب</a>
                        @endif
                    </div>
                </div>

                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-5 border-t border-gray-100 text-sm">
                    <div><dt class="text-gray-400 text-xs mb-0.5">الهاتف</dt><dd class="text-ink font-medium" dir="ltr">{{ $client->phone }}</dd></div>
                    <div><dt class="text-gray-400 text-xs mb-0.5">البريد</dt><dd class="text-ink font-medium truncate" dir="ltr">{{ $client->email ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400 text-xs mb-0.5">المنطقة</dt><dd class="text-ink font-medium">{{ $client->area?->name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400 text-xs mb-0.5">الوكيل</dt><dd class="text-ink font-medium">{{ $client->agent?->name ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400 text-xs mb-0.5">المصدر</dt><dd class="text-ink font-medium">{{ $client->source?->name ?: '—' }}</dd></div>
                </dl>
                @if ($client->notes)
                    <div class="mt-4 pt-4 border-t border-gray-100"><p class="text-xs text-gray-400 mb-1">ملاحظات</p><p class="text-sm text-gray-600">{{ $client->notes }}</p></div>
                @endif
            </div>

            {{-- سجل التواصل --}}
            <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-ink mb-4">سجل التواصل <span class="text-gray-400 font-normal text-sm">({{ $client->interactions->count() }})</span></h3>

                {{-- نموذج تسجيل تواصل --}}
                @can('clients.edit')
                    <form method="POST" action="{{ route('dashboard.clients.interactions.store', $client) }}" class="rounded-field bg-gray-50 border border-gray-100 p-4 mb-5">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">نوع التواصل</label>
                                <select name="type" class="w-full rounded-field border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500">
                                    @foreach ($typeMeta as $k => $m)<option value="{{ $k }}">{{ $m['label'] }}</option>@endforeach
                                </select>
                                @error('type')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">غيّر الحالة إلى (اختياري)</label>
                                <select name="stage_id" class="w-full rounded-field border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500">
                                    <option value="">— بدون تغيير —</option>
                                    @foreach ($stages as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <textarea name="notes" rows="2" placeholder="اكتب اللي تم في المكالمة أو المقابلة..."
                                  class="w-full mt-3 rounded-field border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-none focus:border-primary-500"></textarea>
                        <div class="flex justify-end mt-3">
                            <button class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2 text-sm">تسجيل التواصل</button>
                        </div>
                    </form>
                @endcan

                {{-- الخط الزمني --}}
                <div class="space-y-4">
                    @forelse ($client->interactions as $it)
                        @php $m = $typeMeta[$it->type] ?? $typeMeta['call']; @endphp
                        <div class="flex gap-3">
                            <span class="grid place-items-center w-9 h-9 rounded-full bg-primary-50 text-primary-700 shrink-0">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $m['icon'] !!}</svg>
                            </span>
                            <div class="flex-1 min-w-0 pb-4 border-b border-gray-50 last:border-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-sm text-ink">{{ $m['label'] }}</span>
                                    @if ($it->stage)<span class="text-xs rounded-full px-2 py-0.5" style="color: {{ $it->stage->color }}; background-color: {{ $it->stage->color }}1a;">→ {{ $it->stage->name }}</span>@endif
                                    <span class="text-xs text-gray-400 ms-auto">{{ optional($it->occurred_at)->format('Y-m-d H:i') }}</span>
                                </div>
                                @if ($it->notes)<p class="text-sm text-gray-600 mt-1">{{ $it->notes }}</p>@endif
                                <p class="text-xs text-gray-400 mt-1">بواسطة {{ $it->user?->name ?? 'النظام' }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-sm text-gray-400 py-6">لا يوجد سجل تواصل بعد.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- العمود الجانبي: عقارات العميل --}}
        <div class="space-y-5">
            <div class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-ink mb-4">عقارات العميل <span class="text-gray-400 font-normal text-sm">({{ $client->properties->count() }})</span></h3>

                <div class="space-y-2 mb-4">
                    @forelse ($client->properties as $p)
                        <div class="flex items-center gap-3 rounded-field border border-gray-100 p-3">
                            <span class="grid place-items-center w-9 h-9 rounded-lg bg-accent-100 text-accent-700 shrink-0">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/></svg>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-ink" dir="ltr">{{ $p->reference_code }}</p>
                                <p class="text-xs text-gray-400">{{ $p->pivot->relation ?: 'مرتبط' }}@if($p->status) · {{ $p->status->name }}@endif</p>
                            </div>
                            @can('clients.edit')
                                <form method="POST" action="{{ route('dashboard.clients.properties.detach', [$client, $p]) }}">
                                    @csrf @method('DELETE')
                                    <button class="text-gray-300 hover:text-danger" title="إلغاء الربط">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-center text-sm text-gray-400 py-4">لا توجد عقارات مرتبطة.</p>
                    @endforelse
                </div>

                @can('clients.edit')
                    <form method="POST" action="{{ route('dashboard.clients.properties.attach', $client) }}" class="border-t border-gray-100 pt-4 space-y-2">
                        @csrf
                        <select name="property_id" required class="w-full rounded-field border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:border-primary-500">
                            <option value="">— اختر عقاراً —</option>
                            @foreach ($linkable as $p)<option value="{{ $p->id }}">{{ $p->reference_code }}</option>@endforeach
                        </select>
                        <select name="relation" class="w-full rounded-field border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:border-primary-500">
                            <option value="مهتم">مهتم</option>
                            <option value="عاين">عاين</option>
                            <option value="حجز">حجز</option>
                        </select>
                        <button class="w-full rounded-field bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 text-sm">ربط عقار</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    {{-- ===== مودال تعديل العميل ===== --}}
    @can('clients.edit')
        <div x-show="editOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
            <div class="absolute inset-0 bg-primary-950/50" @click="editOpen = false"></div>
            <div class="relative w-full max-w-2xl bg-white rounded-card shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-ink">تعديل بيانات العميل</h3>
                    <button @click="editOpen = false" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
                </div>
                <form method="POST" action="{{ route('dashboard.clients.update', $client) }}">
                    @csrf @method('PUT')
                    <div class="p-6">
                        @include('dashboard.clients._form', ['client' => $client])
                    </div>
                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                        <button type="button" @click="editOpen = false" class="rounded-field px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                        <button type="submit" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">حفظ التعديلات</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</div>
@endsection
