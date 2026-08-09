<?php

namespace Database\Seeders;

use App\Http\Controllers\Dashboard\PermissionMatrixController;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // الوحدات (اللي ليها شاشات فقط — الوحدات الإضافية متجاهَلة)
        $modules = array_keys(PermissionMatrixController::MODULES);

        $all = [];
        foreach ($modules as $m) {
            foreach (PermissionMatrixController::actionsFor($m) as $a) {
                $name = "{$m}.{$a}";
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $all[] = $name;
            }
        }

        // الأدوار: name إنجليزي (للكود) + description عربي (للعرض)
        $roles = [
            'super-admin' => [
                'description' => 'مدير النظام',
                'perms' => $all, // كل الصلاحيات
            ],
            'property-manager' => [
                'description' => 'مدير العقارات',
                'perms' => array_merge(
                    $this->forModules(['properties', 'property_owners']),
                    $this->view(['clients', 'contact_requests', 'dashboard', 'notifications'])
                ),
            ],
            'sales-agent' => [
                'description' => 'مسؤول عقار',
                'perms' => array_merge(
                    $this->forModules(['clients']),
                    $this->view(['properties', 'contact_requests', 'dashboard', 'notifications'])
                ),
            ],
            'marketing-staff' => [
                'description' => 'موظف تسويق',
                'perms' => array_merge(
                    $this->forModules(['marketing_sources']),
                    $this->view(['clients', 'contact_requests', 'dashboard', 'notifications'])
                ),
            ],
            'customer-service' => [
                'description' => 'خدمة العملاء',
                'perms' => array_merge(
                    $this->forModules(['contact_requests']),
                    $this->view(['clients', 'properties', 'dashboard', 'notifications']),
                    ['notifications.edit']
                ),
            ],
            'accountant' => [
                'description' => 'محاسب',
                'perms' => $this->view(['properties', 'clients', 'dashboard', 'notifications']),
            ],
        ];

        foreach ($roles as $name => $cfg) {
            $role = Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $cfg['description'], 'status' => 'active']
            );
            $role->description = $cfg['description'];
            $role->status = 'active';
            $role->save();
            $role->syncPermissions(array_values($cfg['perms']));
        }
    }

    /** كل الأفعال لعدة وحدات */
    private function forModules(array $modules): array
    {
        $out = [];
        foreach ($modules as $m) {
            foreach (PermissionMatrixController::actionsFor($m) as $a) {
                $out[] = "{$m}.{$a}";
            }
        }

        return $out;
    }

    /** صلاحية العرض فقط لعدة وحدات */
    private function view(array $modules): array
    {
        return array_map(fn ($m) => "{$m}.view", $modules);
    }
}
