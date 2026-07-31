<?php

namespace Database\Seeders;

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
        $modules = [
            'dashboard', 'clients', 'property_owners', 'contact_requests',
            'marketing_sources', 'website', 'properties', 'roles',
            'permissions', 'supervisors',
        ];
        $actions = ['view', 'create', 'edit', 'delete', 'export'];

        $all = [];
        foreach ($modules as $m) {
            foreach ($actions as $a) {
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
                    $this->forModules(['properties', 'property_owners'], $actions),
                    $this->view(['clients', 'contact_requests', 'dashboard'])
                ),
            ],
            'sales-agent' => [
                'description' => 'وكيل مبيعات',
                'perms' => array_merge(
                    $this->forModules(['clients'], $actions),
                    $this->view(['properties', 'contact_requests', 'dashboard'])
                ),
            ],
            'marketing-staff' => [
                'description' => 'موظف تسويق',
                'perms' => array_merge(
                    $this->forModules(['marketing_sources'], $actions),
                    $this->view(['clients', 'contact_requests', 'dashboard'])
                ),
            ],
            'customer-service' => [
                'description' => 'خدمة العملاء',
                'perms' => array_merge(
                    $this->forModules(['contact_requests'], $actions),
                    $this->view(['clients', 'properties', 'dashboard'])
                ),
            ],
            'accountant' => [
                'description' => 'محاسب',
                'perms' => $this->view(['properties', 'clients', 'dashboard']),
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
    private function forModules(array $modules, array $actions): array
    {
        $out = [];
        foreach ($modules as $m) {
            foreach ($actions as $a) {
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
