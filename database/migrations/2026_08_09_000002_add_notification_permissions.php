<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect(['notifications.view', 'notifications.edit'])
            ->map(fn (string $name) => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        if ($superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first()) {
            $superAdmin->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')
            ->whereIn('name', ['notifications.view', 'notifications.edit'])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
