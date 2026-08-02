@props([
    'label' => 'الصورة',
    'name',                       // مثال: featured[image] أو area_items[3][image]
    'value' => null,              // المسار المحفوظ حالياً
    'hint' => '١٦٠٠ × ٨٠٠ بكسل أو نسبة ٢:١ — الصيغ المدعومة: jpg · png · webp',
    'ratio' => 'aspect-[16/9]',
])

@php
    // featured[image] → featured[image_path] / featured[image_remove]
    $base = preg_replace('/\]$/', '', $name);
    $pathName = $base.'_path]';
    $removeName = $base.'_remove]';
@endphp

<div x-data="{ current: @js($value ? \Illuminate\Support\Facades\Storage::url($value) : null), preview: null, removed: false }">
    <label class="block text-xs font-medium text-gray-500 mb-1.5">{{ $label }}</label>

    {{-- المسار المحفوظ يُرسل مع الفورم حتى لا تضيع الصورة عند إعادة الترتيب --}}
    <input type="hidden" name="{{ $pathName }}" value="{{ $value }}">
    <input type="hidden" name="{{ $removeName }}" :value="removed ? '1' : ''">

    <div class="flex items-start gap-3">
        {{-- المعاينة --}}
        <div class="relative w-32 {{ $ratio }} shrink-0 rounded-field overflow-hidden bg-gray-100 border border-gray-200 grid place-items-center text-gray-300">
            <template x-if="(current && ! removed) || preview">
                <img :src="preview || current" class="absolute inset-0 w-full h-full object-cover" alt="">
            </template>
            <template x-if="! ((current && ! removed) || preview)">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
            </template>

            {{-- حذف الصورة --}}
            <button type="button" x-show="(current && ! removed) || preview"
                    @click="removed = true; preview = null; $refs.file.value = ''"
                    class="absolute top-1.5 end-1.5 grid place-items-center w-7 h-7 rounded-full bg-white/90 backdrop-blur text-danger hover:bg-white shadow-sm transition"
                    title="حذف الصورة">
                <x-icon.trash size="14" />
            </button>
        </div>

        <div class="min-w-0 flex-1">
            <input type="file" x-ref="file" name="{{ $name }}" accept="image/*"
                   @change="removed = false; preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                   class="w-full text-sm text-gray-500 file:me-3 file:rounded-full file:border-0 file:bg-primary-50 file:text-primary-700 file:px-4 file:py-2 file:text-sm file:font-bold file:cursor-pointer hover:file:bg-primary-100">
            <p class="text-[11px] text-gray-400 mt-1.5 leading-relaxed">{{ $hint }}</p>
            <p class="text-[11px] text-danger mt-1" x-show="removed" x-cloak>سيتم حذف الصورة عند الحفظ.</p>
        </div>
    </div>
</div>
