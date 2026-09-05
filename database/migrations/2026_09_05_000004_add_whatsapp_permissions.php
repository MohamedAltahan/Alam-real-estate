<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** صلاحيات شاشة واتساب (الربط والقوالب) للأدوار الموجودة — الإرسال نفسه يتبع clients.edit */
return new class extends Migration
{
    private const GRANTS = [
        'super-admin' => ['view', 'edit'],
        'property-manager' => ['view', 'edit'],
    ];

    public function up(): void
    {
        $permissions = [];

        foreach (['view', 'edit'] as $action) {
            $permissions[$action] = Permission::firstOrCreate(['name' => "whatsapp.{$action}", 'guard_name' => 'web']);
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
        Permission::where('guard_name', 'web')->where('name', 'like', 'whatsapp.%')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
