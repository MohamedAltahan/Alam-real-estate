{{-- حالة الموقع العام: إيقاف مؤقت (وضع الصيانة) — إعداد عام لكل الزوار، يظهر فقط لمن يملك تعديل الموقع --}}
@php
    $down = $siteMaintenance;
    $overdue = $down && $down['since']->lte(now()->subHours(\App\Support\SiteFlags::MAINTENANCE_WARN_HOURS));
    $byLine = $down && $down['by'] ? ' · بواسطة '.$down['by'] : '';
    // «منذ 0 ثانية» بعد الضغط مباشرة غير مفهومة
    $sinceText = ! $down ? '' : ($down['since']->gt(now()->subMinute()) ? 'منذ لحظات' : $down['since']->locale('ar')->diffForHumans());
    $check = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5 text-success"><path d="M20 6 9 17l-5-5"/></svg>';
@endphp

<section id="site-status" class="mt-5 rounded-card border shadow-sm p-5 sm:p-6 {{ $down ? 'bg-warning-soft/60 border-warning/30' : 'bg-white border-gray-100' }}">
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <span class="grid place-items-center w-12 h-12 shrink-0 rounded-full {{ $down ? 'bg-warning text-white' : 'bg-success-soft text-success' }}">
            @if ($down)
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
            @else
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/></svg>
            @endif
        </span>

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-sm font-bold text-ink">حالة الموقع</h3>
                @if ($down)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-warning text-white px-2.5 py-0.5 text-[11px] font-bold">
                        <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        متوقف للصيانة
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-soft text-success px-2.5 py-0.5 text-[11px] font-bold">
                        <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                        يعمل
                    </span>
                @endif
            </div>

            @if ($down)
                <p class="text-xs text-gray-600 mt-1" title="{{ $down['since']->timezone(config('app.timezone'))->format('Y-m-d H:i') }}">
                    متوقف {{ $sinceText }}{{ $byLine }}
                </p>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">الزوار يرون صفحة «نعود قريباً» مع أرقام التواصل، ومحركات البحث تفهم أنه توقف مؤقت. أنت مسجَّل دخول فتتصفح الموقع كالمعتاد.</p>
            @else
                <p class="text-xs text-gray-400 mt-1 leading-relaxed">الموقع ظاهر للزوار ومحركات البحث. أوقفه مؤقتاً أثناء إضافة العقارات أو تجهيز المحتوى — لوحة التحكم تظل تعمل كالمعتاد.</p>
            @endif
        </div>

        @if ($down)
            <form method="POST" action="{{ route('dashboard.profile.site-maintenance') }}" class="shrink-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="enabled" value="0">
                <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-success hover:brightness-110 text-white font-bold px-5 py-2.5 text-sm transition">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4V8z"/></svg>
                    تشغيل الموقع
                </button>
            </form>
        @else
            <button type="button" @click="$dispatch('open-modal', 'site-maintenance')"
                    class="shrink-0 inline-flex items-center justify-center gap-2 rounded-full border border-danger/30 bg-white hover:bg-danger/5 text-danger font-bold px-5 py-2.5 text-sm transition">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M10 15V9M14 15V9"/></svg>
                إيقاف الموقع مؤقتاً
            </button>
        @endif
    </div>

    @if ($down)
        @if ($overdue)
            <p class="mt-4 flex items-start gap-2 rounded-field bg-danger-soft text-danger text-xs px-3.5 py-2.5 leading-relaxed">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/></svg>
                الموقع متوقف منذ أكثر من يومين — التوقف الطويل قد يُسقط صفحات من نتائج جوجل، شغّله في أقرب وقت.
            </p>
        @endif

        <div class="mt-4 pt-4 border-t border-warning/20 flex items-center gap-x-5 gap-y-2 flex-wrap text-xs">
            <a href="{{ route('dashboard.profile.site-maintenance.preview') }}" target="_blank" class="inline-flex items-center gap-1.5 font-bold text-primary-800 hover:underline">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                معاينة ما يراه الزوار
            </a>
            <a href="{{ route('site.home') }}" target="_blank" class="inline-flex items-center gap-1.5 font-bold text-primary-800 hover:underline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                فتح الموقع
            </a>
        </div>
    @endif
</section>

@unless ($down)
    <x-modal name="site-maintenance" maxWidth="md">
        <form method="POST" action="{{ route('dashboard.profile.site-maintenance') }}" class="p-6 sm:p-7">
            @csrf
            @method('PUT')
            <input type="hidden" name="enabled" value="1">

            <div class="grid place-items-center w-[72px] h-[72px] mx-auto rounded-full bg-warning text-white mb-5">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M10 15V9M14 15V9"/></svg>
            </div>
            <h3 class="text-xl font-bold text-ink text-center mb-2">إيقاف الموقع مؤقتاً؟</h3>
            <p class="text-sm text-gray-500 text-center mb-5">مناسب أثناء إضافة العقارات أو تجهيز المحتوى.</p>

            <ul class="space-y-2.5 rounded-2xl bg-gray-50 border border-gray-100 p-4 mb-6 text-xs text-gray-600 leading-relaxed">
                <li class="flex items-start gap-2.5">{!! $check !!}<span>الزوار يرون صفحة «نعود قريباً» مع أرقام التواصل وواتساب.</span></li>
                <li class="flex items-start gap-2.5">{!! $check !!}<span>آمن للسيو: جوجل يفهمه كتوقف مؤقت (503) فلا يحذف صفحاتك ولا يتأثر ترتيبك.</span></li>
                <li class="flex items-start gap-2.5">{!! $check !!}<span>لوحة التحكم تعمل كالمعتاد، وفريق العمل المسجَّل دخوله يتصفح الموقع عادي.</span></li>
                <li class="flex items-start gap-2.5 text-warning font-bold">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/></svg>
                    <span>يُفضَّل ألا يطول الإيقاف أكثر من يوم أو يومين.</span>
                </li>
            </ul>

            <div class="flex items-center gap-3">
                <button type="submit" class="flex-1 rounded-full bg-danger hover:brightness-110 text-white font-bold py-3 text-sm transition">تأكيد الإيقاف</button>
                <button type="button" @click="$dispatch('close-modal', 'site-maintenance')" class="flex-1 rounded-full bg-white border border-gray-200 hover:bg-gray-50 text-ink font-bold py-3 text-sm transition">إلغاء</button>
            </div>
        </form>
    </x-modal>
@endunless
