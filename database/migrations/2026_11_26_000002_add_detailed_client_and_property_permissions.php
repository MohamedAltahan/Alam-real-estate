<?php

use App\Http\Controllers\Dashboard\PermissionMatrixController;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * صلاحيات تفصيلية (عرض كل العملاء · تغيير مندوب العميل · عرض نسبة العمولة).
 * كل دور يملك الصلاحية الأساسية يأخذ الجديدة تلقائياً — فلا يتغيّر شيء حتى يلغيها المدير من المصفوفة.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionMatrixController::EXTRAS as $name => $extra) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            Role::where('guard_name', 'web')->get()
                ->filter(fn (Role $role) => $role->name === 'super-admin' || $role->permissions->contains('name', $extra['base']))
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')
            ->whereIn('name', array_keys(PermissionMatrixController::EXTRAS))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
