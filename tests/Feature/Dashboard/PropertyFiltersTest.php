<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\Property;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PropertyFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_search_matches_property_name_and_reference_code(): void
    {
        $user = $this->propertyViewer();
        [$firstArea, $secondArea, $apartment, $villa, $status] = $this->lookups();

        $this->property('701', 'شقة مميزة في السالمية', $firstArea, $apartment, $status);
        $this->property('702', 'فيلا هادئة في مشرف', $secondArea, $villa, $status);

        $this->actingAs($user)
            ->get(route('dashboard.properties.index', ['search' => 'السالمية']))
            ->assertOk()
            ->assertSee('701')
            ->assertDontSee('702');

        $this->actingAs($user)
            ->get(route('dashboard.properties.index', ['search' => '702']))
            ->assertOk()
            ->assertSee('702')
            ->assertDontSee('701');
    }

    public function test_properties_can_be_filtered_by_area_and_unit_type(): void
    {
        $user = $this->propertyViewer();
        [$firstArea, $secondArea, $apartment, $villa, $status] = $this->lookups();

        $this->property('711', 'شقة للاختبار', $firstArea, $apartment, $status);
        $this->property('712', 'فيلا للاختبار', $secondArea, $villa, $status);

        $this->actingAs($user)
            ->get(route('dashboard.properties.index', ['area_id' => $firstArea->id]))
            ->assertOk()
            ->assertSee('name="area_id"', false)
            ->assertSee('711')
            ->assertDontSee('712');

        $this->actingAs($user)
            ->get(route('dashboard.properties.index', ['unit_type_id' => $villa->id]))
            ->assertOk()
            ->assertSee('name="unit_type_id"', false)
            ->assertSee('712')
            ->assertDontSee('711');
    }

    private function propertyViewer(): User
    {
        $user = User::factory()->create();
        $permission = Permission::firstOrCreate([
            'name' => 'properties.view',
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($permission);

        return $user;
    }

    /** @return array{Area, Area, UnitType, UnitType, PropertyStatus} */
    private function lookups(): array
    {
        $firstArea = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'sort_order' => 1]);
        $secondArea = Area::create(['name' => ['ar' => 'مشرف', 'en' => 'Mishref'], 'sort_order' => 2]);
        $apartment = UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment'], 'sort_order' => 1]);
        $villa = UnitType::create(['name' => ['ar' => 'فيلا', 'en' => 'Villa'], 'sort_order' => 2]);
        $status = PropertyStatus::create([
            'name' => ['ar' => 'متاح', 'en' => 'Available'],
            'key' => 'available',
        ]);

        return [$firstArea, $secondArea, $apartment, $villa, $status];
    }

    private function property(
        string $reference,
        string $title,
        Area $area,
        UnitType $unitType,
        PropertyStatus $status,
    ): Property {
        return Property::create([
            'reference_code' => $reference,
            'title' => ['ar' => $title, 'en' => $title],
            'area_id' => $area->id,
            'unit_type_id' => $unitType->id,
            'status_id' => $status->id,
        ]);
    }
}
