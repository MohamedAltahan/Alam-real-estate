<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionMatrixController extends Controller
{
    /** الوحدات (اللي لها شاشات فقط) وأفعالها */
    public const MODULES = [
        'dashboard' => 'لوحة التحكم',
        'clients' => 'إدارة العملاء',
        'property_owners' => 'إدارة الملّاك',
        'contact_requests' => 'طلبات التواصل',
        'marketing_sources' => 'التسويق',
        'website' => 'الموقع الإلكتروني',
        'properties' => 'العقارات',
        'roles' => 'الأدوار',
        'permissions' => 'الصلاحيات',
        'supervisors' => 'المشرفين',
    ];

    public const ACTIONS = [
        'view' => 'عرض',
        'create' => 'إضافة',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'export' => 'تصدير',
    ];

    public function index(Request $request): View
    {
        $roles = Role::orderBy('id')->get();
        $current = $roles->firstWhere('id', $request->integer('role')) ?? $roles->first();

        return view('dashboard.permissions.index', [
            'roles' => $roles,
            'current' => $current,
            'assigned' => $current ? $current->permissions->pluck('name')->flip() : collect(),
            'modules' => self::MODULES,
            'actions' => self::ACTIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('permissions.edit'), 403);

        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $role = Role::findOrFail($data['role_id']);

        // نقبل فقط الصلاحيات ضمن الوحدات والأفعال المعرّفة
        $valid = [];
        foreach (array_keys(self::MODULES) as $m) {
            foreach (array_keys(self::ACTIONS) as $a) {
                $valid[] = "{$m}.{$a}";
            }
        }
        $selected = array_values(array_intersect($data['permissions'] ?? [], $valid));

        // نتأكد أن الصلاحيات موجودة ثم نزامنها
        foreach ($selected as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role->syncPermissions($selected);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('dashboard.permissions.index', ['role' => $role->id])
            ->with('success', 'تم حفظ صلاحيات الدور «'.($role->description ?: $role->name).'».');
    }
}
