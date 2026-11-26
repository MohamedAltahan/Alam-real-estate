<?php

namespace Tests\Feature\Site;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** بحث الرئيسية: «للبيع» لا يظهر إلا عند وجود عقار للبيع */
class HomePurposeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // الفلتر يظهر مع تبويبات التصنيف
        \App\Models\PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential', 'is_active' => true]);
    }

    private function property(string $reference, string $purpose, string $title): Property
    {
        return Property::create(['reference_code' => $reference, 'purpose' => $purpose, 'title' => ['ar' => $title, 'en' => $title]]);
    }

    public function test_only_rent_is_shown_when_nothing_is_for_sale(): void
    {
        $this->property('1', 'rent', 'شقة');
        // المحذوف لا يُظهر الخيار
        $this->property('2', 'sale', 'فيلا')->delete();

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('للإيجار')
            ->assertDontSee('للبيع')
            ->assertDontSee('name="purpose"', false);
    }

    public function test_sale_option_appears_with_rent_selected_by_default(): void
    {
        $this->property('1', 'sale', 'فيلا');

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('للبيع')
            ->assertSee('name="purpose" value="rent"', false)
            ->assertSee("purpose: 'rent'", false);
    }

    public function test_results_follow_the_chosen_purpose_but_exact_reference_ignores_it(): void
    {
        $this->property('10', 'rent', 'شقة الإيجار');
        $this->property('20', 'sale', 'فيلا البيع');

        $this->get(route('site.properties', ['purpose' => 'rent']))
            ->assertSee('شقة الإيجار')
            ->assertDontSee('فيلا البيع')
            ->assertSee('name="purpose" value="rent"', false);

        $this->get(route('site.properties', ['home' => 1, 'purpose' => 'rent', 'q' => '20']))
            ->assertSee('فيلا البيع');
    }
}
