@extends('layouts.dashboard')

@section('title', 'إدارة الموقع')
@section('page-title', 'إدارة الموقع')

@php
    $tabs = [
        'homepage' => 'الصفحة الرئيسية', 'about' => 'من نحن', 'seo' => 'قسم SEO',
        'offers' => 'العروض العقارية', 'properties' => 'العقارات', 'faq' => 'الأسئلة الشائعة',
        'terms' => 'الشروط والأحكام', 'privacy' => 'سياسة الخصوصية', 'footer' => 'الفوتر',
    ];
    $s = fn ($sec, $loc, $field, $def = '') => data_get($sections, "$sec.$loc.$field", $def);
    $areaItems = $s('areas', 'ar', 'items', []);
    $whyItems = $s('why_us', 'ar', 'items', []);

    // بنود المناطق للمكرِّر — مع صورة كل بند من مجموعتها
    $areaRows = collect($areaItems)->values()->map(fn ($it, $i) => [
        'uid' => $it['collection'] ?? 'area-'.$i,
        'area_id' => (string) ($it['area_id'] ?? ''),
        'count' => (string) ($it['count'] ?? ''),
    ])->all();

    $whyRows = collect($whyItems)->values()->map(fn ($it) => [
        'uid' => 'w'.uniqid(),
        'number' => (string) ($it['number'] ?? ''),
        'icon' => (string) ($it['icon'] ?? ''),
        'title_ar' => (string) data_get($it, 'title.ar', ''),
        'title_en' => (string) data_get($it, 'title.en', ''),
        'description_ar' => (string) data_get($it, 'description.ar', ''),
        'description_en' => (string) data_get($it, 'description.en', ''),
    ])->all();

    $inputCls = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
    $sectionCard = 'rounded-card bg-white border border-gray-100 shadow-sm p-5 space-y-4';
    $fileCls = 'w-full text-sm text-gray-500 file:me-3 file:rounded-field file:border-0 file:bg-primary-50 file:text-primary-700 file:px-3 file:py-1.5 file:text-sm';
    $numCls = 'w-full rounded-field border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/15 focus:bg-white';
@endphp

