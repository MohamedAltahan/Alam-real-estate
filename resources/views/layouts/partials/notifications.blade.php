{{--
    جرس الإشعارات: يُعرض من السيرفر أول مرة، ثم يستطلع كل دقيقة (notificationBell في dashboard.js)
    ليحدّث العدّاد ويضيف تذكيرات المعاينات الجديدة مع صوت تنبيه وتنبيه منبثق أسفل الشاشة.
--}}
@php
    // حالة واتساب المكتب من الكاش/السجل المحلي (بلا اتصال بالبوابة أثناء عرض الصفحة)
    $wa = app(\App\Services\WhatsApp\WhatsAppService::class)->status();
    $bellIcons = $icons + [
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'whatsapp' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
    ];
@endphp

<div class="relative flex items-center gap-2"
     x-data="notificationBell({ pollUrl: @js(route('dashboard.notifications.poll')), unread: {{ (int) $feedUnread }}, repeat: @js($me->viewingRepeatBeep()), wa: @js($wa) })"
     @keydown.escape.window="open = false">
    {{-- حالة واتساب المكتب: أخضر متصل · أحمر غير متصل · برتقالي بانتظار الربط · رمادي غير مُعدّ — تُحدَّث مع الاستطلاع كل دقيقة --}}
    <{{ $me->can('whatsapp.view') ? 'a href="'.route('dashboard.whatsapp.index').'"' : 'span' }}
        :title="wa.label + (wa.phone ? ' · ' + wa.phone : '')" data-whatsapp-status
        class="relative grid place-items-center w-[42px] h-[42px] rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition" aria-label="حالة واتساب">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">{!! $bellIcons['whatsapp'] !!}</svg>
        <span class="absolute top-2 end-2.5 w-2.5 h-2.5 rounded-full ring-2 ring-white"
              :class="{ 'bg-success': wa.tone === 'success', 'bg-danger': wa.tone === 'danger', 'bg-warning': wa.tone === 'warning', 'bg-gray-300': wa.tone === 'muted' }"></span>
    </{{ $me->can('whatsapp.view') ? 'a' : 'span' }}>

    <button type="button" @click="open = ! open"
            class="relative grid place-items-center w-[42px] h-[42px] rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition"
            aria-label="الإشعارات">
        <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round">{!! $bellIcons['bell'] !!}</svg>
        <span x-show="unread > 0" x-cloak class="absolute top-2 end-2.5 w-2.5 h-2.5 rounded-full bg-danger ring-2 ring-white"></span>
    </button>

    <div x-cloak x-show="open" @click.outside="open = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         class="absolute top-[calc(100%+8px)] end-0 w-[340px] max-w-[calc(100vw-2rem)]
                rounded-2xl bg-white border border-gray-100 shadow-xl shadow-primary-950/10 overflow-hidden">

        {{-- رأس القائمة --}}
        <div class="flex items-center gap-2 px-4 h-14 border-b border-gray-100">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                 stroke-linecap="round" stroke-linejoin="round" class="text-ink">{!! $bellIcons['bell'] !!}</svg>
            <span class="text-sm font-bold text-ink">الإشعارات</span>
            <span x-show="unread > 0" x-cloak x-text="unread" class="grid place-items-center min-w-5 h-5 px-1.5 rounded-full bg-danger text-white text-[11px] font-bold"></span>
            <form method="POST"
                  action="{{ $me->can('notifications.edit') && $me->can('contact_requests.view') ? route('dashboard.notifications.read-all') : route('dashboard.notifications.read-mine') }}"
                  class="ms-auto" x-show="unread > 0" x-cloak>
                @csrf
                <button class="flex items-center gap-1 text-xs text-gray-500 hover:text-primary-800 transition">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m2 13 4 4L14 7"/><path d="m11 15 2 2L22 7"/></svg>
                    قراءة الكل
                </button>
            </form>
        </div>

        {{-- العناصر --}}
        <div class="max-h-[380px] overflow-y-auto" x-ref="list">
            @forelse ($feedItems as $n)
                <a href="{{ $n['url'] }}" @if (! empty($n['id'])) data-notification-id="{{ $n['id'] }}" @endif
                   class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 hover:bg-gray-50/70 transition">
                    <span class="grid place-items-center w-9 h-9 shrink-0 rounded-full {{ $feedTone[$n['tone']] ?? $feedTone['primary'] }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                             stroke-linecap="round" stroke-linejoin="round">{!! $bellIcons[$n['icon']] ?? $bellIcons['bell'] !!}</svg>
                    </span>
                    <span class="min-w-0 flex-1 leading-snug">
                        <span data-title class="block text-[13px] font-semibold text-ink truncate">{{ $n['title'] }}</span>
                        <span data-time class="block text-[11px] text-gray-400 mt-0.5">{{ $n['at']->locale('ar')->diffForHumans() }}</span>
                    </span>
                    <span data-dot class="w-2 h-2 shrink-0 rounded-full bg-info {{ $n['unread'] ? '' : 'hidden' }}"></span>
                </a>
            @empty
                <p data-empty class="px-4 py-10 text-center text-sm text-gray-400">لا توجد إشعارات بعد</p>
            @endforelse
        </div>

        {{-- قالب إشعار معاينة يُضاف من الاستطلاع --}}
        <template x-ref="itemTemplate">
            <a href="#" class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 hover:bg-gray-50/70 transition">
                <span class="grid place-items-center w-9 h-9 shrink-0 rounded-full {{ $feedTone['accent'] }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $bellIcons['calendar'] !!}</svg>
                </span>
                <span class="min-w-0 flex-1 leading-snug">
                    <span data-title class="block text-[13px] font-semibold text-ink truncate"></span>
                    <span data-time class="block text-[11px] text-gray-400 mt-0.5"></span>
                </span>
                <span data-dot class="w-2 h-2 shrink-0 rounded-full bg-info"></span>
            </a>
        </template>

        <div class="grid grid-cols-2 divide-x divide-x-reverse divide-gray-100 border-t border-gray-100">
            @can('clients.view')
                <a href="{{ route('dashboard.viewings.index') }}" class="block py-3.5 text-center text-sm font-bold text-primary-800 hover:bg-gray-50 transition">مواعيد المعاينات</a>
            @endcan
            @can('contact_requests.view')
                <a href="{{ route('dashboard.requests.index') }}" class="block py-3.5 text-center text-sm font-bold text-primary-800 hover:bg-gray-50 transition">طلبات التواصل</a>
            @endcan
        </div>
    </div>

    {{--
        تنبيهات المعاينات أسفل يسار الشاشة — تبقى ظاهرة حتى يغلقها المستخدم أو يفتح الإشعار.
        x-teleport ينقلها إلى <body> حتى لا يحدّها ترتيب طبقات الشريط العلوي.
    --}}
    <template x-teleport="body">
        <div class="fixed bottom-6 left-6 z-[80] flex flex-col-reverse gap-3 w-[350px] max-w-[calc(100vw-2rem)]">
            <template x-for="toast in toasts" :key="toast.id">
                <div x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-3"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-end="opacity-0 translate-y-3"
                     class="rounded-2xl bg-white border border-gray-100 shadow-2xl shadow-primary-950/25 overflow-hidden">
                    <div class="flex items-start gap-3 p-3.5">
                        <span class="grid place-items-center w-10 h-10 shrink-0 rounded-full"
                              :class="toast.overdue ? '{{ $feedTone['danger'] }}' : '{{ $feedTone['accent'] }}'">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! $bellIcons['calendar'] !!}</svg>
                        </span>
                        <a :href="toast.url" class="min-w-0 flex-1 leading-snug group">
                            <span class="block text-[13px] font-bold text-ink group-hover:text-primary-800 transition" x-text="toast.title"></span>
                            <span class="flex items-center gap-1 text-[11px] font-semibold text-primary-700 mt-1.5">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                                اضغط لفتح صفحة المعاينات
                            </span>
                        </a>
                        <button type="button" @click="dismiss(toast.id)" aria-label="إغلاق التنبيه"
                                class="grid place-items-center w-7 h-7 shrink-0 rounded-full text-gray-400 hover:text-ink hover:bg-gray-100 transition">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="h-1" :class="toast.overdue ? 'bg-danger' : 'bg-accent-500'"></div>
                </div>
            </template>
        </div>
    </template>
</div>
