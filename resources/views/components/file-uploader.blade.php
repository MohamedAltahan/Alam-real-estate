@props([
    'name' => 'files',                 // files[] للملفات الجديدة
    'removeName' => 'files_removed',   // files_removed[] لمعرّفات الحذف
    'files' => [],                     // [{ id, name, size, url, ext }]
    'accept' => 'jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx',
    'maxMb' => 15,
    'hint' => null,
    'resetEvent' => null,              // اسم حدث window لإعادة الضبط بقائمة جديدة
])

@php
    $acceptAttr = collect(explode(',', $accept))->map(fn ($e) => '.'.trim($e))->implode(',');
    $hint = $hint ?? 'صور أو PDF أو Word أو Excel — بحد أقصى '.$maxMb.' ميجابايت لكل ملف، ويمكن اختيار أكثر من ملف.';
@endphp

{{-- قائمة ملفات: المحفوظة (مع حذف) + الجديدة قبل الرفع --}}
<div x-data="fileList({ existing: @js($files), accept: @js($accept), maxMb: {{ (int) $maxMb }} })"
     @if ($resetEvent) x-on:{{ $resetEvent }}.window="reset($event.detail)" @endif
     class="space-y-2">

    <template x-for="id in removed" :key="'r' + id">
        <input type="hidden" name="{{ $removeName }}[]" :value="id">
    </template>

    <ul x-show="! isEmpty" class="space-y-1.5">
        <template x-for="f in existing" :key="'e' + f.id">
            <li class="flex items-center gap-3 rounded-xl border px-3 py-2 text-sm transition"
                :class="removed.includes(f.id) ? 'border-danger/30 bg-danger/5 opacity-70' : 'border-gray-100 bg-white'">
                <span class="grid place-items-center w-9 h-9 shrink-0 rounded-lg text-[10px] font-bold uppercase"
                      :class="{
                          'bg-info-soft text-info': kindOf(f.ext) === 'image',
                          'bg-danger/10 text-danger': kindOf(f.ext) === 'pdf',
                          'bg-primary-50 text-primary-700': kindOf(f.ext) === 'word',
                          'bg-success-soft text-success': kindOf(f.ext) === 'excel',
                          'bg-gray-100 text-gray-500': kindOf(f.ext) === 'file',
                      }" x-text="f.ext || 'file'"></span>
                <span class="min-w-0 flex-1">
                    <a :href="f.url" target="_blank" rel="noopener" class="block font-semibold text-ink truncate hover:text-primary-700" x-text="f.name" :class="removed.includes(f.id) && 'line-through'"></a>
                    <span class="block text-[11px] text-gray-400" x-text="removed.includes(f.id) ? 'سيُحذف عند الحفظ' : formatSize(f.size)"></span>
                </span>
                <button type="button" x-show="! removed.includes(f.id)" @click="removeExisting(f.id)" title="حذف الملف"
                        class="grid place-items-center w-8 h-8 shrink-0 rounded-full text-danger hover:bg-danger/10 transition"><x-icon.trash size="15" /></button>
                <button type="button" x-show="removed.includes(f.id)" @click="restoreExisting(f.id)"
                        class="shrink-0 rounded-full px-3 py-1 text-[11px] font-semibold text-gray-600 border border-gray-200 hover:bg-gray-100">تراجع</button>
            </li>
        </template>

        <template x-for="(p, i) in pending" :key="'p' + i">
            <li class="flex items-center gap-3 rounded-xl border-2 border-primary-200 bg-primary-50/40 px-3 py-2 text-sm">
                <span class="grid place-items-center w-9 h-9 shrink-0 rounded-lg bg-white text-primary-700 text-[10px] font-bold uppercase" x-text="p.ext"></span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-ink truncate" x-text="p.name"></span>
                    <span class="block text-[11px] text-primary-700" x-text="'جديد · ' + formatSize(p.size)"></span>
                </span>
                <button type="button" @click="dropPending(i)" title="إزالة"
                        class="grid place-items-center w-8 h-8 shrink-0 rounded-full text-danger hover:bg-danger/10 transition"><x-icon.trash size="15" /></button>
            </li>
        </template>
    </ul>

    <label class="flex items-center justify-center gap-2 rounded-field border-2 border-dashed border-gray-200 bg-gray-50/60 hover:bg-gray-50 hover:border-primary-300 px-4 py-3 text-sm font-semibold text-primary-700 cursor-pointer transition">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v13"/></svg>
        <span x-text="isEmpty ? 'اختيار ملفات' : 'إضافة ملفات أخرى'"></span>
        <input type="file" x-ref="input" name="{{ $name }}[]" multiple accept="{{ $acceptAttr }}" @change="pick($event)" class="sr-only">
    </label>
    <p class="text-[11px] text-gray-400">{{ $hint }}</p>
    <p x-show="error" x-cloak x-text="error" class="text-xs text-danger"></p>
</div>
