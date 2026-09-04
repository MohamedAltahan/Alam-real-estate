<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropertyOwnerFormRequest;
use App\Models\Area;
use App\Models\City;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Services\PropertyOwnerService;
use App\Support\PhoneCountries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyOwnerController extends Controller
{
    public function __construct(private PropertyOwnerService $owners) {}

    public function index(Request $request): View
    {
        $filters = $request->only(PropertyOwnerService::FILTER_KEYS);

        return view('dashboard.owners.index', [
            'owners' => $this->owners->paginate($filters),
            'filters' => $filters,
        ] + $this->formData());
    }

    public function store(PropertyOwnerFormRequest $request): RedirectResponse
    {
        $this->owners->create($request->validated(), $request);

        return back()->with('success', 'تم إضافة المالك بنجاح.');
    }

    public function update(PropertyOwnerFormRequest $request, PropertyOwner $owner): RedirectResponse
    {
        $this->owners->update($owner, $request->validated(), $request);

        return back()->with('success', 'تم تحديث بيانات المالك.');
    }

    public function destroy(Request $request, PropertyOwner $owner): RedirectResponse
    {
        abort_unless($request->user()->can('property_owners.delete'), 403);
        $owner->delete();

        return redirect()->route('dashboard.owners.index')->with('success', 'تم حذف المالك.');
    }

    public function show(PropertyOwner $owner): View
    {
        $owner->load([
            'area.city', 'contacts', 'media',
            'properties.area.city', 'properties.status', 'properties.unitType',
            'properties.agent', 'properties.media',
        ]);

        return view('dashboard.owners.show', ['owner' => $owner] + $this->formData());
    }

    /** قوائم الفورم والفلاتر المشتركة بين القائمة وصفحة المالك */
    private function formData(): array
    {
        return [
            'cities' => City::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'city_id']),
            'agents' => User::where('is_agent', true)->orderBy('name')->get(['id', 'name']),
            'countries' => PhoneCountries::all(),
        ];
    }
}
