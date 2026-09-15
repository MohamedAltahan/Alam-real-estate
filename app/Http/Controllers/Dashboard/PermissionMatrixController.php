<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionMatrixController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    /** الوحدات (اللي لها شاشات فقط) وأفعالها */
    public const MODULES = [
        'dashboard' => 'لوحة التحكم',
        'notifications' => 'الإشعارات',
        'clients' => 'إدارة العملاء',
        'tasks' => 'المهام',
        'property_owners' => 'إدارة الملّاك',
        'field_owners' => 'الميداني',
        'contact_requests' => 'طلبات التواصل',
        'marketing_sources' => 'التسويق',
        'website' => 'الموقع الإلكتروني',
        'properties' => 'العقارات',
        'areas' => 'إدارة المناطق',
        'unit_types' => 'أنواع العقارات',
        'publishing_channels' => 'المواقع والسوشال ميديا',
        'whatsapp' => 'واتساب',
        'roles' => 'الأدوار',
        'permissions' => 'الصلاحيات',
        'supervisors' => 'المشرفين',
        'reports' => 'التقارير',
        'activity' => 'سجل النشاط',
    ];

    public const ACTIONS = [
        'view' => 'عرض',
        'create' => 'إضافة',
        'edit' => 'تعديل',
        'delete' => 'حذف',
        'export' => 'تصدير',
    ];

    /** الإجراءات الفعلية للوحدات التي لا تستخدم كل أعمدة المصفوفة. */
    public const MODULE_ACTIONS = [
        'dashboard' => ['view'],
        'notifications' => ['view', 'edit'],
        'clients' => ['view', 'create', 'edit', 'delete'],
        'tasks' => ['view', 'create', 'edit', 'delete'],
        'property_owners' => ['view', 'create', 'edit', 'delete'],
        'field_owners' => ['view', 'create', 'edit', 'delete'],
        'contact_requests' => ['view', 'edit', 'delete'],
        'marketing_sources' => ['view', 'create', 'edit', 'delete'],
        'website' => ['view', 'edit'],
        'properties' => ['view', 'create', 'edit', 'delete'],
        'areas' => ['view', 'create', 'edit', 'delete'],
        'unit_types' => ['view', 'create', 'edit', 'delete'],
        'publishing_channels' => ['view', 'create', 'edit', 'delete'],
        'whatsapp' => ['view', 'edit'],
        'roles' => ['view', 'create', 'edit', 'delete'],
        'permissions' => ['view', 'edit'],
        'supervisors' => ['view', 'create', 'edit', 'delete'],
        'reports' => ['view'],
        'activity' => ['view'],
    ];

    /** «العقارات: تعديل · العملاء: حذف» — لسجل النشاط */
    public static function describePermissions(array $names): string
    {
        return collect($names)->map(function (string $name) {
            [$module, $action] = array_pad(explode('.', $name, 2), 2, '');

            return (self::MODULES[$module] ?? $module).': '.(self::ACTIONS[$action] ?? $action);
        })->implode(' · ');
    }

    /** @return array<int, string> */
    public static function actionsFor(string $module): array
    {
        return self::MODULE_ACTIONS[$module] ?? array_keys(self::ACTIONS);
    }

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
            foreach (self::actionsFor($m) as $a) {
                $valid[] = "{$m}.{$a}";
            }
        }
        $selected = array_values(array_intersect($data['permissions'] ?? [], $valid));

        // نتأكد أن الصلاحيات موجودة ثم نزامنها
        foreach ($selected as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $previous = $role->permissions->pluck('name')->all();
        $role->syncPermissions($selected);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // سجل النشاط: ما أُضيف وما أُزيل بأسمائه العربية «الوحدة: الفعل»
        $added = array_values(array_diff($selected, $previous));
        $removed = array_values(array_diff($previous, $selected));

        $this->activity->updated($role, [], array_filter([
            'permissions_added' => $added ? ['old' => null, 'new' => self::describePermissions($added)] : null,
            'permissions_removed' => $removed ? ['old' => self::describePermissions($removed), 'new' => null] : null,
        ]));

        return redirect()
            ->route('dashboard.permissions.index', ['role' => $role->id])
            ->with('success', 'تم حفظ صلاحيات الدور «'.($role->description ?: $role->name).'».');
    }
}
