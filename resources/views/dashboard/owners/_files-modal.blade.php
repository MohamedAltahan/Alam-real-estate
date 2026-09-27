{{--
    نافذة ملفات المالك — تُفتح من عمود «الملفات» في القائمة ومن زر «ملفات المالك» في صفحة المالك.
    يعتمد على openFiles(name, files) في ownerForm (resources/js/dashboard/owner-form.js) و OwnerFormData::filesPayload.
--}}
<x-modal name="owner-files" maxWidth="lg">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div>
            <h3 class="font-bold text-ink">ملفات المالك</h3>
            <p class="text-xs text-gray-400" x-text="filesOwner"></p>
        </div>
        <button type="button" @click="$dispatch('close-modal', 'owner-files')" class="text-gray-400 hover:text-gray-700"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="p-6 max-h-[65vh] overflow-y-auto space-y-2">
        <template x-for="file in ownerFiles" :key="file.id">
            <a :href="file.url" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-gray-100 hover:border-primary-200 hover:bg-primary-50/40 px-3 py-2.5 transition">
                <span class="grid place-items-center w-10 h-10 shrink-0 rounded-lg text-[10px] font-bold uppercase" :class="file.tone" x-text="file.ext || 'file'"></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-ink truncate" x-text="file.name"></span>
                    <span class="block text-[11px] text-gray-400" x-text="file.size_label"></span>
                </span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400 shrink-0"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
            </a>
        </template>
        <p x-show="ownerFiles.length === 0" class="text-center text-sm text-gray-400 py-10">لا توجد ملفات مرفوعة لهذا المالك.</p>
    </div>
</x-modal>
