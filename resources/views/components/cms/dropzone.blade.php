@props([
    'label' => 'الصورة',
    'name',                          // hero[images]  أو  featured[image]
    'removeName' => null,            // اسم حقل معرّفات الحذف — يُشتق تلقائياً
    'media' => [],                   // Media[] أو Media واحد
    'multiple' => false,
    'hint' => 'اسحب الصور هنا أو اضغط للاختيار — الحد الأقصى ٦ ميجابايت للصورة، وتُصغَّر تلقائياً إلى ارتفاع ١٠٨٠ بكسل.',
])

@php
    // $media قد يكون Media واحداً أو مجموعة أو null — collect() على موديل واحد
    // يفكّه إلى خصائصه، لذلك نلفّه يدوياً.
    $list = match (true) {
        is_null($media) => collect(),
        $media instanceof \Illuminate\Support\Collection => $media,
        is_array($media) => collect($media),
        default => collect([$media]),
    };

    $items = $list->filter()->map(fn ($m) => [
        'id' => $m->id,
        'url' => $m->hasGeneratedConversion('web') ? $m->getUrl('web') : $m->getUrl(),
    ])->values();

    // featured[image] → featured[image_removed][]
    $remove = $removeName ?: preg_replace('/\]$/', '_removed][]', $name);
    $fieldName = $multiple ? $name.'[]' : $name;
@endphp

<div x-data="dropzone({ multiple: {{ $multiple ? 'true' : 'false' }}, existing: @js($items) })">
    <label class="block text-xs font-medium text-gray-500 mb-1.5">{{ $label }}</label>

    {{-- معرّفات الصور المطلوب حذفها --}}
    <template x-for="id in removed" :key="id">
        <input type="hidden" name="{{ $remove }}" :value="id">
    </template>

    <div @dragover.prevent="dragging = true"
         @dragleave.prevent="dragging = false"
         @drop.prevent="dropFiles($event)"
         @click="$refs.input.click()"
         :class="dragging ? 'border-primary-500 bg-primary-50' : 'border-gray-200 bg-gray-50/60 hover:bg-gray-50'"
         class="relative rounded-field border-2 border-dashed p-4 cursor-pointer transition">

        <input type="file" x-ref="input" name="{{ $fieldName }}" accept="image/jpeg,image/png,image/webp"
               @if ($multiple) multiple @endif
               @change="add($event.target.files)" @click.stop class="hidden">

        {{-- الحالة الفارغة --}}
        <div x-show="isEmpty" class="flex flex-col items-center justify-center gap-2 py-4 text-center">
            <span class="grid place-items-center w-11 h-11 rounded-full bg-primary-50 text-primary-700">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v13"/></svg>
            </span>
            <p class="text-sm font-bold text-ink">اسحب الصورة هنا أو اضغط للاختيار</p>
            <p class="text-[11px] text-gray-400 max-w-sm leading-relaxed">{{ $hint }}</p>
        </div>

        {{-- المعاينات --}}
        <div x-show="! isEmpty" class="flex flex-wrap gap-3">
            <template x-for="m in visibleExisting" :key="'m' + m.id">
                <div class="relative w-28 aspect-[4/3] rounded-field overflow-hidden border border-gray-200 bg-white group">
                    <img :src="m.url" class="w-full h-full object-cover" alt="">
                    <button type="button" @click.stop="removeExisting(m.id)"
                            class="absolute top-1.5 end-1.5 grid place-items-center w-7 h-7 rounded-full bg-white/90 backdrop-blur text-danger hover:bg-white shadow-sm transition"
                            title="حذف الصورة"><x-icon.trash size="14" /></button>
                </div>
            </template>

            <template x-for="(p, i) in picked" :key="'p' + i">
                <div class="relative w-28 aspect-[4/3] rounded-field overflow-hidden border-2 border-primary-300 bg-white">
                    <img :src="p.url" class="w-full h-full object-cover" alt="">
                    <span class="absolute inset-x-0 bottom-0 bg-primary-900/80 text-white text-[10px] font-bold text-center py-0.5" x-text="human(p.size)"></span>
                    <button type="button" @click.stop="removePicked(i)"
                            class="absolute top-1.5 end-1.5 grid place-items-center w-7 h-7 rounded-full bg-white/90 backdrop-blur text-danger hover:bg-white shadow-sm transition"
                            title="إزالة"><x-icon.trash size="14" /></button>
                </div>
            </template>

            {{-- زر إضافة المزيد --}}
            <template x-if="multiple">
                <div class="grid place-items-center w-28 aspect-[4/3] rounded-field border-2 border-dashed border-gray-200 text-gray-400 hover:border-primary-400 hover:text-primary-600 transition">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                </div>
            </template>
        </div>

        <div x-show="busy" x-cloak class="absolute inset-0 grid place-items-center rounded-field bg-white/70 text-sm font-bold text-primary-800">جارٍ ضغط الصور…</div>
    </div>

    <p x-show="error" x-cloak x-text="error" class="text-xs text-danger mt-1.5"></p>
</div>
