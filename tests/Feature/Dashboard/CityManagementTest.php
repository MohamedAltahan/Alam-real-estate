<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\City;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\KuwaitAreasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CityManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_cities_can_be_managed_with_area_permissions(): void
    {
        $user = $this->userWithAreaPermissions(['view', 'create', 'edit', 'delete']);

        $this->actingAs($user)->get(route('dashboard.cities.index'))
            ->assertOk()
            ->assertSee('إدارة المدن')
            ->assertSee('إضافة مدينة');

        $this->actingAs($user)->post(route('dashboard.cities.store'), [
            'name' => ['ar' => 'محافظة الجهراء', 'en' => 'Jahra'],
            'sort_order' => 5,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $city = City::latest('id')->firstOrFail();
        $this->assertSame('محافظة الجهراء', $city->getTranslation('name', 'ar'));

        $this->actingAs($user)->put(route('dashboard.cities.update', $city), [
            'name' => ['ar' => 'الجهراء', 'en' => 'Jahra'],
            'sort_order' => 2,
            'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame('الجهراء', $city->refresh()->getTranslation('name', 'ar'));
        $this->assertFalse($city->is_active);

        // مدينة بها مناطق لا تُحذف
        $area = Area::create(['name' => ['ar' => 'القصر', 'en' => 'Qasr'], 'city_id' => $city->id]);
        $this->actingAs($user)->delete(route('dashboard.cities.destroy', $city))->assertSessionHas('error');
        $this->assertDatabaseHas('cities', ['id' => $city->id]);

        $area->delete();
        $this->actingAs($user)->delete(route('dashboard.cities.destroy', $city))->assertSessionHas('success');
        $this->assertDatabaseMissing('cities', ['id' => $city->id]);
    }

    public function test_area_can_be_linked_to_a_city_and_areas_index_filters_by_city(): void
    {
        $user = $this->userWithAreaPermissions(['view', 'create']);
        $hawalli = City::create(['name' => ['ar' => 'محافظة حولي', 'en' => 'Hawalli']]);
        $ahmadi = City::create(['name' => ['ar' => 'محافظة الأحمدي', 'en' => 'Ahmadi']]);
        Area::create(['name' => ['ar' => 'الفنطاس', 'en' => 'Fintas'], 'city_id' => $ahmadi->id]);

        $this->actingAs($user)->post(route('dashboard.areas.store'), [
            'name' => ['ar' => 'السالمية', 'en' => 'Salmiya'],
            'city_id' => $hawalli->id,
            'sort_order' => 1,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame($hawalli->id, Area::where('name->ar', 'السالمية')->first()?->city_id ?? Area::latest('id')->first()->city_id);

        $this->actingAs($user)->get(route('dashboard.areas.index', ['city_id' => $hawalli->id]))
            ->assertOk()
            ->assertSee('السالمية')
            ->assertDontSee('الفنطاس');

        $viewer = $this->userWithAreaPermissions(['view']);
        $this->actingAs($viewer)->post(route('dashboard.cities.store'), ['name' => ['ar' => 'x'], 'sort_order' => 1])->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('dashboard.cities.index'))->assertForbidden();
    }

    public function test_kuwait_seeder_is_idempotent_and_links_existing_areas(): void
    {
        $hawalli = Area::create(['name' => ['ar' => 'حولي'], 'sort_order' => 1]);
        $client = Client::create(['name' => 'عميل', 'phone_code' => '+965', 'phone' => '55000000']);
        $need = $client->needs()->create(['area_id' => $hawalli->id]);

        $this->seed(KuwaitAreasSeeder::class);

        $cities = City::count();
        $areas = Area::count();

        $this->assertSame(6, $cities);
        $this->assertGreaterThan(150, $areas);
        $this->assertNotNull($hawalli->refresh()->city_id);
        $this->assertSame('Hawalli', $hawalli->getTranslation('name', 'en'));
        $this->assertSame($hawalli->city_id, $need->refresh()->city_id);

        $this->seed(KuwaitAreasSeeder::class);

        $this->assertSame($cities, City::count());
        $this->assertSame($areas, Area::count());
    }

    private function userWithAreaPermissions(array $actions): User
    {
        $user = User::factory()->create();

        foreach ($actions as $action) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => "areas.{$action}", 'guard_name' => 'web']));
        }

        return $user;
    }
}
