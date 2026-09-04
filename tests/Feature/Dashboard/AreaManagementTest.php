<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AreaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_update_and_delete_an_unused_area(): void
    {
        $user = $this->userWithAreaPermissions(['view', 'create', 'edit', 'delete']);

        $this->actingAs($user)
            ->get(route('dashboard.areas.index'))
            ->assertOk()
            ->assertSee('إدارة المناطق')
            ->assertSee('إضافة منطقة');

        $this->actingAs($user)
            ->post(route('dashboard.areas.store'), [
                'name' => ['ar' => 'القرين', 'en' => 'Al-Qurain'],
                'sort_order' => 12,
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $area = Area::latest('id')->firstOrFail();
        $this->assertSame('القرين', $area->getTranslation('name', 'ar'));
        $this->assertSame('Al-Qurain', $area->getTranslation('name', 'en'));
        $this->assertTrue($area->is_active);

        $this->actingAs($user)
            ->put(route('dashboard.areas.update', $area), [
                'name' => ['ar' => 'القرين الجديدة', 'en' => 'New Al-Qurain'],
                'sort_order' => 4,
                'is_active' => 0,
            ])
            ->assertSessionHasNoErrors();

        $area->refresh();
        $this->assertSame('القرين الجديدة', $area->getTranslation('name', 'ar'));
        $this->assertSame(4, $area->sort_order);
        $this->assertFalse($area->is_active);

        $this->actingAs($user)
            ->delete(route('dashboard.areas.destroy', $area))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('areas', ['id' => $area->id]);
    }

    public function test_used_area_cannot_be_deleted(): void
    {
        $user = $this->userWithAreaPermissions(['view', 'delete']);
        $area = Area::create(['name' => ['ar' => 'منطقة مستخدمة', 'en' => 'Used Area']]);
        Property::create([
            'reference_code' => '77',
            'title' => ['ar' => 'عقار مرتبط', 'en' => 'Linked Property'],
            'area_id' => $area->id,
        ]);

        $this->actingAs($user)
            ->delete(route('dashboard.areas.destroy', $area))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('areas', ['id' => $area->id]);
    }

    public function test_area_actions_and_navigation_respect_permissions(): void
    {
        $viewer = $this->userWithAreaPermissions(['view']);

        $this->actingAs($viewer)
            ->get(route('dashboard.profile.edit'))
            ->assertOk()
            ->assertSee('إدارة المناطق');

        $this->actingAs($viewer)
            ->get(route('dashboard.areas.index'))
            ->assertOk()
            ->assertDontSee('@click="startAdd()"', false)
            ->assertDontSee('name="name[ar]"', false);

        $this->actingAs($viewer)
            ->post(route('dashboard.areas.store'), [
                'name' => ['ar' => 'غير مسموح'],
                'sort_order' => 1,
                'is_active' => 1,
            ])
            ->assertForbidden();

        $unauthorized = User::factory()->create();
        $this->actingAs($unauthorized)
            ->get(route('dashboard.areas.index'))
            ->assertForbidden();
    }

    private function userWithAreaPermissions(array $actions): User
    {
        $user = User::factory()->create();

        foreach ($actions as $action) {
            $permission = Permission::firstOrCreate([
                'name' => "areas.{$action}",
                'guard_name' => 'web',
            ]);
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}
