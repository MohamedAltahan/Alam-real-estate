<?php

namespace Tests\Feature\Site;

use App\Models\Area;
use App\Models\City;
use App\Models\PropertyCategory;
use App\Models\UnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** أنواع الوحدات تتبع التصنيف (سكني/تجاري) في بحث الموقع، والمنطقة تتبع المحافظة */
class UnitTypesByCategoryTest extends TestCase
{
    use RefreshDatabase;

    private PropertyCategory $residential;

    private PropertyCategory $commercial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->residential = PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential', 'sort_order' => 1, 'is_active' => true]);
        $this->commercial = PropertyCategory::create(['name' => ['ar' => 'تجاري', 'en' => 'Commercial'], 'key' => 'commercial', 'sort_order' => 2, 'is_active' => true]);

        UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment'], 'category' => 'residential', 'is_active' => true]);
        UnitType::create(['name' => ['ar' => 'مكتب', 'en' => 'Office'], 'category' => 'commercial', 'is_active' => true]);
    }

    public function test_home_search_fields_follow_the_requested_order(): void
    {
        $html = $this->get(route('site.home'))->assertOk()->getContent();

        $positions = collect(['name="city"', 'name="area"', 'name="unit_type"', 'name="bedrooms"', 'name="price"', 'name="q"'])
            ->map(fn ($needle) => strpos($html, $needle));

        $this->assertNotContains(false, $positions->all());
        $this->assertSame($positions->sort()->values()->all(), $positions->all());
    }

    public function test_home_search_unit_types_carry_their_category_and_areas_their_governorate(): void
    {
        $city = City::create(['name' => ['ar' => 'حولي', 'en' => 'Hawalli'], 'is_active' => true]);
        Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $city->id, 'is_active' => true]);

        // @js يرمّز علامات التنصيص كـ (backslash)u0022
        $q = fn (string $s) => str_replace('"', chr(92).'u0022', $s);

        $this->get(route('site.home'))
            ->assertOk()
            // كل نوع يحمل مفتاح تصنيفه، والقائمة تُصفّى حسب التبويب المختار
            ->assertSee($q('"k":"residential"'), false)
            ->assertSee($q('"k":"commercial"'), false)
            ->assertSee('item.k === catKeys[cat]', false)
            // كل منطقة تحمل محافظتها
            ->assertSee($q('"c":"'.$city->id.'"'), false)
            ->assertSee('item.c === city', false);
    }

    public function test_properties_page_lists_only_the_chosen_category_unit_types(): void
    {
        $this->get(route('site.properties', ['category' => $this->commercial->id]))
            ->assertOk()
            ->assertSee('مكتب')
            ->assertDontSee('شقة');

        $this->get(route('site.properties', ['category' => $this->residential->id]))
            ->assertOk()
            ->assertSee('شقة')
            ->assertDontSee('مكتب');

        // بدون تصنيف تظهر كل الأنواع
        $this->get(route('site.properties'))
            ->assertOk()
            ->assertSee('شقة')
            ->assertSee('مكتب');
    }

    public function test_list_property_form_groups_unit_types_by_category(): void
    {
        $html = $this->get(route('site.list-property'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<optgroup label="سكني">\s*<option value="\d+"\s*>شقة/u', $html);
        $this->assertMatchesRegularExpression('/<optgroup label="تجاري">\s*<option value="\d+"\s*>مكتب/u', $html);
    }
}
