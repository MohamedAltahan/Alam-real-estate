@extends('layouts.dashboard')

@section('title', 'واتساب')
@section('page-title', 'واتساب')

@php
    use App\Models\WhatsappMessage;
    use App\Support\WhatsAppTemplates;

    $tabs = ['connection' => 'ربط الرقم', 'templates' => 'قوالب الرسائل', 'messages' => 'سجل الرسائل'];
    $field = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
    $tone = ['success' => 'bg-success-soft text-success', 'danger' => 'bg-danger/10 text-danger', 'warning' => 'bg-warning-soft text-warning', 'muted' => 'bg-gray-100 text-gray-500'];
    $dot = ['success' => 'bg-success', 'danger' => 'bg-danger', 'warning' => 'bg-warning', 'muted' => 'bg-gray-300'];
@endphp

@section('content')
<div>
    <x-flash />

    <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-xl font-bold text-ink">واتساب المكتب</h2>
            <p class="text-sm text-gray-500">إرسال تفاصيل المعاينات للمالك ومتابعة العميل من رقم المكتب المربوط</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-2 rounded-full px-3.5 h-10 text-sm font-semibold {{ $tone[$status['tone']] }}">
                <span class="w-2 h-2 rounded-full {{ $dot[$status['tone']] }}"></span>
                {{ $status['label'] }}
                @if ($status['phone'])<span dir="ltr" class="tabular-nums">· +{{ $status['phone'] }}</span>@endif
            </span>
            @can('reports.view')
                <a href="{{ route('dashboard.reports.viewings') }}" class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white hover:bg-gray-50 text-sm font-semibold text-gray-700 px-4 h-10 transition">تقرير المعاينات</a>
            @endcan
        </div>
    </div>

    <div class="inline-flex items-center gap-1 rounded-full bg-gray-100/70 border border-gray-100 p-1 mb-5">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('dashboard.whatsapp.index', ['tab' => $key]) }}" class="inline-flex items-center gap-2 rounded-full h-9 px-4 text-sm font-semibold whitespace-nowrap transition {{ $tab === $key ? 'bg-primary-900 text-white' : 'text-gray-600 hover:bg-white hover:text-ink' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($tab === 'connection')
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <section class="xl:col-span-2 rounded-card bg-white border border-gray-100 shadow-sm p-6">
                @if (! $status['configured'])
                    <div class="rounded-field bg-warning-soft text-warning text-sm px-4 py-3">لم يُضبط مفتاح البوابة بعد — أضف <code dir="ltr">KHABEERSOFT_API_KEY</code> في ملف <code dir="ltr">.env</code> ثم أعد تحميل الصفحة.</div>
                @elseif (! $instance)
                    <h3 class="font-bold text-ink mb-1">ربط رقم واتساب المكتب</h3>
                    <p class="text-sm text-gray-500 mb-5">أنشئ جلسة ثم امسح رمز QR من تطبيق واتساب على هاتف المكتب (الإعدادات ← الأجهزة المرتبطة).</p>
                    @can('whatsapp.edit')
                        <form method="POST" action="{{ route('dashboard.whatsapp.connect') }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="flex-1 min-w-[220px]">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">اسم الجلسة</label>
                                <input name="name" value="{{ old('name', 'رقم المكتب') }}" required maxlength="120" class="{{ $field }}">
                                @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-success hover:bg-success/90 text-white font-semibold px-5 h-[42px] text-sm transition">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v7h-7"/></svg>
                                إنشاء الجلسة وعرض QR
                            </button>
                        </form>
                    @else
                        <p class="text-sm text-gray-400">لا تملك صلاحية ربط الرقم.</p>
                    @endcan
                @else
                    <div x-data="qrPanel({ url: @js(route('dashboard.whatsapp.qr')), status: @js($instance->status), phone: @js($instance->phone), label: @js($instance->statusLabel()) })">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="font-bold text-ink">{{ $instance->name }} <span class="text-xs text-gray-400 font-normal" dir="ltr">#{{ $instance->external_id }}</span></h3>
                                <p class="text-xs text-gray-400 mt-0.5">آخر فحص: <span dir="ltr">{{ $instance->checked_at?->format('Y-m-d H:i') ?? '—' }}</span></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
                                      :class="{ 'bg-success-soft text-success': status === 'connected', 'bg-warning-soft text-warning': status === 'qr' || status === 'created', 'bg-danger/10 text-danger': status === 'disconnected' || status === 'error' }"
                                      x-text="label"></span>
                                @can('whatsapp.edit')
                                    <form method="POST" action="{{ route('dashboard.whatsapp.disconnect') }}" onsubmit="return confirm('سيتم فصل الرقم وحذف الجلسة من البوابة. متابعة؟')">
                                        @csrf
                                        <button class="rounded-full border border-danger/30 text-danger hover:bg-danger/10 text-xs font-semibold px-3 py-1.5 transition" x-text="status === 'connected' ? 'فصل الرقم' : 'إلغاء الجلسة'"></button>
                                    </form>
                                @endcan
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center gap-6" x-show="status !== 'connected'" x-cloak>
                            <div class="w-56 h-56 rounded-2xl border border-gray-100 bg-white grid place-items-center overflow-hidden shrink-0">
                                <template x-if="qr"><img :src="qr" class="w-full h-full object-contain" alt="QR"></template>
                                <template x-if="! qr"><span class="text-xs text-gray-400 text-center px-4" x-text="error || 'جارٍ تجهيز رمز QR...'"></span></template>
                            </div>
                            <ol class="text-sm text-gray-600 space-y-2 list-decimal ps-5">
                                <li>افتح واتساب على هاتف المكتب ← الإعدادات ← <strong>الأجهزة المرتبطة</strong>.</li>
                                <li>اضغط «ربط جهاز» وامسح الرمز.</li>
                                <li>تتحدّث الحالة تلقائياً إلى «متصل» خلال ثوانٍ (الرمز يتجدد من تلقاء نفسه).</li>
                            </ol>
                        </div>

                        <div class="mt-5 rounded-field bg-success-soft text-success text-sm px-4 py-3" x-show="status === 'connected'" x-cloak>
                            ✓ الرقم <span dir="ltr" class="font-bold tabular-nums" x-text="phone ? '+' + phone : ''"></span> متصل ويرسل رسائل المعاينات. الأيقونة بجوار الجرس تتحقق من الاتصال كل دقيقة.
                        </div>
                    </div>
                @endif
            </section>

            <aside class="rounded-card bg-white border border-gray-100 shadow-sm p-6 text-sm text-gray-600 space-y-3">
                <h3 class="font-bold text-ink">ماذا يُرسل من هذا الرقم؟</h3>
                <p><strong class="text-ink">تفاصيل المعاينة للمالك:</strong> بعد تحديد معاينة في صفحة العميل، يختار الموظف أحد أرقام المالك (بصفته) وتُرسل بيانات العميل وموعد المعاينة.</p>
                <p><strong class="text-ink">متابعة المعاينة للعميل:</strong> بعد تسجيل نتيجة المعاينة تُرسل رسالة المتابعة لرقم العميل.</p>
                <p class="text-xs text-gray-400 pt-2 border-t border-gray-100">حدود البوابة: 60 رسالة في الساعة للرقم، مع فاصل 1–4 ثوانٍ بين الرسائل. كل محاولة تُسجَّل في «سجل الرسائل» بنجاحها أو سبب فشلها.</p>
            </aside>
        </div>

    @elseif ($tab === 'templates')
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <div class="xl:col-span-2 space-y-5">
                @foreach ($kinds as $kind => $label)
                    <section class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                        <form method="POST" action="{{ route('dashboard.whatsapp.templates.update', $kind) }}">
                            @csrf @method('PUT')
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <h3 class="font-bold text-ink">{{ $label }}</h3>
                                <span class="text-[11px] text-gray-400">{{ $kind === WhatsAppTemplates::KIND_OWNER ? 'تُرسل لأحد أرقام المالك' : 'تُرسل لرقم العميل بعد تسجيل النتيجة' }}</span>
                            </div>
                            <textarea name="body" rows="9" required maxlength="4000" dir="auto" @disabled(! auth()->user()->can('whatsapp.edit'))
                                      class="{{ $field }} leading-relaxed">{{ old('body', $templates[$kind]->body ?? WhatsAppTemplates::DEFAULTS[$kind]['body']) }}</textarea>
                            @error('body')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            @can('whatsapp.edit')
                                <div class="flex justify-end mt-3">
                                    <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm transition">حفظ القالب</button>
                                </div>
                            @endcan
                        </form>
                    </section>
                @endforeach
            </div>

            <aside class="rounded-card bg-white border border-gray-100 shadow-sm p-6">
                <h3 class="font-bold text-ink mb-1">المتغيّرات المتاحة</h3>
                <p class="text-xs text-gray-400 mb-4">تُستبدل تلقائياً ببيانات المعاينة عند الإرسال — والموظف يستطيع تعديل النص النهائي قبل الإرسال.</p>
                <ul class="space-y-2 text-sm">
                    @foreach ($placeholders as $token => $hint)
                        <li class="flex items-start gap-2">
                            <code class="shrink-0 rounded-md bg-gray-100 px-1.5 py-0.5 text-[12px] text-primary-800">{{ $token }}</code>
                            <span class="text-gray-500 text-xs pt-0.5">{{ $hint }}</span>
                        </li>
                    @endforeach
                </ul>
            </aside>
        </div>

    @else
        <div class="rounded-card bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-500 text-xs border-b border-gray-100 bg-gray-50/60">
                            <th class="text-start font-medium px-4 py-3 w-12">#</th>
                            <th class="text-start font-medium px-4 py-3">التاريخ</th>
                            <th class="text-start font-medium px-4 py-3">الرسالة</th>
                            <th class="text-start font-medium px-4 py-3">العميل</th>
                            <th class="text-start font-medium px-4 py-3">العقار</th>
                            <th class="text-start font-medium px-4 py-3">إلى</th>
                            <th class="text-start font-medium px-4 py-3">الحالة</th>
                            <th class="text-start font-medium px-4 py-3">بواسطة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($messages as $message)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-4 py-3 text-gray-400 tabular-nums">{{ $messages->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap" dir="ltr">{{ $message->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 font-semibold text-ink">
                                    <span class="block">{{ WhatsAppTemplates::kindLabel($message->kind) }}</span>
                                    <span class="block text-[11px] text-gray-400 font-normal truncate max-w-[260px]" title="{{ $message->body }}">{{ \Illuminate\Support\Str::limit($message->body, 60) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($message->client)
                                        <a href="{{ route('dashboard.clients.show', $message->client) }}" class="text-ink hover:text-primary-700 font-semibold">{{ $message->client->name }}</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600" dir="ltr">{{ $message->viewing?->property?->reference_code ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 text-xs">{{ $message->to_label ?: $message->to_phone }}</td>
                                <td class="px-4 py-3">
                                    @if ($message->succeeded())
                                        <span class="rounded-full bg-success-soft text-success px-2.5 py-1 text-xs font-semibold">أُرسلت</span>
                                    @else
                                        <span class="rounded-full bg-danger/10 text-danger px-2.5 py-1 text-xs font-semibold" title="{{ $message->error }}">فشلت</span>
                                        <span class="block text-[11px] text-danger/80 mt-1 max-w-[220px] truncate" title="{{ $message->error }}">{{ $message->error }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-xs">{{ $message->sender?->name ?? 'النظام' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-16 text-center text-gray-400">لم تُرسل أي رسائل بعد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $messages->links() }}</div>
    @endif
</div>
@endsection
