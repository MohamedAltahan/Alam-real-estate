<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** صلاحيات شاشة المهام للأدوار الموجودة (قاعدة بيانات جديدة: RolePermissionSeeder يتولاها) */
return new class extends Migration
{
    private const GRANTS = [
        'super-admin' => ['view', 'create', 'edit', 'delete'],
        'property-manager' => ['view', 'create', 'edit', 'delete'],
        'sales-agent' => ['view', 'create', 'edit'],
        'marketing-staff' => ['view', 'create', 'edit'],
        'customer-service' => ['view', 'create', 'edit'],
        'accountant' => ['view'],
    ];

    public function up(): void
    {
        $permissions = [];

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $permissions[$action] = Permission::firstOrCreate(['name' => "tasks.{$action}", 'guard_name' => 'web']);
        }

        foreach (self::GRANTS as $roleName => $actions) {
            if ($role = Role::where('name', $roleName)->where('guard_name', 'web')->first()) {
                $role->givePermissionTo(array_map(fn (string $a) => $permissions[$a], $actions));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')->where('name', 'like', 'tasks.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
