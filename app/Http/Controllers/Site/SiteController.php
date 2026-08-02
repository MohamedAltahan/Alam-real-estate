<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\ContactRequest;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\RequestType;
use App\Models\Testimonial;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        $c = $this->pageSections('home');
        $areaIds = collect($c['areas']['items'] ?? [])->pluck('area_id')->filter();

        return view('site.home', [
            'c' => $c,
            'areas' => Area::whereIn('id', $areaIds)->get()->keyBy('id'),
            'testimonials' => Testimonial::where('is_active', true)->orderBy('sort_order')->get(),
            'searchAreas' => Area::where('is_active', true)->orderBy('sort_order')->get(),
            'searchUnitTypes' => UnitType::where('is_active', true)->get(),
            // تبويبات البحث في الهيرو: سكني/تجاري فقط — "مفروش" مستبعَد هنا وحده
            // ويبقى متاحاً في باقي النظام (نماذج العقارات وصفحة العروض).
            'searchCategories' => PropertyCategory::where('is_active', true)->orderBy('sort_order')->get()
                ->reject(fn ($cat) => $cat->getTranslation('name', 'ar') === 'مفروش')
                ->values(),
            'searchReferences' => Property::orderBy('reference_code')->pluck('reference_code')->filter()->values(),
            'videoProperties' => $this->homeVideos($c['videos'] ?? []),
        ]);
    }

    /**
     * فيديوهات الصفحة الرئيسية: ما اختاره المحرّر بترتيبه، وإن لم يختر شيئاً
     * (أو فقدت كل اختياراته فيديوهاتها) نعرض أحدث ٨ عقارات لها فيديو.
     */
    private function homeVideos(array $section)
    {
        $ids = collect($section['items'] ?? [])->map(fn ($v) => (int) $v)->filter();

        if ($ids->isNotEmpty()) {
            $picked = Property::withVideo()->whereIn('id', $ids)->get();

            // نحافظ على ترتيب المحرّر ونتخطّى ما لم يعد صالحاً
            $ordered = $ids->map(fn ($id) => $picked->firstWhere('id', $id))->filter()->values();

            if ($ordered->isNotEmpty()) {
                return $ordered;
            }
        }

        return Property::withVideo()->latest()->take(8)->get();
    }

    /** قائمة العقارات مع الفلاتر */
    public function properties(Request $request): View
    {
        $properties = Property::query()
            ->with(['area', 'agent', 'status', 'unitType', 'media'])
            ->when($request->category, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->unit_type, fn ($q, $v) => $q->where('unit_type_id', $v))
            ->when($request->area, fn ($q, $v) => $q->where('area_id', $v))
            ->when($request->bedrooms, fn ($q, $v) => $q->where('bedrooms', '>=', (int) $v))
            ->when($request->purpose, fn ($q, $v) => $q->where('purpose', $v))
            ->when($request->reference, fn ($q, $v) => $q->where('reference_code', $v))
            ->when($request->price, function ($q, $v) {
                if (str_ends_with($v, '+')) {
                    return $q->where('price', '>=', (float) rtrim($v, '+'));
                }
                [$min, $max] = array_pad(explode('-', $v), 2, null);

                return $q->where('price', '>=', (float) $min)->when($max !== null, fn ($qq) => $qq->where('price', '<=', (float) $max));
            })
            ->when($request->sort === 'price_asc', fn ($q) => $q->orderBy('price'))
            ->when($request->sort === 'price_desc', fn ($q) => $q->orderByDesc('price'))
            ->when(! in_array($request->sort, ['price_asc', 'price_desc']), fn ($q) => $q->latest())
            ->paginate(9)
            ->withQueryString();

        return view('site.properties', [
            'properties' => $properties,
            'header' => $this->pageHeader('properties'),
            'categories' => PropertyCategory::where('is_active', true)->get(),
            'unitTypes' => UnitType::where('is_active', true)->get(),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->get(),
            'filters' => $request->only('category', 'unit_type', 'area', 'bedrooms', 'purpose', 'reference', 'price'),
        ]);
    }

    /** تفاصيل عقار */
    public function property(Property $property): View
    {
        $property->load(['area', 'agent', 'owner', 'status', 'unitType', 'category', 'amenities', 'media', 'reviews.createdBy']);

        $similar = Property::with(['area', 'agent', 'status', 'media'])
            ->where('id', '!=', $property->id)
            ->where(fn ($q) => $q->where('area_id', $property->area_id)->orWhere('unit_type_id', $property->unit_type_id))
            ->latest()->take(8)->get();

        // لا يوجد شبيه بنفس المنطقة/النوع ⇒ اعرض أحدث العقارات الأخرى بدل قسم فارغ
        if ($similar->isEmpty()) {
            $similar = Property::with(['area', 'agent', 'status', 'media'])
                ->where('id', '!=', $property->id)
                ->latest()->take(8)->get();
        }

        return view('site.property', compact('property', 'similar'));
    }

    /** ملف الوكيل + عقاراته + تقييماته */
    public function agent(User $agent): View
    {
        abort_unless($agent->is_agent && $agent->status === 'active', 404);

        $properties = $agent->properties()
            ->with(['area', 'agent', 'media'])
            ->latest()->paginate(6);

        $reviews = $agent->reviews()->where('is_published', true)->latest()->get();

        return view('site.agent', [
            'agent' => $agent,
            'properties' => $properties,
            'reviews' => $reviews,
            'listingsCount' => $agent->properties()->count(),
            'clientsCount' => $agent->clients()->count(),
        ]);
    }

    /** من نحن */
    public function about(): View
    {
        return view('site.about', [
            'c' => $this->pageSections('about'),
            'agents' => User::where('is_agent', true)->where('status', 'active')
                ->withCount(['properties', 'clients'])
                ->orderBy('name')->get(),
            'testimonials' => Testimonial::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    /** تواصل معنا (عرض النموذج) */
    public function contact(): View
    {
        return view('site.contact', [
            'types' => RequestType::whereIn('key', ['general', 'property_inquiry'])->get(),
        ]);
    }

    /** حفظ رسالة تواصل */
    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'request_type_id' => ['nullable', 'exists:request_types,id'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        ContactRequest::create($data);

        return back()->with('sent', true);
    }

    /** أعرض عقارك (عرض النموذج) */
    public function listProperty(): View
    {
        return view('site.list-property', [
            'unitTypes' => UnitType::where('is_active', true)->get(),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    /** حفظ طلب عرض عقار (ضمن contact_requests بنوع list_property) */
    public function storeListProperty(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'unit_type_id' => ['nullable', 'exists:unit_types,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'price' => ['nullable', 'string', 'max:60'],
            'details' => ['required', 'string', 'max:2000'],
        ]);

        $unit = ! empty($v['unit_type_id']) ? UnitType::find($v['unit_type_id']) : null;
        $area = ! empty($v['area_id']) ? Area::find($v['area_id']) : null;
        $type = RequestType::where('key', 'list_property')->first();

        $summary = collect([
            $unit?->name ? 'النوع: '.$unit->name : null,
            $area?->name ? 'المنطقة: '.$area->name : null,
            ! empty($v['price']) ? 'السعر المطلوب: '.$v['price'] : null,
        ])->filter()->implode(' — ');

        ContactRequest::create([
            'name' => $v['name'],
            'phone' => $v['phone'],
            'email' => $v['email'] ?? null,
            'request_type_id' => $type?->id,
            'subject' => trim('طلب عرض عقار'.($summary ? ' — '.$summary : '')),
            'message' => $v['details'],
        ]);

        return back()->with('sent', true);
    }

    /** الأسئلة الشائعة */
    public function faq(): View
    {
        return view('site.faq', [
            'faqs' => Faq::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    /** الشروط والأحكام */
    public function terms(): View
    {
        return $this->legal('terms');
    }

    /** سياسة الخصوصية */
    public function privacy(): View
    {
        return $this->legal('privacy');
    }

    /** عرض صفحة قانونية (نص واحد من الـ CMS) */
    private function legal(string $slug): View
    {
        $page = Page::where('slug', $slug)->with('sections')->first();
        $sec = $page?->sections->firstWhere('key', 'body');

        return view('site.legal', [
            'slug' => $slug,
            'body' => $sec ? (string) data_get($sec->content, 'body', '') : '',
            'updatedAt' => $sec?->updated_at ?? $page?->updated_at,
        ]);
    }

    /** تبديل لغة الزائر */
    public function switchLocale(string $locale): RedirectResponse
    {
        if (in_array($locale, ['ar', 'en'])) {
            session(['locale' => $locale]);
        }

        return back();
    }

    /** أقسام صفحة كـ [key => content(باللغة الحالية)] */
    private function pageSections(string $slug): array
    {
        $page = Page::where('slug', $slug)->with('sections')->first();

        if (! $page) {
            return [];
        }

        // صور الأقسام مخزَّنة في media library — نحقن روابطها الجاهزة في المحتوى
        return $page->sections->mapWithKeys(function ($s) {
            $content = $s->content ?? [];

            $gallery = $s->getMedia('images')
                ->map(fn ($m) => $m->hasGeneratedConversion('web') ? $m->getUrl('web') : $m->getUrl())
                ->all();

            if ($gallery) {
                $content['images'] = $gallery;
            }

            if ($single = $s->imageUrl('image')) {
                $content['image'] = $single;
            }

            // كل بند له مجموعة صور مستقلة (المناطق مثلاً)
            if (! empty($content['items']) && is_array($content['items'])) {
                $content['items'] = array_map(function ($item) use ($s) {
                    if (! empty($item['collection'])) {
                        $item['image'] = $s->imageUrl($item['collection']);
                    }

                    return $item;
                }, $content['items']);
            }

            return [$s->key => $content];
        })->all();
    }

    /** رأس صفحة قائمة (title/description باللغة الحالية) */
    private function pageHeader(string $slug): array
    {
        $page = Page::where('slug', $slug)->first();
        $sec = $page?->sections()->where('key', 'header')->first();

        return $sec ? ($sec->content ?? []) : [];
    }
}
