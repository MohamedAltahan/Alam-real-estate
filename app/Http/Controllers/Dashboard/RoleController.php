<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function index(): View
    {
        return view('dashboard.roles.index', [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('roles.create'), 403);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:active,inactive'],
        ], [], ['description' => 'اسم الدور']);

        $role = Role::create([
            'name' => $this->uniqueName($data['description']),
            'guard_name' => 'web',
            'description' => $data['description'],
            'status' => $data['status'],
        ]);

        $this->activity->created($role);

        return back()->with('success', 'تم إضافة الدور بنجاح.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('roles.edit'), 403);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:120'],
            'status' => ['required', 'in:active,inactive'],
        ], [], ['description' => 'اسم الدور']);

        $role->fill($data);
        $changes = $this->activity->changes($role, $role->getDirty());
        $role->save();

        $this->activity->updated($role, [], $changes);

        return back()->with('success', 'تم تحديث الدور.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('roles.delete'), 403);
        abort_if($role->name === 'super-admin', 403, 'لا يمكن حذف دور مدير النظام.');

        $this->activity->deleted($role);
        $role->delete();

        return back()->with('success', 'تم حذف الدور.');
    }

    /** توليد اسم برمجي فريد للدور (slug) من الوصف */
    private function uniqueName(string $description): string
    {
        $base = Str::slug($description) ?: 'role-'.Str::lower(Str::random(6));
        $name = $base;
        $i = 1;
        while (Role::where('name', $name)->exists()) {
            $name = $base.'-'.$i++;
        }

        return $name;
    }
}
