@extends('layouts.dashboard')

@section('title', 'واتر مارك')
@section('page-title', 'واتر مارك')

@php
    $canEdit = auth()->user()->can('watermark.edit');
    $sectionCard = 'rounded-card bg-white border border-gray-100 shadow-sm p-5';
    // خلفية شطرنج تُظهر شفافية الشعار
    $checker = 'background-color:#fff;background-image:linear-gradient(45deg,#e9eaf0 25%,transparent 25%),linear-gradient(-45deg,#e9eaf0 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#e9eaf0 75%),linear-gradient(-45deg,transparent 75%,#e9eaf0 75%);background-size:16px 16px;background-position:0 0,0 8px,8px -8px,-8px 0';
@endphp

@section('content')
<div x-data="watermarkPage({
        enabled: @js(old('enabled', $config['enabled']) ? true : false),
        position: @js(old('position', $config['position'])),
        size: @js(old('size', $config['size'])),
        positions: @js($positions),
        sizes: @js($sizes),
        padding: {{ (int) $padding }},
        opacity: {{ (int) $opacity }},
        logoUrl: @js($logoUrl),
        imagesCount: {{ (int) $imagesCount }},
        applyUrl: @js(route('dashboard.watermark.apply')),
     })">
    <x-flash />

    <div class="mb-5">
        <h2 class="text-xl font-bold text-ink">العلامة المائية على صور العقارات</h2>
        <p class="text-sm text-gray-500">ارفع شعار الشركة واختر مكانه وحجمه — يُضاف تلقائياً على صور الغلاف والمعرض لكل عقار.</p>
    </div>

    <div class="grid gap-5 lg:grid-cols-2 items-start">

        {{-- ===================== الإعدادات ===================== --}}
        <form method="POST" action="{{ route('dashboard.watermark.update') }}" enctype="multipart/form-data"
              class="min-w-0 space-y-5">
            @csrf @method('PUT')

            <fieldset @disabled(! $canEdit) class="space-y-5 min-w-0">

                {{-- التفعيل --}}
                <div class="{{ $sectionCard }} flex items-center gap-4">
                    <div class="flex-1">
                        <p class="text-sm font-bold text-ink">تفعيل العلامة المائية</p>
                        <p class="text-xs text-gray-400 mt-0.5">عند الإيقاف تُرفع صور العقارات الجديدة بدون علامة.</p>
                    </div>
                    <input type="hidden" name="enabled" :value="enabled ? 1 : 0">
                    <button type="button" role="switch" :aria-checked="enabled" @click="enabled = ! enabled; dirty = true"
                            class="relative w-11 h-6 shrink-0 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/30"
                            :class="enabled ? 'bg-primary-800' : 'bg-gray-300'">
                        <span class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all" :class="enabled ? 'start-6' : 'start-1'"></span>
                    </button>
                </div>

                {{-- الشعار --}}
                <div class="{{ $sectionCard }} space-y-3">
                    <h3 class="font-bold text-ink">الشعار</h3>
                    <input type="hidden" name="logo_remove" :value="removed ? 1 : 0">

                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="relative w-full sm:w-48 h-28 shrink-0 rounded-field overflow-hidden border border-gray-200 grid place-items-center"
                             style="{{ $checker }}">
                            <template x-if="currentLogo">
                                <img :src="currentLogo" alt="شعار العلامة المائية" class="max-w-[85%] max-h-[80%] object-contain">
                            </template>
                            <template x-if="! currentLogo">
                                <span class="text-xs text-gray-400 bg-white/80 rounded-full px-3 py-1">لا يوجد شعار</span>
                            </template>

                            @if ($canEdit)
                                <button type="button" x-show="currentLogo" @click="removeLogo()" title="حذف الشعار"
                                        class="absolute top-1.5 end-1.5 grid place-items-center w-7 h-7 rounded-full bg-white/90 backdrop-blur text-danger hover:bg-white shadow-sm transition">
                                    <x-icon.trash size="14" />
                                </button>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1 space-y-1.5">
                            <input type="file" x-ref="logo" name="logo" accept="image/png,image/webp" @change="pickLogo($event)"
                                   class="w-full text-sm text-gray-500 file:me-3 file:rounded-full file:border-0 file:bg-primary-50 file:text-primary-700 file:px-4 file:py-2 file:text-sm file:font-bold file:cursor-pointer hover:file:bg-primary-100">
                            <p class="text-[11px] text-gray-400 leading-relaxed">
                                PNG أو WebP بخلفية شفافة — العرض المقترح ٨٠٠–١٠٠٠ بكسل (٤٠٠ على الأقل)، وبحد أقصى ٢ ميجابايت.
                            </p>
                            <p class="text-[11px] text-danger" x-show="removed && ! preview" x-cloak>سيتم حذف الشعار وإيقاف العلامة عند الحفظ.</p>
                        </div>
                    </div>
                </div>

                {{-- المكان --}}
                <div class="{{ $sectionCard }} space-y-3">
                    <div>
                        <h3 class="font-bold text-ink">مكان العلامة على الصورة</h3>
                        <p class="text-xs text-gray-400 mt-0.5">اختر من الشبكة — الاتجاهات حسب الصورة نفسها (يمين الصورة = يمين الشاشة).</p>
                    </div>
                    <input type="hidden" name="position" :value="position">

                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        {{-- ltr: حتى تبقى «أعلى اليسار» في يسار الشبكة فعلاً داخل الصفحة العربية --}}
                        <div dir="ltr" class="grid grid-cols-3 gap-2 w-full max-w-[240px] aspect-[4/3] p-2 rounded-field bg-gray-100 border border-gray-200">
                            @foreach ($positions as $key => $label)
                                <button type="button" @click="choosePosition(@js($key))" title="{{ $label }}" aria-label="{{ $label }}"
                                        :aria-pressed="position === @js($key)"
                                        :class="position === @js($key) ? 'bg-primary-900 border-primary-900' : 'bg-white border-gray-200 hover:border-primary-300'"
                                        class="rounded-lg border grid place-items-center transition">
                                    <span class="w-5 h-2 rounded-sm transition" :class="position === @js($key) ? 'bg-accent-500' : 'bg-gray-200'"></span>
                                </button>
                            @endforeach
                        </div>
                        <p class="text-sm text-gray-500">المكان المختار: <span class="font-bold text-ink" x-text="positionLabel"></span></p>
                    </div>
                </div>

                {{-- الحجم --}}
                <div class="{{ $sectionCard }} space-y-3">
                    <div>
                        <h3 class="font-bold text-ink">حجم العلامة</h3>
                        <p class="text-xs text-gray-400 mt-0.5">عرض الشعار كنسبة من عرض الصورة، والارتفاع يتظبط تلقائياً بنفس نسبة الشعار.</p>
                    </div>
                    <input type="hidden" name="size" :value="size">

                    <div class="grid grid-cols-3 gap-3">
                        @foreach ($sizes as $key => $size)
                            <button type="button" @click="chooseSize(@js($key))" :aria-pressed="size === @js($key)"
                                    :class="size === @js($key) ? 'border-primary-900 bg-primary-50' : 'border-gray-200 bg-white hover:bg-gray-50'"
                                    class="rounded-field border-2 px-2 sm:px-3 py-3 text-center transition">
                                <span dir="ltr" class="relative block h-10 mb-2 rounded-md bg-gray-100 overflow-hidden">
                                    <span class="absolute bottom-1.5 right-1.5 h-2.5 rounded-sm bg-accent-500" style="width: {{ $size['percent'] }}%"></span>
                                </span>
                                <span class="block text-sm font-bold text-ink">{{ $size['label'] }}</span>
                                <span class="block text-[11px] text-gray-400">{{ $size['percent'] }}% من العرض</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                @if ($canEdit)
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-primary-900 hover:bg-primary-800 text-white font-bold px-6 py-2.5 text-sm transition">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                            حفظ الإعدادات
                        </button>
                    </div>
                @endif
            </fieldset>
        </form>

        {{-- ===================== المعاينة + تحديث الصور الحالية ===================== --}}
        <div class="min-w-0 space-y-5 lg:sticky lg:top-24">
            <div class="{{ $sectionCard }} space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-bold text-ink">المعاينة</h3>
                    <span class="text-[11px] font-bold rounded-full px-2.5 py-1"
                          :class="enabled && currentLogo ? 'bg-success-soft text-success' : 'bg-gray-100 text-gray-500'"
                          x-text="enabled && currentLogo ? 'مفعّلة' : 'متوقفة'"></span>
                </div>

                <div class="relative w-full aspect-video rounded-field overflow-hidden bg-gray-100">
                    <img src="{{ $sampleUrl }}" alt="صورة عقار للمعاينة" class="absolute inset-0 w-full h-full object-cover">
                    <template x-if="currentLogo">
                        <img :src="currentLogo" alt="" :style="markStyle" class="pointer-events-none select-none transition-all duration-200"
                             :class="enabled ? '' : 'grayscale'">
                    </template>
                </div>
                <p class="text-[11px] text-gray-400 leading-relaxed">
                    المعاينة بنفس النسب الفعلية: مسافة {{ $padding }}% من الحافة وشفافية {{ $opacity }}%. الصورة الأصلية تبقى محفوظة بدون علامة.
                </p>
            </div>

            @if ($canEdit)
                <div class="{{ $sectionCard }} space-y-3">
                    <div>
                        <h3 class="font-bold text-ink">تحديث الصور الحالية</h3>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                            الإعدادات تُطبَّق تلقائياً على الصور الجديدة. صور العقارات المرفوعة سابقاً
                            (<span class="font-bold text-ink">{{ number_format($imagesCount) }}</span> صورة)
                            تحتاج تحديثاً حتى تأخذ آخر إعدادات محفوظة — أو تُزال منها العلامة لو أوقفتها.
                        </p>
                    </div>

                    <div x-show="applying || processed" x-cloak class="space-y-1.5">
                        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-primary-800 transition-all duration-300" :style="{ width: progress + '%' }"></div>
                        </div>
                        <p class="text-[11px] text-gray-500">
                            <span x-text="processed"></span> من <span x-text="total"></span> صورة
                            <span x-show="failed" class="text-danger">· تعذّر تحديث <span x-text="failed"></span></span>
                        </p>
                    </div>

                    <p x-show="finished" x-cloak class="text-xs font-bold text-success">تم تحديث كل صور العقارات.</p>
                    <p x-show="error" x-cloak x-text="error" class="text-xs text-danger"></p>
                    <p x-show="dirty" x-cloak class="text-xs text-warning">احفظ الإعدادات أولاً ثم حدّث الصور.</p>

                    <button type="button" @click="applyExisting()" :disabled="applying || dirty || ! total"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-full border border-gray-200 bg-white hover:bg-gray-50 text-ink font-bold px-5 py-2.5 text-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             :class="applying && 'animate-spin'"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                        <span x-text="applying ? 'جارٍ التحديث… لا تغلق الصفحة' : 'تحديث الصور الحالية'"></span>
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
