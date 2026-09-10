<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Area;
use App\Models\City;
use App\Models\FieldOwner;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\PublishingChannel;
use App\Models\UnitType;
use App\Models\User;
use App\Services\FieldOwnerService;
use App\Services\PropertyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function __construct(private PropertyService $properties, private FieldOwnerService $fieldOwners) {}

    public function index(Request $request): View
    {
        $filters = $request->only(PropertyService::FILTER_KEYS);

        return view('dashboard.properties.index', [
            'properties' => $this->properties->paginate($filters),
            'statuses' => PropertyStatus::where('is_active', true)->orderBy('sort_order')->get(),
            'cities' => City::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'city_id']),
            'unitTypes' => UnitType::where('is_active', true)->orderBy('sort_order')->get(),
            'channels' => $this->channelsByKind(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        abort_unless(auth()->user()->can('properties.create'), 403);

        // «حفظ كعقار» من شاشة ميداني: الفورم يُعبَّأ مسبقًا من بيانات الزيارة
        $fieldOwner = $request->filled('field_owner') ? FieldOwner::findOrFail($request->integer('field_owner')) : null;

        if ($fieldOwner) {
            abort_unless(auth()->user()->can('field_owners.view'), 403);

            if ($fieldOwner->isPropertyConverted()) {
                return redirect()
                    ->route('dashboard.field-owners.show', $fieldOwner)
                    ->with('error', 'هذه الزيارة محوَّلة بالفعل إلى عقار.');
            }
        }

        $property = new Property($fieldOwner ? $this->fieldOwners->propertyPrefill($fieldOwner) : []);

        return view('dashboard.properties.form', $this->formData($property) + [
            'nextCode' => $this->properties->generateReferenceCode(),
            'fieldOwner' => $fieldOwner,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('properties.create'), 403);

        $data = $this->validated($request);

        // عقار قادم من زيارة ميدانية: يُربط بالزيارة وتُنسخ صورها ويُنشأ المالك إن لزم
        $fieldOwner = $request->filled('field_owner_id') ? FieldOwner::findOrFail($request->integer('field_owner_id')) : null;

        $property = $fieldOwner
            ? $this->fieldOwners->createProperty($fieldOwner, $data, $request->input('amenities', []))
            : $this->properties->create($data, $request->input('amenities', []));
        $this->syncImages($request, $property);

        return redirect()
            ->route('dashboard.properties.show', $property)
            ->with('success', 'تم إضافة العقار رقم '.$property->reference_code.($fieldOwner ? ' وربطه بالزيارة الميدانية.' : '.'));
    }

    public function show(Property $property): View
    {
        $property->load(['area', 'city', 'category', 'unitType', 'status', 'owner', 'agent', 'amenities', 'media', 'reviews.createdBy', 'channels.media']);

        return view('dashboard.properties.show', ['property' => $property]);
    }

    public function edit(Property $property): View
    {
        abort_unless(auth()->user()->can('properties.edit'), 403);
        $property->load('amenities', 'media');

        return view('dashboard.properties.form', $this->formData($property));
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        abort_unless(auth()->user()->can('properties.edit'), 403);

        $data = $this->validated($request);
        $this->properties->update($property, $data, $request->input('amenities', []));
        $this->syncImages($request, $property);

        return redirect()
            ->route('dashboard.properties.show', $property)
            ->with('success', 'تم تحديث العقار.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        abort_unless(auth()->user()->can('properties.delete'), 403);
        $this->properties->delete($property);

        return redirect()->route('dashboard.properties.index')->with('success', 'تم حذف العقار.');
    }

    public function addReview(Request $request, Property $property): RedirectResponse
    {
        abort_unless(auth()->user()->can('properties.edit'), 403);
        $data = $request->validate([
            'reviewer_name' => ['required', 'string', 'max:120'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);
        $this->properties->addReview($property, $data);

        return back()->with('success', 'تمت إضافة التقييم.');
    }

    /** حفظ قنوات النشر (مواقع أو سوشال) التي نُشر عليها العقار مع روابط الإعلانات */
    public function updateChannels(Request $request, Property $property): RedirectResponse
    {
        abort_unless(auth()->user()->can('properties.edit'), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(PublishingChannel::KINDS))],
            'channels' => ['nullable', 'array'],
            'channels.*.on' => ['nullable', 'boolean'],
            'channels.*.url' => ['nullable', 'url', 'max:500'],
        ], [
            'channels.*.url.url' => 'رابط الإعلان غير صالح — يجب أن يبدأ بـ http:// أو https://',
        ]);

        $this->properties->syncChannels($property, $data['kind'], (array) ($data['channels'] ?? []));

        return back()->with('success', 'تم حفظ قنوات النشر للعقار رقم '.$property->reference_code.'.');
    }

    // ===== Helpers =====

    /** @return array<string, Collection> */
    private function channelsByKind(): array
    {
        $all = PublishingChannel::query()->active()->with('media')->orderBy('sort_order')->orderBy('id')->get();

        return [
            PublishingChannel::KIND_WEBSITE => $all->where('kind', PublishingChannel::KIND_WEBSITE)->values(),
            PublishingChannel::KIND_SOCIAL => $all->where('kind', PublishingChannel::KIND_SOCIAL)->values(),
        ];
    }

    private function formData(Property $property): array
    {
        return [
            'property' => $property,
            'cities' => City::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'city_id']),
            'categories' => PropertyCategory::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'unitTypes' => UnitType::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'statuses' => PropertyStatus::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'owners' => PropertyOwner::orderBy('name')->get(['id', 'name']),
            'agents' => User::where('is_agent', true)->orderBy('name')->get(['id', 'name']),
            'amenities' => Amenity::where('is_active', true)->orderBy('sort_order')->get(),
        ];
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'title.ar' => ['required', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255'],
            'short_description.ar' => ['nullable', 'string', 'max:500'],
            'short_description.en' => ['nullable', 'string', 'max:500'],
            'description.ar' => ['nullable', 'string'],
            'description.en' => ['nullable', 'string'],
            'specifications.ar' => ['nullable', 'string'],
            'specifications.en' => ['nullable', 'string'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'area_id' => ['required', 'exists:areas,id'],
            'category_id' => ['required', 'exists:property_categories,id'],
            'unit_type_id' => ['required', 'exists:unit_types,id'],
            'purpose' => ['required', 'in:sale,rent'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_period' => ['nullable', 'in:monthly,yearly'],
            'status_id' => ['required', 'exists:property_statuses,id'],
            'owner_id' => ['nullable', 'exists:property_owners,id'],
            'owner_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'agent_id' => ['nullable', 'exists:users,id'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'area_size' => ['nullable', 'numeric', 'min:0'],
            'is_furnished' => ['nullable', 'boolean'],
            'building_name' => ['nullable', 'string', 'max:150'],
            'block' => ['nullable', 'string', 'max:60'],
            'street' => ['nullable', 'string', 'max:120'],
            'building' => ['nullable', 'string', 'max:120'],
            'map_url' => ['nullable', 'url', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'guard_name' => ['nullable', 'string', 'max:150'],
            'guard_phone' => ['nullable', 'string', 'max:40'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'is_featured' => ['nullable', 'boolean'],
            'cover' => Property::imageRules(),
            'gallery.*' => Property::imageRules(),
            'amenities' => ['array'],
            'amenities.*' => ['exists:amenities,id'],
            'field_owner_id' => ['nullable', 'integer', 'exists:field_owners,id'],
        ], [
            'map_url.url' => 'رابط الموقع يجب أن يكون رابط خرائط جوجل صالحًا (يبدأ بـ https://).',
        ], [
            'title.ar' => 'العنوان (عربي)', 'city_id' => 'المحافظة', 'area_id' => 'المنطقة', 'category_id' => 'التصنيف',
            'unit_type_id' => 'نوع الوحدة', 'purpose' => 'الغرض', 'price' => 'السعر', 'status_id' => 'الحالة',
            'owner_commission_rate' => 'نسبة العمولة من المالك', 'building_name' => 'إسم المبنى',
            'map_url' => 'رابط موقع العقار', 'guard_name' => 'حارس العقار', 'guard_phone' => 'رقم الحارس',
        ]);

        // المنطقة تتبع المحافظة، ونوع الوحدة يتبع التصنيف
        $area = Area::find($v['area_id']);
        $category = PropertyCategory::find($v['category_id']);
        $unitType = UnitType::find($v['unit_type_id']);
        $this->crossChecks($v, $area, $category, $unitType);

        $cityId = ($v['city_id'] ?? null) ?: $area?->city_id;
        $isResidential = $category?->key !== PropertyCategory::COMMERCIAL;

        return [
            'title' => array_filter($request->input('title', []), fn ($x) => $x !== null),
            'short_description' => array_filter($request->input('short_description', []), fn ($x) => $x !== null),
            'description' => array_filter($request->input('description', []), fn ($x) => $x !== null),
            'specifications' => array_filter($request->input('specifications', []), fn ($x) => $x !== null),
            'city_id' => $cityId,
            'area_id' => $v['area_id'],
            'category_id' => $v['category_id'],
            'unit_type_id' => $v['unit_type_id'],
            'purpose' => $v['purpose'],
            'price' => $v['price'],
            'price_period' => $v['purpose'] === 'rent' ? ($v['price_period'] ?? 'monthly') : null,
            'status_id' => $v['status_id'],
            'owner_id' => $v['owner_id'] ?? null,
            'owner_commission_rate' => $v['owner_commission_rate'] ?? null,
            'agent_id' => $v['agent_id'] ?? null,
            'bedrooms' => $isResidential ? ($v['bedrooms'] ?? null) : null,
            'bathrooms' => $v['bathrooms'] ?? null,
            'area_size' => $v['area_size'] ?? null,
            'is_furnished' => $request->boolean('is_furnished'),
            'building_name' => $v['building_name'] ?? null,
            'block' => $v['block'] ?? null,
            'street' => $v['street'] ?? null,
            'building' => $v['building'] ?? null,
            'map_url' => $v['map_url'] ?? null,
            'latitude' => $v['latitude'] ?? null,
            'longitude' => $v['longitude'] ?? null,
            'guard_name' => $v['guard_name'] ?? null,
            'guard_phone' => $v['guard_phone'] ?? null,
            'video_url' => $v['video_url'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
        ];
    }

    /** تحقق مترابط: المنطقة داخل المحافظة المختارة، ونوع الوحدة من تصنيف العقار */
    private function crossChecks(array $v, ?Area $area, ?PropertyCategory $category, ?UnitType $unitType): void
    {
        $errors = [];

        if ($area && ! empty($v['city_id']) && $area->city_id && (int) $area->city_id !== (int) $v['city_id']) {
            $errors['area_id'] = 'المنطقة المختارة لا تتبع المحافظة المختارة.';
        }

        if ($category && $unitType && $category->key && $unitType->category && $unitType->category !== $category->key) {
            $errors['unit_type_id'] = 'نوع الوحدة لا يتبع التصنيف المختار.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** الغلاف والمعرض عبر media library (٦ ميجا · ارتفاع ١٠٨٠) */
    private function syncImages(Request $request, Property $property): void
    {
        foreach ((array) $request->input('cover_removed', []) as $id) {
            $property->media()->where('id', $id)->first()?->delete();
        }
        foreach ((array) $request->input('gallery_removed', []) as $id) {
            $property->media()->where('id', $id)->first()?->delete();
        }

        if ($request->hasFile('cover')) {
            $property->clearMediaCollection('cover');
            $property->addMedia($request->file('cover'))->toMediaCollection('cover');
        }

        foreach ((array) $request->file('gallery', []) as $file) {
            $property->addMedia($file)->toMediaCollection('gallery');
        }
    }
}
