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

    public function test_properties_can_be_filtered_by_creation_date_range(): void
    {
        $user = $this->propertyViewer();
        [$firstArea, $secondArea, $apartment, $villa, $status] = $this->lookups();

        $old = $this->property('721', 'عقار قديم', $firstArea, $apartment, $status);
        $old->forceFill(['created_at' => now()->subDays(30)])->saveQuietly();
        $this->property('722', 'عقار جديد', $secondArea, $villa, $status);

        $this->actingAs($user)
            ->get(route('dashboard.properties.index', ['from' => now()->subDays(3)->toDateString()]))
            ->assertOk()->assertSee('name="from"', false)->assertSee('722')->assertDontSee('721');

        $this->actingAs($user)
            ->get(route('dashboard.properties.index', ['to' => now()->subDays(10)->toDateString()]))
            ->assertOk()->assertSee('721')->assertDontSee('722');

        $this->actingAs($user)
            ->get(route('dashboard.properties.index', ['from' => now()->subDays(40)->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()->assertSee('721')->assertSee('722');
    }

    public function test_status_can_be_changed_inline_from_the_list_and_sets_sold_at(): void
    {
        $viewer = $this->propertyViewer();
        $editor = $this->propertyViewer();
        $editor->givePermissionTo(Permission::firstOrCreate(['name' => 'properties.edit', 'guard_name' => 'web']));
        [$firstArea, , $apartment, , $available] = $this->lookups();
        $sold = PropertyStatus::create(['name' => ['ar' => 'مباع', 'en' => 'Sold'], 'key' => 'sold', 'color' => '#C0392B']);
        $inactive = PropertyStatus::create(['name' => ['ar' => 'مؤرشف', 'en' => 'Archived'], 'key' => 'archived', 'is_active' => false]);

        $property = $this->property('731', 'عقار للبيع', $firstArea, $apartment, $available);

        // المشاهد يرى الشارة فقط، والمحرّر يرى قائمة التغيير
        $this->actingAs($viewer)->get(route('dashboard.properties.index'))->assertOk()->assertDontSee('data-property-status=', false);
        $this->actingAs($editor)->get(route('dashboard.properties.index'))->assertOk()
            ->assertSee('data-property-status="'.route('dashboard.properties.status', $property).'"', false);

        $this->actingAs($viewer)->patchJson(route('dashboard.properties.status', $property), ['status_id' => $sold->id])->assertForbidden();

        $this->actingAs($editor)->patchJson(route('dashboard.properties.status', $property), ['status_id' => $sold->id])
            ->assertOk()->assertJsonPath('status.key', 'sold')->assertJsonPath('status.name', 'مباع')->assertJsonPath('status.color', '#C0392B');
        $property->refresh();
        $this->assertSame('sold', $property->status->key);
        $this->assertNotNull($property->sold_at);

        $this->actingAs($editor)->patchJson(route('dashboard.properties.status', $property), ['status_id' => $available->id])->assertOk();
        $this->assertNull($property->fresh()->sold_at);

        $this->actingAs($editor)->patchJson(route('dashboard.properties.status', $property), ['status_id' => $inactive->id])->assertUnprocessable();
        $this->actingAs($editor)->patchJson(route('dashboard.properties.status', $property), ['status_id' => 9999])->assertUnprocessable();
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
