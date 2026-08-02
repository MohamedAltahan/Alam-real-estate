<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Faq;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Property;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    private const HOME_SECTIONS = ['hero', 'featured', 'areas', 'videos', 'why_us', 'testimonials', 'cta'];

    private const ABOUT_SECTIONS = ['hero', 'story', 'values', 'team'];

    /** الصفحات التي تُدار السيو الخاصة بها */
    private const SEO_PAGES = [
        'home' => 'الصفحة الرئيسية',
        'properties' => 'العقارات',
        'about' => 'من نحن',
        'offers' => 'العروض العقارية',
        'areas' => 'أفضل المناطق',
        'contact' => 'تواصل معنا',
        'property-details' => 'تفاصيل العقار',
        'agent' => 'تفاصيل الوكيل',
    ];

    public function index(): View
    {
        $home = $this->homePage();
        $aboutPage = $this->aboutPage();

        return view('dashboard.website.index', [
            'sections' => $this->loadSections($home, self::HOME_SECTIONS),
            'about' => $this->loadSections($aboutPage, self::ABOUT_SECTIONS),
            // نماذج الأقسام نفسها — منها تُقرأ الصور (media library)
            'homeSecs' => $this->sectionModels($home, self::HOME_SECTIONS),
            'aboutSecs' => $this->sectionModels($aboutPage, self::ABOUT_SECTIONS),
            'seoPages' => $this->seoPages(),
            'terms' => $this->legalBody('terms'),
            'privacy' => $this->legalBody('privacy'),
            'offersHeader' => $this->listingHeader('offers'),
            'propsHeader' => $this->listingHeader('properties'),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->get(),
            // العقارات المرشَّحة لقسم الفيديوهات (لها رابط يوتيوب فقط)
            'videoPool' => Property::withVideo()->latest()->get(['id', 'reference_code', 'title', 'video_url']),
            'faqs' => Faq::orderBy('sort_order')->get(),
            'testimonials' => Testimonial::orderBy('sort_order')->get(),
            'settings' => $this->currentSettings(),
        ]);
    }

    // ===== حفظ الصفحة الرئيسية =====
    public function updateHomepage(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('website.edit'), 403);

        $this->validateImages($request);

        $home = $this->homePage();

        // --- Hero: صور متعدّدة بلا حد (media library) ---
        $h = $request->input('hero', []);
        $hero = $this->section($home, 'hero', 0);
        $this->syncMedia($request, $hero, 'images', 'hero.images', (array) $request->input('hero.images_removed', []));

        $this->fill($hero,
            shared: ['stats' => [
                'properties' => $h['stat_properties'] ?? '', 'clients' => $h['stat_clients'] ?? '', 'areas' => $h['stat_areas'] ?? '',
            ]],
            ar: ['badge' => $h['badge_ar'] ?? '', 'title' => $h['title_ar'] ?? '', 'subtitle' => $h['subtitle_ar'] ?? '', 'description' => $h['description_ar'] ?? ''],
            en: ['badge' => $h['badge_en'] ?? '', 'title' => $h['title_en'] ?? '', 'subtitle' => $h['subtitle_en'] ?? '', 'description' => $h['description_en'] ?? '']
        );

        // --- Featured ---
        $ft = $request->input('featured', []);
        $featured = $this->section($home, 'featured', 1);
        $this->syncMedia($request, $featured, 'image', 'featured.image', (array) $request->input('featured.image_removed', []), single: true);

        $this->fill($featured, shared: [],
            ar: ['title' => $ft['title_ar'] ?? '', 'description' => $ft['description_ar'] ?? ''],
            en: ['title' => $ft['title_en'] ?? '', 'description' => $ft['description_en'] ?? '']
        );

        // --- Areas: بنود ديناميكية بلا حد، صورة كل بند في مجموعة مستقلة ---
        $ar = $request->input('areas', []);
        $areas = $this->section($home, 'areas', 2);
        $areaItems = [];

        foreach ((array) $request->input('area_items', []) as $uid => $it) {
            if (empty($it['area_id'])) {
                continue;
            }
            // اسم المجموعة = المعرّف نفسه بعد التنقية (idempotent — لا تُضاف البادئة مرّتين)
            $collection = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $uid);
            $collection = str_starts_with($collection, 'area-') ? $collection : 'area-'.$collection;
            $this->syncMedia($request, $areas, $collection, "area_items.$uid.image", (array) ($it['image_removed'] ?? []), single: true);

            $areaItems[] = [
                'area_id' => $it['area_id'],
                'count' => $it['count'] ?? '',
                'collection' => $collection,
            ];
        }

        $this->pruneCollections($areas, array_column($areaItems, 'collection'), 'area-');

        $this->fill($areas, shared: ['items' => $areaItems],
            ar: ['title' => $ar['title_ar'] ?? '', 'description' => $ar['description_ar'] ?? ''],
            en: ['title' => $ar['title_en'] ?? '', 'description' => $ar['description_en'] ?? '']
        );

        // --- Videos: اختيار وترتيب من عقارات لها رابط يوتيوب (فارغ = أحدث ٨ تلقائياً) ---
        $vd = $request->input('videos', []);
        $picked = collect((array) ($vd['items'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        // نتجاهل أي عقار فقد فيديوه أو حُذف منذ الاختيار
        $valid = Property::withVideo()->whereIn('id', $picked)->pluck('id')->all();

        $this->put($home, 'videos', 3,
            shared: ['items' => $picked->filter(fn ($id) => in_array($id, $valid, true))->values()->all()],
            ar: ['title' => $vd['title_ar'] ?? '', 'description' => $vd['description_ar'] ?? ''],
            en: ['title' => $vd['title_en'] ?? '', 'description' => $vd['description_en'] ?? '']
        );

        // --- Why us: بنود ديناميكية بلا حد ---
        $wy = $request->input('why', []);
        $whyItems = [];
        foreach ((array) $request->input('why_items', []) as $it) {
            if (blank($it['title_ar'] ?? null) && blank($it['number'] ?? null)) {
                continue;
            }
            $whyItems[] = [
                'number' => $it['number'] ?? '',
                'icon' => $it['icon'] ?? '',
                'title' => ['ar' => $it['title_ar'] ?? '', 'en' => $it['title_en'] ?? ''],
                'description' => ['ar' => $it['description_ar'] ?? '', 'en' => $it['description_en'] ?? ''],
            ];
        }
        $this->put($home, 'why_us', 4,
            shared: ['items' => $whyItems],
            ar: ['title' => $wy['title_ar'] ?? '', 'description' => $wy['description_ar'] ?? ''],
            en: ['title' => $wy['title_en'] ?? '', 'description' => $wy['description_en'] ?? '']
        );

        // --- Testimonials header ---
        $ts = $request->input('testimonials', []);
        $this->put($home, 'testimonials', 5,
            shared: [],
            ar: ['title' => $ts['title_ar'] ?? '', 'description' => $ts['description_ar'] ?? ''],
            en: ['title' => $ts['title_en'] ?? '', 'description' => $ts['description_en'] ?? '']
        );

        // --- CTA ---
        $ct = $request->input('cta', []);
        $cta = $this->section($home, 'cta', 6);
        $this->syncMedia($request, $cta, 'image', 'cta.image', (array) $request->input('cta.image_removed', []), single: true);

        $this->fill($cta, shared: [],
            ar: ['badge' => $ct['badge_ar'] ?? '', 'title' => $ct['title_ar'] ?? '', 'description' => $ct['description_ar'] ?? ''],
            en: ['badge' => $ct['badge_en'] ?? '', 'title' => $ct['title_en'] ?? '', 'description' => $ct['description_en'] ?? '']
        );

        return back()->with('success', 'تم حفظ محتوى الصفحة الرئيسية.');
    }

    // ===== حفظ صفحة "من نحن" =====
    public function updateAbout(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('website.edit'), 403);

        $request->validate(['about_story.image' => PageSection::imageRules()], [
            'max' => 'حجم الصورة يتجاوز الحد المسموح (٦ ميجابايت).',
        ]);

        $page = $this->aboutPage();

        // Hero
        $h = $request->input('about_hero', []);
        $this->put($page, 'hero', 0, shared: [],
            ar: ['badge' => $h['badge_ar'] ?? '', 'title' => $h['title_ar'] ?? '', 'description' => $h['description_ar'] ?? ''],
            en: ['badge' => $h['badge_en'] ?? '', 'title' => $h['title_en'] ?? '', 'description' => $h['description_en'] ?? '']);

        // Story (+ إحصاءات ديناميكية)
        $st = $request->input('about_story', []);
        $stats = [];
        foreach ((array) $request->input('story_stats', []) as $it) {
            if (blank($it['number'] ?? null) && blank($it['title_ar'] ?? null)) {
                continue;
            }
            $stats[] = [
                'number' => $it['number'] ?? '',
                'title' => ['ar' => $it['title_ar'] ?? '', 'en' => $it['title_en'] ?? ''],
                'description' => ['ar' => $it['description_ar'] ?? '', 'en' => $it['description_en'] ?? ''],
            ];
        }

        $story = $this->section($page, 'story', 1);
        $this->syncMedia($request, $story, 'image', 'about_story.image', (array) $request->input('about_story.image_removed', []), single: true);

        $this->fill($story, shared: ['stats' => $stats],
            ar: ['title' => $st['title_ar'] ?? '', 'description' => $st['description_ar'] ?? ''],
            en: ['title' => $st['title_en'] ?? '', 'description' => $st['description_en'] ?? '']);

        // Values (+ 3 cards)
        $vl = $request->input('about_values', []);
        $items = [];
        foreach ([0, 1, 2] as $i) {
            $it = $request->input("value_items.$i", []);
            $items[] = [
                'icon' => $it['icon'] ?? '',
                'title' => ['ar' => $it['title_ar'] ?? '', 'en' => $it['title_en'] ?? ''],
                'description' => ['ar' => $it['description_ar'] ?? '', 'en' => $it['description_en'] ?? ''],
            ];
        }
        $this->put($page, 'values', 2, shared: ['items' => $items],
            ar: ['title' => $vl['title_ar'] ?? '', 'subtitle' => $vl['subtitle_ar'] ?? ''],
            en: ['title' => $vl['title_en'] ?? '', 'subtitle' => $vl['subtitle_en'] ?? '']);

        // Team header (الوكلاء ديناميكيون من جدول المستخدمين)
        $tm = $request->input('about_team', []);
        $this->put($page, 'team', 3, shared: [],
            ar: ['title' => $tm['title_ar'] ?? '', 'subtitle' => $tm['subtitle_ar'] ?? ''],
            en: ['title' => $tm['title_en'] ?? '', 'subtitle' => $tm['subtitle_en'] ?? '']);

        return back()->with('success', 'تم حفظ محتوى صفحة «من نحن».');
    }

    // ===== حفظ SEO لكل الصفحات =====
    public function updateSeo(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('website.edit'), 403);

        foreach (array_keys(self::SEO_PAGES) as $slug) {
            $d = $request->input("seo.$slug", []);
            $page = Page::where('slug', $slug)->first();
            if (! $page) {
                continue;
            }
            $page->update([
                'seo_title' => ['ar' => $d['title_ar'] ?? '', 'en' => $d['title_en'] ?? ''],
                'seo_description' => ['ar' => $d['description_ar'] ?? '', 'en' => $d['description_en'] ?? ''],
                'seo_keywords' => ['ar' => $d['keywords_ar'] ?? '', 'en' => $d['keywords_en'] ?? ''],
            ]);
        }

        return back()->with('success', 'تم حفظ إعدادات محركات البحث (SEO).');
    }

    // ===== الشروط والأحكام / سياسة الخصوصية =====
    public function updateLegal(Request $request, string $slug): RedirectResponse
    {
        abort_unless(in_array($slug, ['terms', 'privacy']), 404);
        abort_unless($request->user()->can('website.edit'), 403);

        $d = $request->validate(['body_ar' => ['nullable', 'string'], 'body_en' => ['nullable', 'string']]);

        $sec = $this->legalPage($slug)->sections()->firstOrCreate(['key' => 'body'], ['sort_order' => 0]);
        $sec->setTranslation('content', 'ar', ['body' => $d['body_ar'] ?? '']);
        $sec->setTranslation('content', 'en', ['body' => $d['body_en'] ?? '']);
        $sec->save();

        return back()->with('success', 'تم حفظ الصفحة.');
    }

    // ===== العروض العقارية / العقارات (رأس الصفحة فقط — المحتوى ديناميكي) =====
    public function updateListing(Request $request, string $slug): RedirectResponse
    {
        abort_unless(in_array($slug, ['offers', 'properties']), 404);
        abort_unless($request->user()->can('website.edit'), 403);

        $h = $request->input('header', []);
        $sec = $this->seoPages()[$slug]->sections()->firstOrCreate(['key' => 'header'], ['sort_order' => 0]);
        $sec->setTranslation('content', 'ar', ['title' => $h['title_ar'] ?? '', 'description' => $h['description_ar'] ?? '']);
        $sec->setTranslation('content', 'en', ['title' => $h['title_en'] ?? '', 'description' => $h['description_en'] ?? '']);
        $sec->save();

        return back()->with('success', 'تم حفظ رأس الصفحة.');
    }

    // ===== الأسئلة الشائعة =====
    public function storeFaq(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('website.edit'), 403);
        $d = $this->faqData($request);
        Faq::create([
            'question' => ['ar' => $d['question_ar'], 'en' => $d['question_en'] ?: $d['question_ar']],
            'answer' => ['ar' => $d['answer_ar'], 'en' => $d['answer_en'] ?: $d['answer_ar']],
            'sort_order' => (Faq::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'تمت إضافة السؤال.');
    }

    public function updateFaq(Request $request, Faq $faq): RedirectResponse
    {
        abort_unless($request->user()->can('website.edit'), 403);
        $d = $this->faqData($request);
        $faq->update([
            'question' => ['ar' => $d['question_ar'], 'en' => $d['question_en'] ?: $d['question_ar']],
            'answer' => ['ar' => $d['answer_ar'], 'en' => $d['answer_en'] ?: $d['answer_ar']],
        ]);

        return back()->with('success', 'تم تحديث السؤال.');
    }

    public function destroyFaq(Request $request, Faq $faq): RedirectResponse
    {
        abort_unless($request->user()->can('website.edit'), 403);
        $faq->delete();

        return back()->with('success', 'تم حذف السؤال.');
    }

    // ===== آراء العملاء =====
    public function storeTestimonial(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('website.edit'), 403);
        $d = $this->testimonialData($request);
        Testimonial::create([
            'name' => $d['name'],
            'title' => ['ar' => $d['title_ar'], 'en' => $d['title_en'] ?: $d['title_ar']],
            'content' => ['ar' => $d['content_ar'], 'en' => $d['content_en'] ?: $d['content_ar']],
            'rating' => $d['rating'],
            'sort_order' => (Testimonial::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'تمت إضافة الرأي.');
    }

    public function updateTestimonial(Request $request, Testimonial $testimonial): RedirectResponse
    {
        abort_unless($request->user()->can('website.edit'), 403);
        $d = $this->testimonialData($request);
        $testimonial->update([
            'name' => $d['name'],
            'title' => ['ar' => $d['title_ar'], 'en' => $d['title_en'] ?: $d['title_ar']],
            'content' => ['ar' => $d['content_ar'], 'en' => $d['content_en'] ?: $d['content_ar']],
            'rating' => $d['rating'],
        ]);

        return back()->with('success', 'تم تحديث الرأي.');
    }

    public function destroyTestimonial(Request $request, Testimonial $testimonial): RedirectResponse
    {
        abort_unless($request->user()->can('website.edit'), 403);
        $testimonial->delete();

        return back()->with('success', 'تم حذف الرأي.');
    }

    // ===== الفوتر / الإعدادات العامة =====
    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('website.edit'), 403);
        foreach (self::SETTINGS as $group => $keys) {
            foreach ($keys as $key) {
                Setting::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $request->input("{$group}_{$key}")]);
            }
        }

        return back()->with('success', 'تم حفظ إعدادات الموقع.');
    }

    // ===== Helpers =====
    private const SETTINGS = [
        'contact' => ['phone', 'email', 'address', 'whatsapp'],
        'social' => ['facebook', 'instagram', 'twitter', 'linkedin'],
    ];

    private function homePage(): Page
    {
        return Page::firstOrCreate(['slug' => 'home'], ['name' => 'الرئيسية']);
    }

    private function aboutPage(): Page
    {
        return Page::firstOrCreate(['slug' => 'about'], ['name' => 'من نحن']);
    }

    /** التأكد من وجود صفحات السيو وإرجاعها [slug => Page] */
    private function seoPages()
    {
        return collect(self::SEO_PAGES)->map(fn ($label, $slug) => Page::firstOrCreate(['slug' => $slug], ['name' => $label]));
    }

    private function legalPage(string $slug): Page
    {
        $names = ['terms' => 'الشروط والأحكام', 'privacy' => 'سياسة الخصوصية'];

        return Page::firstOrCreate(['slug' => $slug], ['name' => $names[$slug] ?? $slug]);
    }

    /** جلب نص صفحة قانونية [ar, en] */
    private function legalBody(string $slug): array
    {
        $sec = $this->legalPage($slug)->sections()->where('key', 'body')->first();

        return [
            'ar' => $sec ? (string) data_get($sec->getTranslation('content', 'ar', false), 'body', '') : '',
            'en' => $sec ? (string) data_get($sec->getTranslation('content', 'en', false), 'body', '') : '',
        ];
    }

    /** جلب رأس صفحة قائمة [ar=>[], en=>[]] */
    private function listingHeader(string $slug): array
    {
        $page = $this->seoPages()[$slug];
        $sec = $page->sections()->where('key', 'header')->first();

        return [
            'ar' => $sec ? ($sec->getTranslation('content', 'ar', false) ?: []) : [],
            'en' => $sec ? ($sec->getTranslation('content', 'en', false) ?: []) : [],
        ];
    }

    /** تحميل أقسام صفحة كمصفوفة [key => [ar=>[], en=>[]]] */
    private function loadSections(Page $page, array $keys): array
    {
        $page->load('sections');
        $out = [];
        foreach ($keys as $key) {
            $sec = $page->sections->firstWhere('key', $key);
            $out[$key] = [
                'ar' => $sec ? ($sec->getTranslation('content', 'ar', false) ?: []) : [],
                'en' => $sec ? ($sec->getTranslation('content', 'en', false) ?: []) : [],
            ];
        }

        return $out;
    }

    private function put(Page $page, string $key, int $order, array $shared, array $ar, array $en): void
    {
        $this->fill($this->section($page, $key, $order), $shared, $ar, $en);
    }

    private function section(Page $page, string $key, int $order): PageSection
    {
        return $page->sections()->firstOrCreate(['key' => $key], ['sort_order' => $order]);
    }

    /** نماذج الأقسام (مع صورها) لعرضها في الفورم — [key => PageSection] */
    private function sectionModels(Page $page, array $keys): array
    {
        $out = [];
        foreach ($keys as $i => $key) {
            $out[$key] = $this->section($page, $key, $i)->load('media');
        }

        return $out;
    }

    private function fill(PageSection $s, array $shared, array $ar, array $en): void
    {
        $s->setTranslation('content', 'ar', array_merge($shared, $ar));
        $s->setTranslation('content', 'en', array_merge($shared, $en));
        $s->save();
    }

    /**
     * تحقّق موحّد من كل حقول الصور: صورة صالحة · ٦ ميجابايت كحد أقصى.
     * القاعدة نفسها مطبَّقة على السيرفر حتى لو تجاوز أحدهم ضغط المتصفح.
     */
    private function validateImages(Request $request): void
    {
        $rules = PageSection::imageRules();

        $request->validate([
            'hero.images.*' => $rules,
            'featured.image' => $rules,
            'cta.image' => $rules,
            'area_items.*.image' => $rules,
        ], [
            'max' => 'حجم الصورة يتجاوز الحد المسموح (٦ ميجابايت).',
            'image' => 'الملف المرفوع ليس صورة صالحة.',
            'mimetypes' => 'الصيغ المسموحة: jpg · png · webp فقط.',
        ]);
    }

    /**
     * مزامنة مجموعة صور: حذف المحدَّد ثم إضافة المرفوع الجديد.
     * المفرد (single) يستبدل الصورة القديمة تلقائياً.
     */
    private function syncMedia(Request $request, PageSection $section, string $collection, string $field, array $removedIds, bool $single = false): void
    {
        foreach (array_filter($removedIds) as $id) {
            $section->media()->where('id', $id)->first()?->delete();
        }

        $files = $request->hasFile($field)
            ? Arr::wrap($request->file($field))
            : [];

        foreach ($files as $file) {
            if ($single) {
                $section->clearMediaCollection($collection);
            }
            $section->addMedia($file)->toMediaCollection($collection);
        }
    }

    /** حذف مجموعات صور البنود التي أُزيلت من القائمة (تنظيف القرص) */
    private function pruneCollections(PageSection $section, array $keep, string $prefix): void
    {
        $section->media()
            ->where('collection_name', 'like', $prefix.'%')
            ->whereNotIn('collection_name', $keep ?: ['__none__'])
            ->get()
            ->each->delete();
    }

    private function currentSettings(): array
    {
        $out = [];
        foreach (self::SETTINGS as $group => $keys) {
            foreach ($keys as $key) {
                $out["{$group}_{$key}"] = Setting::get($group, $key);
            }
        }

        return $out;
    }

    private function faqData(Request $request): array
    {
        return $request->validate([
            'question_ar' => ['required', 'string', 'max:500'], 'question_en' => ['nullable', 'string', 'max:500'],
            'answer_ar' => ['required', 'string'], 'answer_en' => ['nullable', 'string'],
        ], [], ['question_ar' => 'السؤال', 'answer_ar' => 'الإجابة']);
    }

    private function testimonialData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'title_ar' => ['nullable', 'string', 'max:120'], 'title_en' => ['nullable', 'string', 'max:120'],
            'content_ar' => ['required', 'string'], 'content_en' => ['nullable', 'string'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ], [], ['name' => 'الاسم', 'content_ar' => 'الرأي', 'rating' => 'التقييم']);
    }
}
