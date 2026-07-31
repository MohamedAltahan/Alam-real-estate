<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Area;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use App\Services\PropertyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function __construct(private PropertyService $properties) {}

    public function index(Request $request): View
    {
        return view('dashboard.properties.index', [
            'properties' => $this->properties->paginate($request->only('search', 'status_id', 'area_id', 'purpose')),
            'statuses' => PropertyStatus::where('is_active', true)->get(),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->get(),
            'filters' => $request->only('search', 'status_id', 'area_id', 'purpose'),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('properties.create'), 403);

        return view('dashboard.properties.form', $this->formData(new Property) + [
            'nextCode' => $this->properties->generateReferenceCode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('properties.create'), 403);

        $data = $this->validated($request);
        $data['cover_image'] = $this->uploadCover($request);
        $property = $this->properties->create($data, $request->input('amenities', []));
        $this->uploadGallery($request, $property);

        return redirect()
            ->route('dashboard.properties.show', $property)
            ->with('success', 'تم إضافة العقار '.$property->reference_code.'.');
    }

    public function show(Property $property): View
    {
        $property->load(['area', 'category', 'unitType', 'status', 'owner', 'agent', 'amenities', 'images', 'reviews.createdBy']);

        return view('dashboard.properties.show', ['property' => $property]);
    }

    public function edit(Property $property): View
    {
        abort_unless(auth()->user()->can('properties.edit'), 403);
        $property->load('amenities', 'images');

        return view('dashboard.properties.form', $this->formData($property));
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        abort_unless(auth()->user()->can('properties.edit'), 403);

        $data = $this->validated($request);
        if ($cover = $this->uploadCover($request)) {
            $data['cover_image'] = $cover;
        }
        $this->properties->update($property, $data, $request->input('amenities', []));
        $this->uploadGallery($request, $property);

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

    // ===== Helpers =====

    private function formData(Property $property): array
    {
        return [
            'property' => $property,
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->get(),
            'categories' => PropertyCategory::where('is_active', true)->get(),
            'unitTypes' => UnitType::where('is_active', true)->get(),
            'statuses' => PropertyStatus::where('is_active', true)->get(),
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
            'area_id' => ['required', 'exists:areas,id'],
            'category_id' => ['required', 'exists:property_categories,id'],
            'unit_type_id' => ['required', 'exists:unit_types,id'],
            'purpose' => ['required', 'in:sale,rent'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_period' => ['nullable', 'in:monthly,yearly'],
            'status_id' => ['required', 'exists:property_statuses,id'],
            'owner_id' => ['nullable', 'exists:property_owners,id'],
            'agent_id' => ['nullable', 'exists:users,id'],
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'area_size' => ['nullable', 'numeric', 'min:0'],
            'block' => ['nullable', 'string', 'max:60'],
            'street' => ['nullable', 'string', 'max:120'],
            'building' => ['nullable', 'string', 'max:120'],
            'video_url' => ['nullable', 'url', 'max:255'],
            'is_featured' => ['nullable', 'boolean'],
            'cover_image' => ['nullable', 'image', 'max:4096'],
            'gallery.*' => ['nullable', 'image', 'max:4096'],
            'amenities' => ['array'],
            'amenities.*' => ['exists:amenities,id'],
        ], [], [
            'title.ar' => 'العنوان (عربي)', 'area_id' => 'المنطقة', 'category_id' => 'التصنيف',
            'unit_type_id' => 'نوع الوحدة', 'purpose' => 'الغرض', 'price' => 'السعر', 'status_id' => 'الحالة',
        ]);

        return [
            'title' => array_filter($request->input('title', []), fn ($x) => $x !== null),
            'short_description' => array_filter($request->input('short_description', []), fn ($x) => $x !== null),
            'description' => array_filter($request->input('description', []), fn ($x) => $x !== null),
            'specifications' => array_filter($request->input('specifications', []), fn ($x) => $x !== null),
            'area_id' => $v['area_id'],
            'category_id' => $v['category_id'],
            'unit_type_id' => $v['unit_type_id'],
            'purpose' => $v['purpose'],
            'price' => $v['price'],
            'price_period' => $v['purpose'] === 'rent' ? ($v['price_period'] ?? 'monthly') : null,
            'status_id' => $v['status_id'],
            'owner_id' => $v['owner_id'] ?? null,
            'agent_id' => $v['agent_id'] ?? null,
            'bedrooms' => $v['bedrooms'] ?? null,
            'bathrooms' => $v['bathrooms'] ?? null,
            'area_size' => $v['area_size'] ?? null,
            'block' => $v['block'] ?? null,
            'street' => $v['street'] ?? null,
            'building' => $v['building'] ?? null,
            'video_url' => $v['video_url'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
        ];
    }

    private function uploadCover(Request $request): ?string
    {
        return $request->hasFile('cover_image')
            ? $request->file('cover_image')->store('properties', 'public')
            : null;
    }

    private function uploadGallery(Request $request, Property $property): void
    {
        foreach ($request->file('gallery', []) as $file) {
            $property->images()->create([
                'path' => $file->store('properties/gallery', 'public'),
                'sort_order' => $property->images()->count(),
            ]);
        }
    }
}
