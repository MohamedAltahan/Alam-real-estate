<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UnitTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_create_update_and_delete_an_unused_unit_type(): void
    {
        $user = $this->userWithUnitTypePermissions(['view', 'create', 'edit', 'delete']);

        $this->actingAs($user)
            ->get(route('dashboard.unit-types.index'))
            ->assertOk()
            ->assertSee('أنواع العقارات')
            ->assertSee('إضافة نوع');

        $this->actingAs($user)
            ->post(route('dashboard.unit-types.store'), [
                'name' => ['ar' => 'شاليه', 'en' => 'Chalet'],
                'category' => 'residential',
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $unitType = UnitType::latest('id')->firstOrFail();
        $this->assertSame('شاليه', $unitType->getTranslation('name', 'ar'));
        $this->assertSame('Chalet', $unitType->getTranslation('name', 'en'));
        $this->assertSame('residential', $unitType->category);
        $this->assertTrue($unitType->is_active);
        $autoSortOrder = $unitType->sort_order;

        $this->actingAs($user)
            ->put(route('dashboard.unit-types.update', $unitType), [
                'name' => ['ar' => 'شاليه فاخر', 'en' => 'Luxury Chalet'],
                'category' => 'commercial',
                'is_active' => 0,
            ])
            ->assertSessionHasNoErrors();

        $unitType->refresh();
        $this->assertSame('شاليه فاخر', $unitType->getTranslation('name', 'ar'));
        $this->assertSame('commercial', $unitType->category);
        $this->assertSame($autoSortOrder, $unitType->sort_order); // الترتيب يُضبط تلقائيًا ولا يتغير بالتعديل
        $this->assertFalse($unitType->is_active);

        $this->actingAs($user)
            ->delete(route('dashboard.unit-types.destroy', $unitType))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('unit_types', ['id' => $unitType->id]);
    }

    public function test_unit_type_requested_by_a_client_cannot_be_deleted(): void
    {
        $user = $this->userWithUnitTypePermissions(['view', 'delete']);
        $unitType = UnitType::create(['name' => ['ar' => 'نوع مستخدم', 'en' => 'Used Type']]);
        Client::create([
            'name' => 'عميل مرتبط',
            'phone' => '50000000',
        ])->needs()->create(['unit_type_id' => $unitType->id]);

        $this->actingAs($user)
            ->delete(route('dashboard.unit-types.destroy', $unitType))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('unit_types', ['id' => $unitType->id]);
    }

    public function test_unit_type_actions_and_navigation_respect_permissions(): void
    {
        $viewer = $this->userWithUnitTypePermissions(['view']);

        $this->actingAs($viewer)
            ->get(route('dashboard.profile.edit'))
            ->assertOk()
            ->assertSee('أنواع العقارات');

        $this->actingAs($viewer)
            ->get(route('dashboard.unit-types.index'))
            ->assertOk()
            ->assertDontSee('@click="startAdd()"', false)
            ->assertDontSee('name="name[ar]"', false);

        $this->actingAs($viewer)
            ->post(route('dashboard.unit-types.store'), [
                'name' => ['ar' => 'غير مسموح'],
                'category' => 'residential',
                'is_active' => 1,
            ])
            ->assertForbidden();

        $unauthorized = User::factory()->create();
        $this->actingAs($unauthorized)
            ->get(route('dashboard.unit-types.index'))
            ->assertForbidden();
    }

    public function test_active_unit_types_are_loaded_dynamically_into_property_filter_in_their_order(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'properties.view',
            'guard_name' => 'web',
        ]));

        UnitType::create([
            'name' => ['ar' => 'النوع الثاني', 'en' => 'Second Type'],
            'sort_order' => 20,
            'is_active' => true,
        ]);
        UnitType::create([
            'name' => ['ar' => 'النوع الأول', 'en' => 'First Type'],
            'sort_order' => 10,
            'is_active' => true,
        ]);
        UnitType::create([
            'name' => ['ar' => 'نوع معطل', 'en' => 'Inactive Type'],
            'sort_order' => 1,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard.properties.index'))
            ->assertOk()
            ->assertSeeInOrder(['النوع الأول', 'النوع الثاني'])
            ->assertDontSee('نوع معطل');
    }

    private function userWithUnitTypePermissions(array $actions): User
    {
        $user = User::factory()->create();

        foreach ($actions as $action) {
            $permission = Permission::firstOrCreate([
                'name' => "unit_types.{$action}",
                'guard_name' => 'web',
            ]);
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}
