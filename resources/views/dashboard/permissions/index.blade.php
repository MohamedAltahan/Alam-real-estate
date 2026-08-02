@extends('layouts.dashboard')

@section('title', 'إدارة الصلاحيات')
@section('page-title', 'إدارة الصلاحيات')

@section('content')
@php
    /*
    | نقاط ملوّنة لتبويبات الأدوار — الألوان مأخوذة من Figma (node 163:4522).
    | القيمة الثانية هي بديل أفتح يُستخدم فوق التبويب النشط الكحلي حتى تبقى النقطة ظاهرة.
    */
    $roleDots = [
        'super-admin'      => ['#ef4444', '#ef4444'],
        'property-manager' => ['#2b357d', '#7397e7'],
        'sales-agent'      => ['#059669', '#10b981'],
        'marketing-staff'  => ['#c49a19', '#e0bc00'],
        'customer-service' => ['#8b5cf6', '#a78bfa'],
        'accountant'       => ['#f59e0b', '#f59e0b'],
    ];
    // للأدوار المضافة لاحقاً من الشاشة: ألوان متبدّلة حسب الترتيب
    $extraDots = ['#0ea5e9', '#ec4899', '#14b8a6', '#f97316', '#6366f1'];
@endphp

<form method="POST" action="{{ route('dashboard.permissions.update') }}">
    @csrf
    <input type="hidden" name="role_id" value="{{ $current?->id }}">

    <x-flash />

    {{-- العنوان وزرّ الحفظ في صفٍّ واحد (مطابق لـ Figma) --}}
    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">مصفوفة الصلاحيات</h2>
            <p class="text-sm text-gray-500">تحكّم في صلاحيات كل دور لكل وحدة</p>
        </div>

        @if ($current)
            @can('permissions.edit')
                <button type="submit"
                        class="inline-flex items-center gap-2 shrink-0 rounded-full bg-primary-900 hover:bg-primary-800
                               text-white font-bold px-4 h-9 text-[13px] transition">
                    حفظ التغييرات
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                </button>
            @endcan
        @endif
    </div>

    @if ($current)
        <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">

            {{-- تبويبات الأدوار — ملتصقة برأس الجدول --}}
            <div class="flex flex-wrap gap-1.5 px-4 pt-3">
                @foreach ($roles as $r)
                    @php
                        $isActive = $current->id === $r->id;
                        $dots = $roleDots[$r->name] ?? array_fill(0, 2, $extraDots[$loop->index % count($extraDots)]);
                    @endphp
                    <a href="{{ route('dashboard.permissions.index', ['role' => $r->id]) }}"
                       class="inline-flex items-center gap-2 rounded-t-xl px-3.5 h-9 text-[13px] font-semibold transition
                              {{ $isActive ? 'bg-primary-900 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 hover:text-ink' }}">
                        <span class="w-2 h-2 shrink-0 rounded-full" style="background-color: {{ $isActive ? $dots[1] : $dots[0] }}"></span>
                        {{ $r->description ?: $r->name }}
                    </a>
                @endforeach
            </div>

            @if ($current->name === 'super-admin')
                <div class="mx-4 mt-3 rounded-field bg-accent-50 border border-accent-200 text-accent-900 text-[13px] px-4 py-2.5">
                    مدير النظام يملك جميع الصلاحيات تلقائياً.
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px]">
                    <thead>
                        <tr class="bg-gray-50 text-[13px] text-gray-500">
                            <th class="text-start font-semibold px-5 py-2">الوحدة</th>
                            @foreach ($actions as $alabel)
                                <th class="font-semibold px-3 py-2 text-center">{{ $alabel }}</th>
                            @endforeach
                            <th class="font-semibold px-5 py-2 text-center">تحديد الكل</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($modules as $mkey => $mlabel)
                            @php
                                // الحالة الابتدائية للصف حتى لا تومض شارة «تحديد/إلغاء» قبل إقلاع Alpine
                                $rowAll = collect($actions)->keys()->every(fn ($a) => $assigned->has("{$mkey}.{$a}"));
                            @endphp
                            <tr class="border-t border-gray-100 hover:bg-gray-50/50 transition"
                                x-data="{
                                    all: {{ $rowAll ? 'true' : 'false' }},
                                    // ‏$root وليس ‎$el‎: الأخير يتغيّر حسب العنصر المُنفِّذ (الخانة أو الشارة)
                                    cells() { return [...this.$root.querySelectorAll('input[data-perm]')]; },
                                    sync() { const c = this.cells(); this.all = c.length > 0 && c.every(i => i.checked); },
                                    toggle() { const v = ! this.all; this.cells().forEach(i => i.checked = v); this.all = v; },
                                }"
                                x-init="sync()">

                                <td class="px-5 py-3 text-sm font-bold text-ink whitespace-nowrap">{{ $mlabel }}</td>

                                @foreach ($actions as $akey => $alabel)
                                    <td class="px-3 py-3">
                                        {{-- الشكل نفسه صار افتراضياً لكل صناديق الاختيار في resources/css/app.css --}}
                                        <label class="flex justify-center py-1 cursor-pointer" title="{{ $alabel }} — {{ $mlabel }}">
                                            <input type="checkbox" data-perm name="permissions[]" value="{{ $mkey }}.{{ $akey }}"
                                                   @checked($assigned->has("{$mkey}.{$akey}")) @change="sync()">
                                        </label>
                                    </td>
                                @endforeach

                                <td class="px-5 py-3 text-center">
                                    {{--
                                        صيغة الكائن في :class ضرورية هنا: Alpine يزيل الأصناف التي تصبح false
                                        حتى لو كانت مطبوعة من السيرفر — وإلا تراكمت أصناف اللونين معاً.
                                    --}}
                                    <button type="button" @click="toggle()"
                                            class="rounded-full px-3 py-1 text-[11px] font-bold transition
                                                   {{ $rowAll ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' }}"
                                            :class="{
                                                'bg-danger-soft text-danger hover:bg-danger/15': all,
                                                'bg-success-soft text-success hover:bg-success/15': ! all,
                                            }"
                                            x-text="all ? 'إلغاء' : 'تحديد'">{{ $rowAll ? 'إلغاء' : 'تحديد' }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</form>
@endsection
