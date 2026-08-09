<?php

namespace Tests\Feature\Dashboard;

use App\Http\Controllers\Dashboard\PermissionMatrixController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_form_submits_as_put_and_saves_selected_permissions(): void
    {
        $editor = User::factory()->create();
        $viewPermission = Permission::create(['name' => 'permissions.view', 'guard_name' => 'web']);
        $editPermission = Permission::create(['name' => 'permissions.edit', 'guard_name' => 'web']);
        $clientPermission = Permission::create(['name' => 'clients.view', 'guard_name' => 'web']);
        $obsoleteExportPermission = Permission::create(['name' => 'clients.export', 'guard_name' => 'web']);
        $notificationView = Permission::where('name', 'notifications.view')->firstOrFail();
        $notificationEdit = Permission::where('name', 'notifications.edit')->firstOrFail();
        $editor->givePermissionTo([$viewPermission, $editPermission]);

        $role = Role::create([
            'name' => 'test-role',
            'guard_name' => 'web',
            'description' => 'دور الاختبار',
            'status' => 'active',
        ]);

        $response = $this->actingAs($editor)
            ->get(route('dashboard.permissions.index', ['role' => $role->id]))
            ->assertOk()
            ->assertSee('name="_method" value="PUT"', false);

        foreach (PermissionMatrixController::MODULES as $module => $label) {
            foreach (PermissionMatrixController::ACTIONS as $action => $actionLabel) {
                $needle = 'value="'.$module.'.'.$action.'"';

                if (in_array($action, PermissionMatrixController::actionsFor($module), true)) {
                    $response->assertSee($needle, false);
                } else {
                    $response->assertDontSee($needle, false);
                }
            }
        }

        $this->actingAs($editor)
            ->put(route('dashboard.permissions.update'), [
                'role_id' => $role->id,
                'permissions' => [
                    $clientPermission->name,
                    $notificationView->name,
                    $notificationEdit->name,
                    $obsoleteExportPermission->name,
                ],
            ])
            ->assertRedirect(route('dashboard.permissions.index', ['role' => $role->id]));

        $this->assertTrue($role->refresh()->hasPermissionTo('clients.view'));
        $this->assertTrue($role->hasPermissionTo('notifications.view'));
        $this->assertTrue($role->hasPermissionTo('notifications.edit'));
        $this->assertFalse($role->hasPermissionTo('clients.export'));
    }
}
