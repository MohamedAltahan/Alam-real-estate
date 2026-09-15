{{-- نافذة تعديل ملاحظة المعاينة من الخارج (viewing-notes.js) — تُضمَّن خارج منطقة النتائج الحيّة --}}
@can('clients.edit')
<div x-data="viewingNotes()" x-on:viewing-notes.window="start($event.detail)" class="relative z-[60]">
    <x-modal name="viewing-notes" maxWidth="lg">
        <form @submit.prevent="save()">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <h3 class="font-bold text-ink" x-text="title"></h3>
                    <p class="text-xs text-gray-400 mt-1 truncate" x-text="subtitle"></p>
                </div>
                <button type="button" @click="$dispatch('close-modal', 'viewing-notes')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>

            <div class="p-6">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">الملاحظة</label>
                <textarea x-ref="notes" x-model="notes" rows="5" maxlength="2000" dir="auto" placeholder="اكتب ملاحظة على المعاينة (اختياري)"
                          class="w-full rounded-field border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm leading-relaxed focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white"></textarea>
                <p x-show="error" x-text="error" x-cloak class="mt-2 text-xs text-danger"></p>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60">
                <button type="button" @click="$dispatch('close-modal', 'viewing-notes')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button>
                <button type="submit" :disabled="saving" class="rounded-full bg-primary-900 hover:bg-primary-800 disabled:bg-gray-200 disabled:text-gray-400 text-white font-semibold px-5 py-2.5 text-sm transition">حفظ الملاحظة</button>
            </div>
        </form>
    </x-modal>
</div>
@endcan
