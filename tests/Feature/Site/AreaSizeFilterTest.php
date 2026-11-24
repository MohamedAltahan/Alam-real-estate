<?php

namespace Tests\Feature\Site;

use App\Models\Property;
use App\Models\PropertyCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** «المساحة» بدل «غرف النوم» في بحث الصفحة الرئيسية عند اختيار تجاري */
class AreaSizeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_box_swaps_bedrooms_for_area_on_commercial(): void
    {
        PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential', 'is_active' => true, 'sort_order' => 1]);
        $commercial = PropertyCategory::create(['name' => ['ar' => 'تجاري', 'en' => 'Commercial'], 'key' => 'commercial', 'is_active' => true, 'sort_order' => 2]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee("commercial: '{$commercial->id}'", false)
            ->assertSee('x-if="commercial && cat === commercial"', false)
            ->assertSee('name="bedrooms"', false)
            ->assertSee('name="size"', false)
            ->assertSee('200-500', false);
    }

    public function test_properties_page_filters_by_area_size(): void
    {
        Property::create(['reference_code' => '951', 'title' => ['ar' => 'محل صغير', 'en' => 'Small shop'], 'area_size' => 40]);
        Property::create(['reference_code' => '952', 'title' => ['ar' => 'مكتب متوسط', 'en' => 'Mid office'], 'area_size' => 135]);
        Property::create(['reference_code' => '953', 'title' => ['ar' => 'مخزن كبير', 'en' => 'Big store'], 'area_size' => 900]);

        $this->get(route('site.properties', ['size' => '100-200']))
            ->assertOk()
            ->assertSee('مكتب متوسط')
            ->assertDontSee('محل صغير')
            ->assertDontSee('مخزن كبير')
            ->assertSee('name="size" value="100-200" checked', false);

        $this->get(route('site.properties', ['size' => '500+']))->assertSee('مخزن كبير')->assertDontSee('مكتب متوسط');
        $this->get(route('site.properties', ['size' => '0-50']))->assertSee('محل صغير')->assertDontSee('مكتب متوسط');
    }
}
