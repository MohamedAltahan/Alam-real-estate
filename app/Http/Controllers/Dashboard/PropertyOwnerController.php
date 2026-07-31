<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\PropertyOwner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyOwnerController extends Controller
{
    public function index(Request $request): View
    {
        $owners = PropertyOwner::query()
            ->with('area')
            ->withCount('properties')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.owners.index', [
            'owners' => $owners,
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->get(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('property_owners.create'), 403);
        PropertyOwner::create($this->validated($request));

        return back()->with('success', 'تم إضافة المالك بنجاح.');
    }

    public function update(Request $request, PropertyOwner $owner): RedirectResponse
    {
        abort_unless($request->user()->can('property_owners.edit'), 403);
        $owner->update($this->validated($request));

        return back()->with('success', 'تم تحديث بيانات المالك.');
    }

    public function destroy(Request $request, PropertyOwner $owner): RedirectResponse
    {
        abort_unless($request->user()->can('property_owners.delete'), 403);
        $owner->delete();

        return back()->with('success', 'تم حذف المالك.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'nationality' => ['nullable', 'string', 'max:120'],
            'registered_address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
        ], [], [
            'name' => 'الاسم', 'phone' => 'رقم الهاتف', 'status' => 'الحالة',
        ]);
    }
}
