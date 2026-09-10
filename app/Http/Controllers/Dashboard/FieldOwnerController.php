<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldOwnerFormRequest;
use App\Models\Area;
use App\Models\City;
use App\Models\FieldOwner;
use App\Services\FieldOwnerService;
use App\Support\FieldOwnerFields;
use App\Support\PhoneCountries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** شاشة «ميداني»: زيارات المندوبين لملاك عقارات جدد */
class FieldOwnerController extends Controller
{
    public function __construct(private FieldOwnerService $fieldOwners) {}

    public function index(Request $request): View
    {
        $filters = $request->only(FieldOwnerService::FILTER_KEYS);

        return view('dashboard.field-owners.index', [
            'records' => $this->fieldOwners->paginate($filters),
            'stageCounts' => $this->fieldOwners->stageCounts($filters),
            'reps' => $this->fieldOwners->reps(),
            'filters' => $filters,
        ] + $this->lookups());
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('field_owners.create'), 403);

        $record = new FieldOwner([
            'contact_method' => FieldOwnerFields::DEFAULT_METHOD,
            'stage' => FieldOwnerFields::DEFAULT_STAGE,
        ]);

        return view('dashboard.field-owners.form', ['record' => $record] + $this->lookups());
    }

    public function store(FieldOwnerFormRequest $request): RedirectResponse
    {
        $record = $this->fieldOwners->create($request->validated(), $request, (int) $request->user()->id);

        return redirect()
            ->route('dashboard.field-owners.show', $record)
            ->with('success', 'تمت إضافة الزيارة الميدانية.');
    }

    public function show(FieldOwner $fieldOwner): View
    {
        $fieldOwner->load(['contacts', 'city', 'area', 'creator', 'media', 'convertedOwner', 'convertedProperty']);

        return view('dashboard.field-owners.show', [
            'record' => $fieldOwner,
            'duplicate' => $fieldOwner->isOwnerConverted() ? null : $this->fieldOwners->findDuplicateOwner($fieldOwner),
        ]);
    }

    public function edit(FieldOwner $fieldOwner): View
    {
        abort_unless(auth()->user()->can('field_owners.edit'), 403);
        $fieldOwner->load(['contacts', 'media']);

        return view('dashboard.field-owners.form', ['record' => $fieldOwner] + $this->lookups());
    }

    public function update(FieldOwnerFormRequest $request, FieldOwner $fieldOwner): RedirectResponse
    {
        $this->fieldOwners->update($fieldOwner, $request->validated(), $request);

        return redirect()
            ->route('dashboard.field-owners.show', $fieldOwner)
            ->with('success', 'تم تحديث بيانات الزيارة.');
    }

    public function destroy(Request $request, FieldOwner $fieldOwner): RedirectResponse
    {
        abort_unless($request->user()->can('field_owners.delete'), 403);
        $fieldOwner->delete();

        return redirect()->route('dashboard.field-owners.index')->with('success', 'تم حذف الزيارة.');
    }

    /** حفظ الزيارة كمالك حقيقي في شاشة الملاك (أو ربطها بمالك موجود) */
    public function convertOwner(Request $request, FieldOwner $fieldOwner): RedirectResponse
    {
        abort_unless($request->user()->can('property_owners.create'), 403);

        if ($fieldOwner->isOwnerConverted()) {
            return back()->with('success', 'هذه الزيارة محوَّلة بالفعل إلى مالك.');
        }

        $data = $request->validate([
            'existing_owner_id' => ['nullable', 'integer', 'exists:property_owners,id'],
        ]);

        $existingId = ! empty($data['existing_owner_id']) ? (int) $data['existing_owner_id'] : null;
        $owner = $this->fieldOwners->convertToOwner($fieldOwner, $existingId);

        return redirect()
            ->route('dashboard.owners.show', $owner)
            ->with('success', $existingId ? 'تم ربط الزيارة بالمالك الموجود.' : 'تم حفظ المالك في شاشة الملاك.');
    }

    /** قوائم الفورم والفلاتر */
    private function lookups(): array
    {
        return [
            'cities' => City::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'city_id']),
            'countries' => PhoneCountries::all(),
        ];
    }
}
