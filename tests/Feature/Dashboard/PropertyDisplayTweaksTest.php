<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\City;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use App\Support\PropertyFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** كل مندوبي المالك · أسعار بلا أصفار زائدة · اسم المالك بجانب المبنى · عمولة فوق 100% */
class PropertyDisplayTweaksTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_list_shows_every_sales_agent_of_the_owner(): void
    {
        $owner = PropertyOwner::create(['name' => 'مالك متعدد', 'phone_code' => '+965', 'phone' => '99887766']);
        $first = User::factory()->create(['name' => 'مندوب أول', 'is_agent' => true, 'phone' => '55551111']);
        $second = User::factory()->create(['name' => 'مندوب ثاني', 'is_agent' => true, 'phone' => '55552222']);

        Property::create(['reference_code' => '1', 'title' => ['ar' => 'أ', 'en' => 'A'], 'owner_id' => $owner->id, 'agent_id' => $first->id]);
        Property::create(['reference_code' => '2', 'title' => ['ar' => 'ب', 'en' => 'B'], 'owner_id' => $owner->id, 'agent_id' => $first->id]);
        Property::create(['reference_code' => '3', 'title' => ['ar' => 'ج', 'en' => 'C'], 'owner_id' => $owner->id, 'agent_id' => $second->id]);

        $this->actingAs($this->userWith(['property_owners.view']))->get(route('dashboard.owners.index'))
            ->assertOk()
            ->assertSeeInOrder(['مالك متعدد', 'مندوب أول', '(2)', 'مندوب ثاني', '(1)'])
            ->assertSee('openAgents(', false)
            ->assertSee('55552222');
    }

    public function test_prices_drop_needless_decimal_zeros_without_changing_the_value(): void
    {
        $this->assertSame('250,000', PropertyFields::price('250000.000'));
        $this->assertSame('1,250.5', PropertyFields::price('1250.500'));
        $this->assertSame('99.125', PropertyFields::price(99.125));
        $this->assertSame('0', PropertyFields::price(null));
        $this->assertSame('450', PropertyFields::priceInput('450.000'));
        $this->assertSame('', PropertyFields::priceInput(null));

        $property = Property::create(['reference_code' => '9', 'title' => ['ar' => 'سعر', 'en' => 'Price'], 'price' => 250000, 'purpose' => 'sale']);
        $viewer = $this->userWith(['properties.view', 'properties.edit']);

        $this->actingAs($viewer)->get(route('dashboard.properties.index'))->assertOk()->assertSee('250,000')->assertDontSee('250,000.000');
        $this->actingAs($viewer)->get(route('dashboard.properties.show', $property))->assertOk()->assertSee('250,000')->assertDontSee('250,000.000');
        $this->actingAs($viewer)->get(route('dashboard.properties.edit', $property))->assertOk()->assertSee('value="250000"', false);
    }

    public function test_owner_name_appears_beside_the_building_in_the_dashboard_only(): void
    {
        $owner = PropertyOwner::create(['name' => 'سالم المالك', 'phone_code' => '+965', 'phone' => '99887766']);
        $property = Property::create([
            'reference_code' => '12', 'title' => ['ar' => 'شقة البرج', 'en' => 'Tower flat'],
            'building_name' => 'برج السالمية', 'owner_id' => $owner->id,
        ]);
        $noBuilding = Property::create(['reference_code' => '13', 'title' => ['ar' => 'بلا مبنى', 'en' => 'No building'], 'owner_id' => $owner->id]);

        $this->assertSame('مبنى: برج السالمية · المالك: سالم المالك', $property->buildingLabel());
        $this->assertSame('المالك: سالم المالك', $noBuilding->buildingLabel());
        $this->assertSame('12 — مبنى: برج السالمية · المالك: سالم المالك', $property->codeLabel());

        $this->actingAs($this->userWith(['properties.view']))->get(route('dashboard.properties.index'))
            ->assertOk()->assertSee('المبنى / المالك')->assertSeeInOrder(['#12', 'برج السالمية', 'سالم المالك']);

        $this->get(route('site.property', $property))->assertOk()->assertDontSee('سالم المالك');
    }

    public function test_commission_rate_may_exceed_one_hundred_percent_up_to_the_column_limit(): void
    {
        $city = City::create(['name' => ['ar' => 'حولي', 'en' => 'Hawalli']]);
        $area = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $city->id]);
        PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential']);
        $apartment = UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment'], 'category' => 'residential']);
        $status = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
        $user = $this->userWith(['properties.view', 'properties.create']);

        $payload = fn (mixed $rate) => [
            'title' => ['ar' => 'عمولة كبيرة', 'en' => 'Big commission'],
            'city_id' => $city->id, 'area_id' => $area->id,
            'category_id' => PropertyCategory::where('key', 'residential')->value('id'), 'unit_type_id' => $apartment->id,
            'purpose' => 'rent', 'price' => 600, 'price_period' => 'monthly', 'status_id' => $status->id,
            'owner_commission_rate' => $rate, 'amenities' => [],
        ];

        $this->actingAs($user)->post(route('dashboard.properties.store'), $payload(1000))->assertSessionHasErrors('owner_commission_rate');
        $this->actingAs($user)->post(route('dashboard.properties.store'), $payload(150))->assertSessionHasNoErrors();

        $property = Property::latest('id')->firstOrFail();
        $this->assertSame('150.00', $property->owner_commission_rate);

        $this->actingAs($user)->get(route('dashboard.properties.show', $property))->assertOk()->assertSee('150%');
    }

    public function test_property_page_links_to_the_website_and_site_photo_has_arrows(): void
    {
        $property = Property::create(['reference_code' => '21', 'title' => ['ar' => 'رابط الموقع', 'en' => 'Site link']]);

        $this->actingAs($this->userWith(['properties.view']))->get(route('dashboard.properties.show', $property))
            ->assertOk()->assertSee('عرض على الموقع')->assertSee('href="'.route('site.property', $property).'"', false);

        auth()->logout();
        $this->get(route('site.property', $property))->assertOk()->assertSee('@click="move(-1)"', false)->assertSee('@click="move(1)"', false);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        return $user;
    }
}
