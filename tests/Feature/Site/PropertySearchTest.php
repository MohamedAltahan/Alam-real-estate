<?php

namespace Tests\Feature\Site;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** بحث الموقع الحر: الرقم المرجعي أو اسم العقار أو اسم المبنى */
class PropertySearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // الأقدم هو المطابق تماماً، فترتيب «الأحدث» وحده كان سيضعه ثانياً
        Carbon::setTestNow(now()->subDays(3));
        Property::create(['reference_code' => '31', 'title' => ['ar' => 'شقة الرقم المطابق', 'en' => 'Exact ref flat']]);
        Carbon::setTestNow(now()->addDays(2));
        Property::create(['reference_code' => '131', 'title' => ['ar' => 'شقة الرقم الأطول', 'en' => 'Longer ref flat']]);
        Carbon::setTestNow();
        Property::create(['reference_code' => '500', 'title' => ['ar' => 'شقة مفروشة في السالمية', 'en' => 'Salmiya flat']]);
        Property::create(['reference_code' => '600', 'title' => ['ar' => 'دور أرضي', 'en' => 'Ground floor'], 'building_name' => 'برج الحمرا']);
    }

    public function test_home_search_box_has_a_free_text_name_or_reference_field(): void
    {
        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee('ابحث باسم العقار أو المبنى أو الرقم المرجعي')
            ->assertDontSee('name="reference"', false);
    }

    public function test_search_matches_property_name(): void
    {
        $this->get(route('site.properties', ['q' => 'مفروشة']))
            ->assertOk()
            ->assertSee('شقة مفروشة في السالمية')
            ->assertDontSee('دور أرضي')
            ->assertDontSee('شقة الرقم المطابق');
    }

    public function test_search_matches_building_name(): void
    {
        $this->get(route('site.properties', ['q' => 'الحمرا']))
            ->assertOk()
            ->assertSee('دور أرضي')
            ->assertDontSee('شقة مفروشة في السالمية');
    }

    public function test_exact_reference_shows_only_that_property(): void
    {
        // «31» رقم عقار موجود ⇒ يظهر وحده، بلا 131
        $this->get(route('site.properties', ['q' => '31']))
            ->assertOk()
            ->assertSee('شقة الرقم المطابق')
            ->assertDontSee('شقة الرقم الأطول')
            ->assertDontSee('شقة مفروشة في السالمية');

        // جزء من رقم لا يطابق عقاراً بعينه ⇒ بحث جزئي كالمعتاد
        $this->get(route('site.properties', ['q' => '13']))
            ->assertOk()
            ->assertSee('شقة الرقم الأطول')
            ->assertDontSee('شقة الرقم المطابق');
    }

    public function test_exact_reference_ignores_the_category_tab_of_the_home_search(): void
    {
        $residential = \App\Models\PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential']);
        $commercial = \App\Models\PropertyCategory::create(['name' => ['ar' => 'تجاري', 'en' => 'Commercial'], 'key' => 'commercial']);
        Property::where('reference_code', '31')->update(['category_id' => $commercial->id]);

        // البحث من تبويب «سكني» برقم عقار تجاري يجده
        $this->get(route('site.properties', ['category' => $residential->id, 'q' => '31']))
            ->assertOk()
            ->assertSee('شقة الرقم المطابق');
    }

    public function test_search_term_is_kept_in_the_filters_panel(): void
    {
        $this->get(route('site.properties', ['q' => 'الحمرا']))
            ->assertOk()
            ->assertSee('name="q" value="الحمرا"', false);
    }
}
