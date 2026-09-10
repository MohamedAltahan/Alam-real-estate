{{-- نافذة إرسال رسالة معاينة عبر واتساب: اختيار الرقم (بصفته) + نص الرسالة معبّأً — مشتركة بين صفحة العميل وصفحة المعاينات --}}
@can('clients.edit')
{{-- z أعلى من نوافذ الصفحة (z-50) حتى تظهر فوق النافذة التي فُتحت منها --}}
<div x-data="waSend()" x-on:wa-send.window="start($event.detail)" class="relative z-[60]">
    <x-modal name="wa-send" maxWidth="lg">
        <form :action="payload.action" method="POST">
            @csrf
            <input type="hidden" name="kind" :value="payload.kind">
            <input type="hidden" name="to" :value="to">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <h3 class="font-bold text-ink flex items-center gap-2">
                        <span class="grid place-items-center w-8 h-8 rounded-full bg-success-soft text-success"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></span>
                        <span x-text="payload.title"></span>
                    </h3>
                    <p class="text-xs text-gray-400 mt-1 truncate"><span x-text="payload.client"></span> · <span dir="auto" x-text="payload.reference"></span></p>
                </div>
                <button type="button" @click="$dispatch('close-modal', 'wa-send')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>

            <div class="p-6 space-y-5">
                <div>
                    <p class="text-sm font-medium text-gray-700 mb-2">إرسال إلى</p>
                    <div class="space-y-2" x-show="payload.recipients.length">
                        <template x-for="r in payload.recipients" :key="r.phone">
                            <label class="flex items-center gap-3 rounded-xl border px-3.5 py-2.5 cursor-pointer transition"
                                   :class="to === r.phone ? 'border-success bg-success-soft/40' : 'border-gray-200 hover:bg-gray-50'">
                                <input type="radio" name="recipient" :value="r.phone" :checked="to === r.phone" @change="pick(r.phone)" class="accent-success">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-ink truncate" x-text="r.label"></span>
                                    <span class="block text-xs text-gray-500 tabular-nums"><bdi dir="ltr" x-text="r.display"></bdi></span>
                                </span>
                            </label>
                        </template>
                    </div>
                    <p x-show="! payload.recipients.length" x-cloak class="rounded-field bg-warning-soft text-warning text-sm px-4 py-3">لا يوجد رقم مسجّل لهذا المستلم — أضف رقم التواصل أولاً.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">نص الرسالة <span class="text-gray-400 font-normal">(يمكنك تعديله قبل الإرسال)</span></label>
                    <textarea name="body" x-model="body" @input="dirty = true" rows="9" required maxlength="4000" dir="auto"
                              class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm leading-relaxed focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></textarea>
                </div>

                <p x-show="! payload.connected" x-cloak class="rounded-field bg-danger/10 text-danger text-sm px-4 py-3">واتساب المكتب غير متصل حالياً — اربط الرقم من شاشة «واتساب» ثم أعد المحاولة.</p>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal', 'wa-send')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" :disabled="! canSend" class="inline-flex items-center gap-2 rounded-full bg-success hover:bg-success/90 disabled:bg-gray-200 disabled:text-gray-400 text-white font-semibold px-5 py-2.5 text-sm transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    إرسال عبر واتساب
                </button>
            </div>
        </form>
    </x-modal>
</div>
@endcan
