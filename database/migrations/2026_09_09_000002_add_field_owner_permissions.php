<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** صلاحيات شاشة «ميداني» للأدوار الموجودة (قاعدة بيانات جديدة: RolePermissionSeeder يتولاها) */
return new class extends Migration
{
    private const GRANTS = [
        'super-admin' => ['view', 'create', 'edit', 'delete'],
        'property-manager' => ['view', 'create', 'edit', 'delete'],
        'sales-agent' => ['view', 'create', 'edit'],
    ];

    public function up(): void
    {
        $permissions = [];

        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $permissions[$action] = Permission::firstOrCreate(['name' => "field_owners.{$action}", 'guard_name' => 'web']);
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
        Permission::where('guard_name', 'web')->where('name', 'like', 'field_owners.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
