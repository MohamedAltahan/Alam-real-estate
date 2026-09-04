<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\City;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use App\Services\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PropertyFormDependenciesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private City $city;

    private Area $area;

    private Area $otherArea;

    private PropertyCategory $residential;

    private PropertyCategory $commercial;

    private UnitType $apartment;

    private UnitType $office;

    private PropertyStatus $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        foreach (['properties.view', 'properties.create', 'properties.edit'] as $name) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $this->city = City::create(['name' => ['ar' => 'محافظة حولي', 'en' => 'Hawalli']]);
        $otherCity = City::create(['name' => ['ar' => 'محافظة الأحمدي', 'en' => 'Ahmadi']]);
        $this->area = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $this->city->id]);
        $this->otherArea = Area::create(['name' => ['ar' => 'الفنطاس', 'en' => 'Fintas'], 'city_id' => $otherCity->id]);
        $this->residential = PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential']);
        $this->commercial = PropertyCategory::create(['name' => ['ar' => 'تجاري', 'en' => 'Commercial'], 'key' => 'commercial']);
        $this->apartment = UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment'], 'category' => 'residential']);
        $this->office = UnitType::create(['name' => ['ar' => 'مكتب', 'en' => 'Office'], 'category' => 'commercial']);
        $this->status = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
    }

    public function test_reference_codes_are_numeric_and_increment(): void
    {
        Property::create(['reference_code' => '7', 'title' => ['ar' => 'قديم', 'en' => 'Old']]);

        $service = app(PropertyService::class);
        $this->assertSame('8', $service->generateReferenceCode());

        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $created = Property::latest('id')->firstOrFail();
        $this->assertSame('8', $created->reference_code);
        $this->assertSame($this->city->id, $created->city_id);
        $this->assertSame('https://maps.app.goo.gl/abc', $created->map_url);
        $this->assertSame('برج السالمية', $created->building_name);
        $this->assertSame('2.50', $created->owner_commission_rate);
        $this->assertSame('أبو خالد', $created->guard_name);
        $this->assertSame('99887766', $created->guard_phone);
        $this->assertTrue($created->is_furnished);
        $this->assertSame(3, $created->bedrooms);
    }

    public function test_area_must_belong_to_the_selected_governorate(): void
    {
        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload([
            'city_id' => $this->city->id,
            'area_id' => $this->otherArea->id,
        ]))->assertSessionHasErrors('area_id');

        // بدون محافظة → تُستنتج من المنطقة
        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload([
            'city_id' => '',
            'area_id' => $this->otherArea->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($this->otherArea->city_id, Property::latest('id')->firstOrFail()->city_id);
    }

    public function test_unit_type_must_match_the_category_and_bedrooms_are_dropped_for_commercial(): void
    {
        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload([
            'category_id' => $this->commercial->id,
            'unit_type_id' => $this->apartment->id,
        ]))->assertSessionHasErrors('unit_type_id');

        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload([
            'category_id' => $this->commercial->id,
            'unit_type_id' => $this->office->id,
            'bedrooms' => 4,
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Property::latest('id')->firstOrFail()->bedrooms);
    }

    public function test_property_form_lists_only_the_four_statuses_and_governorates(): void
    {
        PropertyStatus::create(['name' => ['ar' => 'قيد الإنتظار', 'en' => 'Pending'], 'key' => 'pending']);

        $this->actingAs($this->user)->get(route('dashboard.properties.create'))
            ->assertOk()
            ->assertSee('المحافظة')
            ->assertSee('مندوب المبيعات')
            ->assertSee('رابط موقع العقار على خرائط جوجل')
            ->assertSee('عمولة المالك')
            ->assertSee('قيد الإنتظار')
            ->assertDontSee('محجوز')
            ->assertDontSee('مسؤول العقار');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => ['ar' => 'شقة السالمية', 'en' => 'Salmiya flat'],
            'city_id' => $this->city->id,
            'area_id' => $this->area->id,
            'category_id' => $this->residential->id,
            'unit_type_id' => $this->apartment->id,
            'purpose' => 'rent',
            'price' => 450,
            'price_period' => 'monthly',
            'status_id' => $this->status->id,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'is_furnished' => 1,
            'building_name' => 'برج السالمية',
            'map_url' => 'https://maps.app.goo.gl/abc',
            'owner_commission_rate' => 2.5,
            'guard_name' => 'أبو خالد',
            'guard_phone' => '99887766',
            'amenities' => [],
        ], $overrides);
    }
}