@section('content')
<div x-data="{ tab: 'homepage' }">
    <x-flash />

    <div class="mb-5">
        <h2 class="text-xl font-bold text-ink">إدارة محتوى الموقع</h2>
        <p class="text-sm text-gray-500">حرّر محتوى كل صفحة — الحقول تنعكس مباشرة على الموقع (عربي/إنجليزي)</p>
    </div>

    {{-- التبويبات --}}
    <div class="flex flex-wrap gap-2 mb-5">
        @foreach ($tabs as $k => $label)
            <button @click="tab = '{{ $k }}'" :class="tab === '{{ $k }}' ? 'bg-primary-900 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'" class="rounded-full px-4 py-2 text-sm font-medium transition">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ============ الصفحة الرئيسية ============ --}}
    <div x-show="tab === 'homepage'" x-cloak>
        @can('website.edit')
        <form method="POST" action="{{ route('dashboard.website.homepage') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf @method('PUT')

            {{-- الواجهة الرئيسية --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">الواجهة الرئيسية (الهيرو)</h3>
                <x-cms.pair label="الشارة العلوية" group="hero" field="badge" :ar="$s('hero','ar','badge')" :en="$s('hero','en','badge')" />
                <x-cms.pair label="العنوان" group="hero" field="title" :ar="$s('hero','ar','title')" :en="$s('hero','en','title')" />
                <x-cms.pair label="العنوان الفرعي" group="hero" field="subtitle" :ar="$s('hero','ar','subtitle')" :en="$s('hero','en','subtitle')" />
                <x-cms.pair label="الوصف" group="hero" field="description" type="textarea" :ar="$s('hero','ar','description')" :en="$s('hero','en','description')" />
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">عدد العقارات</label><input name="hero[stat_properties]" value="{{ data_get($sections, 'hero.ar.stats.properties') }}" placeholder="500+" class="{{ $numCls }}"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">عدد العملاء</label><input name="hero[stat_clients]" value="{{ data_get($sections, 'hero.ar.stats.clients') }}" placeholder="1,200+" class="{{ $numCls }}"></div>
                    <div><label class="block text-xs font-medium text-gray-500 mb-1">عدد المناطق</label><input name="hero[stat_areas]" value="{{ data_get($sections, 'hero.ar.stats.areas') }}" placeholder="15+" class="{{ $numCls }}"></div>
                </div>
                <x-cms.dropzone label="صور الخلفية المتحركة (بلا حد — تتبدّل تلقائياً)"
                                name="hero[images]" multiple :media="$homeSecs['hero']->getMedia('images')" />
            </div>

            {{-- عروض عقارية مميزة --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">عروض عقارية مميزة</h3>
                <x-cms.pair label="العنوان" group="featured" field="title" :ar="$s('featured','ar','title')" :en="$s('featured','en','title')" />
                <x-cms.pair label="الوصف" group="featured" field="description" type="textarea" :ar="$s('featured','ar','description')" :en="$s('featured','en','description')" />
                <x-cms.dropzone label="صورة توضيحية" name="featured[image]"
                                :media="$homeSecs['featured']->getFirstMedia('image')" />
            </div>

            {{-- التغطية الجغرافية --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">التغطية الجغرافية (أفضل المناطق)</h3>
                <x-cms.pair label="العنوان" group="areas" field="title" :ar="$s('areas','ar','title')" :en="$s('areas','en','title')" />
                <x-cms.pair label="الوصف" group="areas" field="description" :ar="$s('areas','ar','description')" :en="$s('areas','en','description')" />
                {{-- بنود ديناميكية بلا حد. المحفوظ يُرسَم من السيرفر (ومعه صورته)،
                     والجديد يُضاف بـ Alpine. حذف بند = إزالة حقوله من الفورم. --}}
                <div class="space-y-3">
                    @foreach ($areaRows as $row)
                        <div x-data="{ gone: false }" x-show="! gone" class="rounded-field bg-gray-50/60 border border-gray-100 p-3 space-y-3">
                            <template x-if="! gone">
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-gray-500">{{ $loop->iteration }} — منطقة محفوظة</span>
                                        <button type="button" @click="gone = true" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف المنطقة"><x-icon.trash /></button>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">المنطقة</label>
                                            {{-- منطقة واحدة من جدول المناطق — الاسم AR/EN مخزَّن فيه بالفعل --}}
                                            <select name="area_items[{{ $row['uid'] }}][area_id]" class="{{ $inputCls }}">
                                                <option value="">— اختر المنطقة —</option>
                                                @foreach ($areas as $a)<option value="{{ $a->id }}" @selected($row['area_id'] == $a->id)>{{ $a->name }}</option>@endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs text-gray-500 mb-1">عدد العقارات المتاحة</label>
                                            <input type="number" min="0" name="area_items[{{ $row['uid'] }}][count]" value="{{ $row['count'] }}" placeholder="اتركه فارغاً ليُحسب تلقائياً" class="{{ $inputCls }}">
                                        </div>
                                    </div>
                                    <x-cms.dropzone label="صورة المنطقة" :name="'area_items['.$row['uid'].'][image]'"
                                                    :media="$homeSecs['areas']->getFirstMedia($row['uid'])" />
                                </div>
                            </template>
                        </div>
                    @endforeach

                    {{-- بنود جديدة --}}
                    <div x-data="{ rows: [], n: 0 }" class="space-y-3">
                        <template x-for="(row, i) in rows" :key="row.uid">
                            <div class="rounded-field bg-primary-50/40 border border-primary-100 p-3 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-primary-700">منطقة جديدة</span>
                                    <button type="button" @click="rows.splice(i, 1)" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="إزالة"><x-icon.trash /></button>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">المنطقة</label>
                                        <select :name="`area_items[${row.uid}][area_id]`" class="{{ $inputCls }}">
                                            <option value="">— اختر المنطقة —</option>
                                            @foreach ($areas as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">عدد العقارات المتاحة</label>
                                        <input type="number" min="0" :name="`area_items[${row.uid}][count]`" placeholder="اتركه فارغاً ليُحسب تلقائياً" class="{{ $inputCls }}">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1.5">صورة المنطقة</label>
                                    <input type="file" :name="`area_items[${row.uid}][image]`" accept="image/jpeg,image/png,image/webp"
                                           class="w-full text-sm text-gray-500 file:me-3 file:rounded-full file:border-0 file:bg-primary-50 file:text-primary-700 file:px-4 file:py-2 file:text-sm file:font-bold">
                                    <p class="text-[11px] text-gray-400 mt-1">حد أقصى ٦ ميجابايت — تُصغَّر تلقائياً لارتفاع ١٠٨٠ بكسل.</p>
                                </div>
                            </div>
                        </template>

                        <button type="button" @click="rows.push({ uid: 'area-new' + (n++) })"
                                class="inline-flex items-center gap-2 rounded-full border-2 border-dashed border-gray-300 hover:border-primary-500 hover:text-primary-700 text-gray-500 font-bold px-4 py-2 text-sm transition">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                            إضافة منطقة
                        </button>
                    </div>
                </div>
            </div>

            {{-- فديوهات تعرفية --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">فديوهات تعرفية (تعريف الخدمات)</h3>
                <x-cms.pair label="العنوان" group="videos" field="title" :ar="$s('videos','ar','title')" :en="$s('videos','en','title')" />
                <x-cms.pair label="الوصف" group="videos" field="description" :ar="$s('videos','ar','description')" :en="$s('videos','en','description')" />
                @php
                    $picked = collect($s('videos', 'ar', 'items', []))->map(fn ($v) => (int) $v)->all();
                    $pool = $videoPool->map(fn ($p) => [
                        'id' => $p->id,
                        'ref' => (string) $p->reference_code,
                        'title' => (string) $p->title,
                        'thumb' => $p->video_thumb,
                    ])->values()->all();
                    // اختيارات لم تعد صالحة (حُذف العقار أو رابط الفيديو)
                    $stale = array_values(array_diff($picked, $videoPool->pluck('id')->all()));
                @endphp

                <div x-data="{
                        pool: @js($pool),
                        picked: @js(array_values(array_intersect($picked, $videoPool->pluck('id')->all()))),
                        has(id) { return this.picked.includes(id) },
                        toggle(id) { this.has(id) ? this.picked.splice(this.picked.indexOf(id), 1) : this.picked.push(id) },
                        move(i, d) { const j = i + d; if (j < 0 || j >= this.picked.length) return;
                                     [this.picked[i], this.picked[j]] = [this.picked[j], this.picked[i]] },
                        card(id) { return this.pool.find(p => p.id === id) },
                     }" class="space-y-4">

                    {{-- حقول الإرسال: ترتيب الاختيار هو ترتيب العرض --}}
                    <template x-for="(id, i) in picked" :key="id">
                        <input type="hidden" name="videos[items][]" :value="id">
                    </template>

                    <div class="rounded-field bg-primary-50 border border-primary-100 text-primary-800 text-xs p-3 leading-relaxed">
                        اختر الفيديوهات التي تظهر في الصفحة الرئيسية من <b>العقارات التي لها رابط يوتيوب</b>، ورتّبها بالأسهم.
                        <b x-show="picked.length === 0">لم تختر شيئاً — سيعرض الموقع أحدث ٨ فيديوهات تلقائياً.</b>
                        <span x-show="picked.length > 0"><b x-text="picked.length"></b> فيديو مختار.</span>
                    </div>

                    @if ($stale)
                        <div class="rounded-field bg-warning-soft border border-warning/20 text-warning text-xs p-3">
                            {{ count($stale) }} عقار كان مختاراً ولم يعد له فيديو (أو حُذف) — أُزيل تلقائياً من القائمة.
                        </div>
                    @endif

                    {{-- المختار: بالترتيب --}}
                    <div x-show="picked.length" class="space-y-2">
                        <p class="text-xs font-bold text-gray-500">الفيديوهات المختارة (بالترتيب)</p>
                        <template x-for="(id, i) in picked" :key="'s' + id">
                            <div class="flex items-center gap-3 rounded-field bg-white border border-gray-200 p-2">
                                <span class="grid place-items-center w-6 h-6 shrink-0 rounded-full bg-primary-900 text-white text-[11px] font-bold" x-text="i + 1"></span>
                                <img :src="card(id)?.thumb" class="w-20 h-12 object-cover rounded shrink-0 bg-gray-100" alt="">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold text-ink truncate" x-text="card(id)?.title"></span>
                                    <span class="block text-[11px] text-gray-400" dir="ltr" x-text="card(id)?.ref"></span>
                                </span>
                                <button type="button" @click="move(i, -1)" :disabled="i === 0" class="grid place-items-center w-8 h-8 rounded-full text-gray-500 hover:bg-gray-100 disabled:opacity-30" title="لأعلى">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m18 15-6-6-6 6"/></svg>
                                </button>
                                <button type="button" @click="move(i, 1)" :disabled="i === picked.length - 1" class="grid place-items-center w-8 h-8 rounded-full text-gray-500 hover:bg-gray-100 disabled:opacity-30" title="لأسفل">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <button type="button" @click="toggle(id)" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10" title="إزالة"><x-icon.trash /></button>
                            </div>
                        </template>
                    </div>

                    {{-- كل الفيديوهات المتاحة --}}
                    <div>
                        <p class="text-xs font-bold text-gray-500 mb-2">كل العقارات التي لها فيديو ({{ $videoPool->count() }})</p>
                        @if ($videoPool->isEmpty())
                            <p class="rounded-field bg-gray-50 border border-gray-100 text-gray-500 text-xs p-3">
                                لا يوجد عقار له رابط يوتيوب بعد. افتح أي عقار من «إدارة العقارات» وأضف رابط الفيديو.
                            </p>
                        @else
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                <template x-for="p in pool" :key="p.id">
                                    <button type="button" @click="toggle(p.id)"
                                            :class="has(p.id) ? 'border-primary-600 ring-2 ring-primary-500/25' : 'border-gray-200 hover:border-gray-300'"
                                            class="relative rounded-field border-2 bg-white overflow-hidden text-start transition">
                                        <img :src="p.thumb" class="w-full aspect-video object-cover bg-gray-100" alt="">
                                        <span x-show="has(p.id)" class="absolute top-1.5 end-1.5 grid place-items-center w-6 h-6 rounded-full bg-primary-900 text-white">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                        </span>
                                        <span class="block p-2">
                                            <span class="block text-xs font-bold text-ink truncate" x-text="p.title"></span>
                                            <span class="block text-[10px] text-gray-400" dir="ltr" x-text="p.ref"></span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- لماذا علم العقارية (بالتصحيح: رقم + وصف) --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">لماذا علم العقارية</h3>
                <x-cms.pair label="العنوان" group="why" field="title" :ar="$s('why_us','ar','title')" :en="$s('why_us','en','title')" />
                <x-cms.pair label="الوصف" group="why" field="description" :ar="$s('why_us','ar','description')" :en="$s('why_us','en','description')" />
                {{-- بنود ديناميكية بلا حد (التصميم كان أربعة ثابتة) --}}
                <div x-data="{ rows: @js($whyRows), n: 0,
                               add() { this.rows.push({ uid: 'w-new' + (this.n++), number: '', icon: '', title_ar: '', title_en: '', description_ar: '', description_en: '' }) } }"
                     class="space-y-3">
                    <template x-for="(row, i) in rows" :key="row.uid">
                        <div class="rounded-field bg-gray-50/60 border border-gray-100 p-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-500">ميزة <span x-text="i + 1"></span></span>
                                <button type="button" @click="rows.splice(i, 1)" class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition" title="حذف الميزة"><x-icon.trash /></button>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="block text-xs text-gray-500 mb-1">الرقم (مثال: 100%)</label>
                                    <input :name="`why_items[${row.uid}][number]`" x-model="row.number" dir="ltr" class="{{ $inputCls }}"></div>
                                <div><label class="block text-xs text-gray-500 mb-1">الأيقونة (اسم Phosphor)</label>
                                    <input :name="`why_items[${row.uid}][icon]`" x-model="row.icon" dir="ltr" placeholder="ShieldCheck" class="{{ $inputCls }}"></div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><label class="block text-xs font-medium text-gray-500 mb-1">العنوان (عربي)</label>
                                    <input :name="`why_items[${row.uid}][title_ar]`" x-model="row.title_ar" class="{{ $inputCls }}"></div>
                                <div><label class="block text-xs font-medium text-gray-500 mb-1">العنوان (English)</label>
                                    <input :name="`why_items[${row.uid}][title_en]`" x-model="row.title_en" dir="ltr" class="{{ $inputCls }}"></div>
                            </div>
                            {{-- التصميم كرّر «العنوان» مرّتين — الصحيح أن الثانية هي الوصف --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div><label class="block text-xs font-medium text-gray-500 mb-1">الوصف (عربي)</label>
                                    <textarea :name="`why_items[${row.uid}][description_ar]`" x-model="row.description_ar" rows="2" class="{{ $inputCls }}"></textarea></div>
                                <div><label class="block text-xs font-medium text-gray-500 mb-1">الوصف (English)</label>
                                    <textarea :name="`why_items[${row.uid}][description_en]`" x-model="row.description_en" rows="2" dir="ltr" class="{{ $inputCls }}"></textarea></div>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="add()" class="inline-flex items-center gap-2 rounded-full border-2 border-dashed border-gray-300 hover:border-primary-500 hover:text-primary-700 text-gray-500 font-bold px-4 py-2 text-sm transition">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        إضافة ميزة
                    </button>
                </div>
            </div>

            {{-- آراء العملاء (العنوان فقط — الإدارة أسفل) --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">آراء العملاء — العنوان</h3>
                <x-cms.pair label="العنوان" group="testimonials" field="title" :ar="$s('testimonials','ar','title')" :en="$s('testimonials','en','title')" />
                <x-cms.pair label="الوصف" group="testimonials" field="description" :ar="$s('testimonials','ar','description')" :en="$s('testimonials','en','description')" />
                <p class="text-xs text-gray-400">لإدارة الآراء نفسها، استخدم القسم أسفل الصفحة.</p>
            </div>

            {{-- ابدأ رحلتك الآن --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">ابدأ رحلتك الآن (CTA)</h3>
                <x-cms.pair label="الشارة" group="cta" field="badge" :ar="$s('cta','ar','badge')" :en="$s('cta','en','badge')" />
                <x-cms.pair label="العنوان" group="cta" field="title" :ar="$s('cta','ar','title')" :en="$s('cta','en','title')" />
                <x-cms.pair label="الوصف" group="cta" field="description" type="textarea" :ar="$s('cta','ar','description')" :en="$s('cta','en','description')" />
                <x-cms.dropzone label="صورة الخلفية" name="cta[image]"
                                :media="$homeSecs['cta']->getFirstMedia('image')" />
            </div>

            <div class="flex items-center justify-end gap-3 sticky bottom-0 bg-gray-50 py-3">
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">حفظ الصفحة الرئيسية</button>
            </div>
        </form>
        @endcan

        {{-- إدارة آراء العملاء --}}
        <div class="mt-6" x-data="tstCrud()">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-bold text-ink">آراء العملاء ({{ $testimonials->count() }})</h3>
                @can('website.edit')<button @click="startAdd()" class="rounded-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-4 py-2 text-sm">+ إضافة رأي</button>@endcan
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @forelse ($testimonials as $tst)
                    @php $td = ['id' => $tst->id, 'name' => $tst->name, 'title_ar' => $tst->getTranslation('title', 'ar', false), 'title_en' => $tst->getTranslation('title', 'en', false), 'content_ar' => $tst->getTranslation('content', 'ar', false), 'content_en' => $tst->getTranslation('content', 'en', false), 'rating' => $tst->rating]; @endphp
                    <div class="rounded-card bg-white border border-gray-100 shadow-sm p-4">
                        <div class="flex items-start justify-between"><div><p class="font-semibold text-ink">{{ $tst->name }}</p><p class="text-xs text-gray-400">{{ $tst->title }}</p></div><span class="text-accent-500 text-xs">{{ str_repeat('★', $tst->rating ?? 0) }}</span></div>
                        <p class="text-sm text-gray-600 mt-2">{{ $tst->content }}</p>
                        @can('website.edit')<div class="flex gap-1 mt-3 pt-3 border-t border-gray-50">
                            <button @click='startEdit(@json($td))' class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></button>
                            <form method="POST" action="{{ route('dashboard.website.testimonials.destroy', $tst) }}" onsubmit="return confirm('حذف الرأي؟')">@csrf @method('DELETE')<button class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition"><x-icon.trash /></button></form>
                        </div>@endcan
                    </div>
                @empty
                    <p class="col-span-full text-center text-sm text-gray-400 py-6">لا توجد آراء.</p>
                @endforelse
            </div>

            <x-modal name="tst-form">
                <form :action="action" method="POST">@csrf<template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100"><h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل رأي' : 'إضافة رأي'"></h3><button type="button" @click="$dispatch('close-modal','tst-form')" class="text-gray-400"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
                    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الاسم</label><input name="name" x-model="form.name" required class="{{ $numCls }}"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1.5">التقييم</label><select name="rating" x-model="form.rating" class="{{ $numCls }}">@for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ $i }} نجوم</option>@endfor</select></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الوظيفة (عربي)</label><input name="title_ar" x-model="form.title_ar" class="{{ $numCls }}"></div>
                        <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الوظيفة (English)</label><input name="title_en" x-model="form.title_en" dir="ltr" class="{{ $numCls }}"></div>
                        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">الرأي (عربي)</label><textarea name="content_ar" x-model="form.content_ar" rows="2" required class="{{ $numCls }}"></textarea></div>
                        <div class="sm:col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1.5">الرأي (English)</label><textarea name="content_en" x-model="form.content_en" rows="2" dir="ltr" class="{{ $numCls }}"></textarea></div>
                    </div>
                    <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60"><button type="button" @click="$dispatch('close-modal','tst-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button><button class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">حفظ</button></div>
                </form>
            </x-modal>
        </div>
    </div>

    {{-- ============ الأسئلة الشائعة ============ --}}
    <div x-show="tab === 'faq'" x-cloak x-data="faqCrud()">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-ink">الأسئلة الشائعة ({{ $faqs->count() }})</h3>
            @can('website.edit')<button @click="startAdd()" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2 text-sm">+ إضافة سؤال</button>@endcan
        </div>
        <div class="space-y-2">
            @forelse ($faqs as $f)
                @php $fd = ['id' => $f->id, 'question_ar' => $f->getTranslation('question', 'ar', false), 'question_en' => $f->getTranslation('question', 'en', false), 'answer_ar' => $f->getTranslation('answer', 'ar', false), 'answer_en' => $f->getTranslation('answer', 'en', false)]; @endphp
                <div class="rounded-card bg-white border border-gray-100 shadow-sm p-4 flex items-start justify-between gap-3">
                    <div class="min-w-0"><p class="font-semibold text-ink">{{ $f->question }}</p><p class="text-sm text-gray-500 mt-1">{{ $f->answer }}</p></div>
                    @can('website.edit')<div class="flex gap-1 shrink-0">
                        <button @click='startEdit(@json($fd))' class="grid place-items-center w-8 h-8 rounded-full text-gray-400 hover:text-primary-700 hover:bg-primary-50"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></button>
                        <form method="POST" action="{{ route('dashboard.website.faqs.destroy', $f) }}" onsubmit="return confirm('حذف السؤال؟')">@csrf @method('DELETE')<button class="grid place-items-center w-8 h-8 rounded-full text-danger hover:bg-danger/10 transition"><x-icon.trash /></button></form>
                    </div>@endcan
                </div>
            @empty
                <p class="text-center text-sm text-gray-400 py-8">لا توجد أسئلة.</p>
            @endforelse
        </div>
        <x-modal name="faq-form">
            <form :action="action" method="POST">@csrf<template x-if="mode === 'edit'"><input type="hidden" name="_method" value="PUT"></template>
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100"><h3 class="font-bold text-ink" x-text="mode === 'edit' ? 'تعديل سؤال' : 'إضافة سؤال'"></h3><button type="button" @click="$dispatch('close-modal','faq-form')" class="text-gray-400"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg></button></div>
                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1.5">السؤال (عربي)</label><input name="question_ar" x-model="form.question_ar" required class="{{ $numCls }}"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1.5">السؤال (English)</label><input name="question_en" x-model="form.question_en" dir="ltr" class="{{ $numCls }}"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الإجابة (عربي)</label><textarea name="answer_ar" x-model="form.answer_ar" rows="3" required class="{{ $numCls }}"></textarea></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الإجابة (English)</label><textarea name="answer_en" x-model="form.answer_en" rows="3" dir="ltr" class="{{ $numCls }}"></textarea></div>
                </div>
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60"><button type="button" @click="$dispatch('close-modal','faq-form')" class="rounded-full px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button><button class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">حفظ</button></div>
            </form>
        </x-modal>
    </div>

    {{-- ============ الفوتر ============ --}}
    <div x-show="tab === 'footer'" x-cloak>
        @can('website.edit')
        <form method="POST" action="{{ route('dashboard.website.settings') }}" class="{{ $sectionCard }}">
            @csrf @method('PUT')
            <h3 class="font-bold text-ink">بيانات التواصل والفوتر</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">الهاتف</label><input name="contact_phone" value="{{ $settings['contact_phone'] }}" dir="ltr" class="{{ $numCls }}"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">البريد الإلكتروني</label><input name="contact_email" value="{{ $settings['contact_email'] }}" dir="ltr" class="{{ $numCls }}"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">واتساب</label><input name="contact_whatsapp" value="{{ $settings['contact_whatsapp'] }}" dir="ltr" class="{{ $numCls }}"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1.5">العنوان</label><input name="contact_address" value="{{ $settings['contact_address'] }}" class="{{ $numCls }}"></div>
                @foreach (['facebook' => 'فيسبوك', 'instagram' => 'إنستجرام', 'twitter' => 'تويتر (X)', 'linkedin' => 'لينكدإن'] as $k => $label)
                    <div><label class="block text-sm font-medium text-gray-700 mb-1.5">{{ $label }}</label><input name="social_{{ $k }}" value="{{ $settings['social_'.$k] }}" dir="ltr" placeholder="https://" class="{{ $numCls }}"></div>
                @endforeach
            </div>
            <div class="flex justify-end"><button class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">حفظ</button></div>
        </form>
        @endcan
    </div>

    {{-- ============ من نحن ============ --}}
    @php
        $ab = fn ($sec, $loc, $field, $def = '') => data_get($about, "$sec.$loc.$field", $def);
        $storyStats = $ab('story', 'ar', 'stats', []);
        $valueItems = $ab('values', 'ar', 'items', []);
    @endphp
    <div x-show="tab === 'about'" x-cloak>
        @can('website.edit')
        <form method="POST" action="{{ route('dashboard.website.about') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf @method('PUT')

            {{-- الهيرو --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">الواجهة (الهيرو)</h3>
                <x-cms.pair label="الشارة العلوية" group="about_hero" field="badge" :ar="$ab('hero','ar','badge')" :en="$ab('hero','en','badge')" />
                <x-cms.pair label="العنوان" group="about_hero" field="title" :ar="$ab('hero','ar','title')" :en="$ab('hero','en','title')" />
                <x-cms.pair label="الوصف" group="about_hero" field="description" type="textarea" :ar="$ab('hero','ar','description')" :en="$ab('hero','en','description')" />
            </div>

            {{-- قصتنا --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">قصتنا (بدأت رحلتنا)</h3>
                <x-cms.pair label="العنوان" group="about_story" field="title" :ar="$ab('story','ar','title')" :en="$ab('story','en','title')" />
                <x-cms.pair label="الوصف" group="about_story" field="description" type="textarea" :rows="4" :ar="$ab('story','ar','description')" :en="$ab('story','en','description')" />
                <x-cms.dropzone label="الصورة التوضيحية" name="about_story[image]"
                                :media="$aboutSecs['story']->getFirstMedia('image')" />
                <div class="space-y-3 pt-2 border-t border-gray-50">
                    <p class="text-xs font-semibold text-gray-500">عدّادات (كارتان)</p>
                    @for ($i = 0; $i < 2; $i++)
                        @php $it = $storyStats[$i] ?? []; @endphp
                        <div class="rounded-field bg-gray-50/60 p-3 space-y-2">
                            <div><label class="block text-xs text-gray-500 mb-1">الرقم (مثال: 100+)</label><input name="story_stats[{{ $i }}][number]" value="{{ $it['number'] ?? '' }}" dir="ltr" class="{{ $numCls }}"></div>
                            <x-cms.pair label="العنوان" group="story_stats[{{ $i }}]" field="title" :ar="data_get($it, 'title.ar')" :en="data_get($it, 'title.en')" />
                            <x-cms.pair label="الوصف" group="story_stats[{{ $i }}]" field="description" :ar="data_get($it, 'description.ar')" :en="data_get($it, 'description.en')" />
                        </div>
                    @endfor
                </div>
            </div>

            {{-- قيمنا --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">قيمنا التي نعتز بها</h3>
                <x-cms.pair label="العنوان" group="about_values" field="title" :ar="$ab('values','ar','title')" :en="$ab('values','en','title')" />
                <x-cms.pair label="الوصف" group="about_values" field="subtitle" :ar="$ab('values','ar','subtitle')" :en="$ab('values','en','subtitle')" />
                <div class="space-y-3">
                    <p class="text-xs font-semibold text-gray-500">الكروت (رؤيتنا / رسالتنا / قيمنا)</p>
                    @for ($i = 0; $i < 3; $i++)
                        @php $it = $valueItems[$i] ?? []; @endphp
                        <div class="rounded-field bg-gray-50/60 p-3 space-y-2">
                            <div><label class="block text-xs text-gray-500 mb-1">الأيقونة (اسم Phosphor)</label><input name="value_items[{{ $i }}][icon]" value="{{ $it['icon'] ?? '' }}" dir="ltr" placeholder="Eye / Target / ShieldCheck" class="{{ $numCls }}"></div>
                            <x-cms.pair label="العنوان" group="value_items[{{ $i }}]" field="title" :ar="data_get($it, 'title.ar')" :en="data_get($it, 'title.en')" />
                            <x-cms.pair label="الوصف" group="value_items[{{ $i }}]" field="description" :ar="data_get($it, 'description.ar')" :en="data_get($it, 'description.en')" />
                        </div>
                    @endfor
                </div>
            </div>

            {{-- عائلة علم العقارية --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">عائلة علم العقارية (الفريق)</h3>
                <x-cms.pair label="العنوان" group="about_team" field="title" :ar="$ab('team','ar','title')" :en="$ab('team','en','title')" />
                <x-cms.pair label="الوصف" group="about_team" field="subtitle" :ar="$ab('team','ar','subtitle')" :en="$ab('team','en','subtitle')" />
                <p class="text-xs text-gray-400">الوكلاء يظهرون تلقائياً من «المشرفين» (المُعلَّمين كوكلاء).</p>
            </div>

            <div class="flex items-center justify-end sticky bottom-0 bg-gray-50 py-3">
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">حفظ صفحة «من نحن»</button>
            </div>
        </form>
        @endcan
    </div>

    {{-- ============ قسم SEO ============ --}}
    <div x-show="tab === 'seo'" x-cloak>
        @can('website.edit')
        <form method="POST" action="{{ route('dashboard.website.seo') }}">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach ($seoPages as $slug => $page)
                    <div class="{{ $sectionCard }}">
                        <h3 class="font-bold text-ink flex items-center gap-2">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary-500"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20z"/></svg>
                            {{ $page->name }}
                        </h3>
                        <x-cms.pair label="عنوان الصفحة (Title)" group="seo[{{ $slug }}]" field="title" :ar="$page->getTranslation('seo_title', 'ar', false)" :en="$page->getTranslation('seo_title', 'en', false)" />
                        <x-cms.pair label="الوصف (Meta Description)" group="seo[{{ $slug }}]" field="description" type="textarea" :ar="$page->getTranslation('seo_description', 'ar', false)" :en="$page->getTranslation('seo_description', 'en', false)" />
                        <x-cms.pair label="الكلمات المفتاحية" group="seo[{{ $slug }}]" field="keywords" :ar="$page->getTranslation('seo_keywords', 'ar', false)" :en="$page->getTranslation('seo_keywords', 'en', false)" />
                    </div>
                @endforeach
            </div>
            <div class="flex items-center justify-end sticky bottom-0 bg-gray-50 py-3 mt-2">
                <button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">حفظ إعدادات SEO</button>
            </div>
        </form>
        @endcan
    </div>

    {{-- ============ الشروط والأحكام + سياسة الخصوصية ============ --}}
    @foreach (['terms' => ['label' => 'الشروط والأحكام', 'data' => $terms], 'privacy' => ['label' => 'سياسة الخصوصية', 'data' => $privacy]] as $slug => $legal)
        <div x-show="tab === '{{ $slug }}'" x-cloak>
            @can('website.edit')
            <form method="POST" action="{{ route('dashboard.website.legal', $slug) }}" class="{{ $sectionCard }}">
                @csrf @method('PUT')
                <h3 class="font-bold text-ink">{{ $legal['label'] }}</h3>
                <p class="text-xs text-gray-400 -mt-2">يمكنك كتابة نص عادي أو HTML بسيط للتنسيق.</p>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">المحتوى (عربي)</label>
                    <textarea name="body_ar" rows="12" class="{{ $numCls }}">{{ $legal['data']['ar'] }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">المحتوى (English)</label>
                    <textarea name="body_en" rows="12" dir="ltr" class="{{ $numCls }}">{{ $legal['data']['en'] }}</textarea>
                </div>
                <div class="flex justify-end"><button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">حفظ {{ $legal['label'] }}</button></div>
            </form>
            @endcan
        </div>
    @endforeach

    {{-- ============ العروض العقارية + العقارات (رأس الصفحة) ============ --}}
    @foreach (['offers' => ['label' => 'العروض العقارية', 'data' => $offersHeader], 'properties' => ['label' => 'العقارات', 'data' => $propsHeader]] as $slug => $lst)
        <div x-show="tab === '{{ $slug }}'" x-cloak>
            @can('website.edit')
            <form method="POST" action="{{ route('dashboard.website.listing', $slug) }}" class="{{ $sectionCard }}">
                @csrf @method('PUT')
                <h3 class="font-bold text-ink">رأس صفحة {{ $lst['label'] }}</h3>
                <p class="text-xs text-gray-400 -mt-2">قائمة العقارات نفسها تظهر تلقائياً من قاعدة البيانات — هنا تحرّر رأس الصفحة فقط.</p>
                <x-cms.pair label="العنوان الرئيسي" group="header" field="title" :ar="data_get($lst['data'], 'ar.title')" :en="data_get($lst['data'], 'en.title')" />
                <x-cms.pair label="الوصف" group="header" field="description" type="textarea" :ar="data_get($lst['data'], 'ar.description')" :en="data_get($lst['data'], 'en.description')" />
                <div class="flex justify-end"><button type="submit" class="rounded-full bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">حفظ رأس الصفحة</button></div>
            </form>
            @endcan
        </div>
    @endforeach
</div>

<script>
    function tstCrud() {
        return {
            mode: 'add', action: '', form: { name: '', title_ar: '', title_en: '', content_ar: '', content_en: '', rating: '5' },
            startAdd() { this.mode = 'add'; this.form = { name: '', title_ar: '', title_en: '', content_ar: '', content_en: '', rating: '5' }; this.action = '{{ route('dashboard.website.testimonials.store') }}'; this.$dispatch('open-modal', 'tst-form'); },
            startEdit(t) { this.mode = 'edit'; this.form = { name: t.name ?? '', title_ar: t.title_ar ?? '', title_en: t.title_en ?? '', content_ar: t.content_ar ?? '', content_en: t.content_en ?? '', rating: String(t.rating ?? 5) }; this.action = '{{ url('dashboard/website/testimonials') }}/' + t.id; this.$dispatch('open-modal', 'tst-form'); },
        };
    }
    function faqCrud() {
        return {
            mode: 'add', action: '', form: { question_ar: '', question_en: '', answer_ar: '', answer_en: '' },
            startAdd() { this.mode = 'add'; this.form = { question_ar: '', question_en: '', answer_ar: '', answer_en: '' }; this.action = '{{ route('dashboard.website.faqs.store') }}'; this.$dispatch('open-modal', 'faq-form'); },
            startEdit(f) { this.mode = 'edit'; this.form = { question_ar: f.question_ar ?? '', question_en: f.question_en ?? '', answer_ar: f.answer_ar ?? '', answer_en: f.answer_en ?? '' }; this.action = '{{ url('dashboard/website/faqs') }}/' + f.id; this.$dispatch('open-modal', 'faq-form'); },
        };
    }
</script>
@endsection
