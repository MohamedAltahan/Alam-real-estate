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
    $heroImages = $s('hero', 'ar', 'images', []);
    $areaItems = $s('areas', 'ar', 'items', []);
    $videoItems = $s('videos', 'ar', 'items', []);
    $whyItems = $s('why_us', 'ar', 'items', []);
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
            <button @click="tab = '{{ $k }}'" :class="tab === '{{ $k }}' ? 'bg-primary-900 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'" class="rounded-field px-4 py-2 text-sm font-medium transition">{{ $label }}</button>
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
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">صور الخلفية المتحركة</label>
                    @if (count($heroImages))<div class="flex flex-wrap gap-2 mb-2">@foreach ($heroImages as $img)<img src="{{ Storage::url($img) }}" class="w-16 h-16 object-cover rounded-field" alt="">@endforeach</div>@endif
                    <input type="file" name="hero[images][]" accept="image/*" multiple class="{{ $fileCls }}">
                </div>
            </div>

            {{-- عروض عقارية مميزة --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">عروض عقارية مميزة</h3>
                <x-cms.pair label="العنوان" group="featured" field="title" :ar="$s('featured','ar','title')" :en="$s('featured','en','title')" />
                <x-cms.pair label="الوصف" group="featured" field="description" type="textarea" :ar="$s('featured','ar','description')" :en="$s('featured','en','description')" />
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">صورة توضيحية</label>
                    @if ($s('featured', 'ar', 'image'))<img src="{{ Storage::url($s('featured', 'ar', 'image')) }}" class="w-20 h-20 object-cover rounded-field mb-2" alt="">@endif
                    <input type="file" name="featured[image]" accept="image/*" class="{{ $fileCls }}">
                </div>
            </div>

            {{-- التغطية الجغرافية --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">التغطية الجغرافية (أفضل المناطق)</h3>
                <x-cms.pair label="العنوان" group="areas" field="title" :ar="$s('areas','ar','title')" :en="$s('areas','en','title')" />
                <x-cms.pair label="الوصف" group="areas" field="description" :ar="$s('areas','ar','description')" :en="$s('areas','en','description')" />
                <div class="space-y-2">
                    @for ($i = 0; $i < $slots['areas']; $i++)
                        @php $it = $areaItems[$i] ?? []; @endphp
                        <div class="grid grid-cols-12 gap-2 items-end rounded-field bg-gray-50/60 p-2">
                            <div class="col-span-5"><label class="block text-xs text-gray-500 mb-1">المنطقة</label>
                                <select name="area_items[{{ $i }}][area_id]" class="{{ $numCls }}"><option value="">— لا شيء —</option>@foreach ($areas as $a)<option value="{{ $a->id }}" @selected(($it['area_id'] ?? null) == $a->id)>{{ $a->name }}</option>@endforeach</select>
                            </div>
                            <div class="col-span-3"><label class="block text-xs text-gray-500 mb-1">عدد العقارات</label><input name="area_items[{{ $i }}][count]" value="{{ $it['count'] ?? '' }}" class="{{ $numCls }}"></div>
                            <div class="col-span-4"><label class="block text-xs text-gray-500 mb-1">صورة @if (! empty($it['image']))<span class="text-success">✓</span>@endif</label><input type="file" name="area_items[{{ $i }}][image]" accept="image/*" class="{{ $fileCls }}"></div>
                        </div>
                    @endfor
                </div>
            </div>

            {{-- فديوهات تعرفية --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">فديوهات تعرفية (تعريف الخدمات)</h3>
                <x-cms.pair label="العنوان" group="videos" field="title" :ar="$s('videos','ar','title')" :en="$s('videos','en','title')" />
                <x-cms.pair label="الوصف" group="videos" field="description" :ar="$s('videos','ar','description')" :en="$s('videos','en','description')" />
                <div class="space-y-3">
                    @for ($i = 0; $i < $slots['videos']; $i++)
                        @php $it = $videoItems[$i] ?? []; @endphp
                        <div class="rounded-field bg-gray-50/60 p-3 space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="block text-xs text-gray-500 mb-1">رابط يوتيوب</label><input name="video_items[{{ $i }}][youtube_url]" value="{{ $it['youtube_url'] ?? '' }}" dir="ltr" class="{{ $numCls }}"></div>
                                <div><label class="block text-xs text-gray-500 mb-1">صورة @if (! empty($it['image']))<span class="text-success">✓</span>@endif</label><input type="file" name="video_items[{{ $i }}][image]" accept="image/*" class="{{ $fileCls }}"></div>
                            </div>
                            <x-cms.pair label="العنوان" group="video_items[{{ $i }}]" field="title" :ar="data_get($it, 'title.ar')" :en="data_get($it, 'title.en')" />
                        </div>
                    @endfor
                </div>
            </div>

            {{-- لماذا علم العقارية (بالتصحيح: رقم + وصف) --}}
            <div class="{{ $sectionCard }}">
                <h3 class="font-bold text-ink">لماذا علم العقارية</h3>
                <x-cms.pair label="العنوان" group="why" field="title" :ar="$s('why_us','ar','title')" :en="$s('why_us','en','title')" />
                <x-cms.pair label="الوصف" group="why" field="description" :ar="$s('why_us','ar','description')" :en="$s('why_us','en','description')" />
                <div class="space-y-3">
                    @for ($i = 0; $i < $slots['why']; $i++)
                        @php $it = $whyItems[$i] ?? []; @endphp
                        <div class="rounded-field bg-gray-50/60 p-3 space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <div><label class="block text-xs text-gray-500 mb-1">الرقم (مثال: 100%)</label><input name="why_items[{{ $i }}][number]" value="{{ $it['number'] ?? '' }}" dir="ltr" class="{{ $numCls }}"></div>
                                <div><label class="block text-xs text-gray-500 mb-1">الأيقونة (اسم Phosphor)</label><input name="why_items[{{ $i }}][icon]" value="{{ $it['icon'] ?? '' }}" dir="ltr" placeholder="ShieldCheck" class="{{ $numCls }}"></div>
                            </div>
                            <x-cms.pair label="العنوان" group="why_items[{{ $i }}]" field="title" :ar="data_get($it, 'title.ar')" :en="data_get($it, 'title.en')" />
                            <x-cms.pair label="الوصف" group="why_items[{{ $i }}]" field="description" :ar="data_get($it, 'description.ar')" :en="data_get($it, 'description.en')" />
                        </div>
                    @endfor
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
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">صورة الخلفية</label>
                    @if ($s('cta', 'ar', 'image'))<img src="{{ Storage::url($s('cta', 'ar', 'image')) }}" class="w-28 h-16 object-cover rounded-field mb-2" alt="">@endif
                    <input type="file" name="cta[image]" accept="image/*" class="{{ $fileCls }}">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 sticky bottom-0 bg-gray-50 py-3">
                <button type="submit" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">حفظ الصفحة الرئيسية</button>
            </div>
        </form>
        @endcan

        {{-- إدارة آراء العملاء --}}
        <div class="mt-6" x-data="tstCrud()">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-bold text-ink">آراء العملاء ({{ $testimonials->count() }})</h3>
                @can('website.edit')<button @click="startAdd()" class="rounded-field bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-4 py-2 text-sm">+ إضافة رأي</button>@endcan
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @forelse ($testimonials as $tst)
                    @php $td = ['id' => $tst->id, 'name' => $tst->name, 'title_ar' => $tst->getTranslation('title', 'ar', false), 'title_en' => $tst->getTranslation('title', 'en', false), 'content_ar' => $tst->getTranslation('content', 'ar', false), 'content_en' => $tst->getTranslation('content', 'en', false), 'rating' => $tst->rating]; @endphp
                    <div class="rounded-card bg-white border border-gray-100 shadow-sm p-4">
                        <div class="flex items-start justify-between"><div><p class="font-semibold text-ink">{{ $tst->name }}</p><p class="text-xs text-gray-400">{{ $tst->title }}</p></div><span class="text-accent-500 text-xs">{{ str_repeat('★', $tst->rating ?? 0) }}</span></div>
                        <p class="text-sm text-gray-600 mt-2">{{ $tst->content }}</p>
                        @can('website.edit')<div class="flex gap-1 mt-3 pt-3 border-t border-gray-50">
                            <button @click='startEdit(@json($td))' class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-primary-700 hover:bg-primary-50"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></button>
                            <form method="POST" action="{{ route('dashboard.website.testimonials.destroy', $tst) }}" onsubmit="return confirm('حذف الرأي؟')">@csrf @method('DELETE')<button class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-danger hover:bg-danger/10"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button></form>
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
                    <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60"><button type="button" @click="$dispatch('close-modal','tst-form')" class="rounded-field px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button><button class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">حفظ</button></div>
                </form>
            </x-modal>
        </div>
    </div>

    {{-- ============ الأسئلة الشائعة ============ --}}
    <div x-show="tab === 'faq'" x-cloak x-data="faqCrud()">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-ink">الأسئلة الشائعة ({{ $faqs->count() }})</h3>
            @can('website.edit')<button @click="startAdd()" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-4 py-2 text-sm">+ إضافة سؤال</button>@endcan
        </div>
        <div class="space-y-2">
            @forelse ($faqs as $f)
                @php $fd = ['id' => $f->id, 'question_ar' => $f->getTranslation('question', 'ar', false), 'question_en' => $f->getTranslation('question', 'en', false), 'answer_ar' => $f->getTranslation('answer', 'ar', false), 'answer_en' => $f->getTranslation('answer', 'en', false)]; @endphp
                <div class="rounded-card bg-white border border-gray-100 shadow-sm p-4 flex items-start justify-between gap-3">
                    <div class="min-w-0"><p class="font-semibold text-ink">{{ $f->question }}</p><p class="text-sm text-gray-500 mt-1">{{ $f->answer }}</p></div>
                    @can('website.edit')<div class="flex gap-1 shrink-0">
                        <button @click='startEdit(@json($fd))' class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-primary-700 hover:bg-primary-50"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/></svg></button>
                        <form method="POST" action="{{ route('dashboard.website.faqs.destroy', $f) }}" onsubmit="return confirm('حذف السؤال؟')">@csrf @method('DELETE')<button class="grid place-items-center w-8 h-8 rounded-lg text-gray-400 hover:text-danger hover:bg-danger/10"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button></form>
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
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/60"><button type="button" @click="$dispatch('close-modal','faq-form')" class="rounded-field px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-100">إلغاء</button><button class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">حفظ</button></div>
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
            <div class="flex justify-end"><button class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-5 py-2.5 text-sm">حفظ</button></div>
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
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">الصورة التوضيحية</label>
                    @if ($ab('story', 'ar', 'image'))<img src="{{ Storage::url($ab('story', 'ar', 'image')) }}" class="w-24 h-24 object-cover rounded-field mb-2" alt="">@endif
                    <input type="file" name="about_story[image]" accept="image/*" class="{{ $fileCls }}">
                </div>
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
                <button type="submit" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">حفظ صفحة «من نحن»</button>
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
                <button type="submit" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">حفظ إعدادات SEO</button>
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
                <div class="flex justify-end"><button type="submit" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">حفظ {{ $legal['label'] }}</button></div>
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
                <div class="flex justify-end"><button type="submit" class="rounded-field bg-primary-900 hover:bg-primary-800 text-white font-semibold px-6 py-2.5 text-sm">حفظ رأس الصفحة</button></div>
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
