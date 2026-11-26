<?php

namespace Tests\Feature\Site;

use App\Models\City;
use App\Models\PropertyCategory;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** بانر «عروض مميزة»: إظهار/إخفاء + نص الزر + وجهته (تصنيف · نوع وحدة · محافظة) من «إدارة الموقع» */
class OffersBannerTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private PropertyCategory $commercial;

    private UnitType $office;

    private UnitType $flat;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->create();
        foreach (['website.view', 'website.edit'] as $name) {
            $this->editor->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential', 'is_active' => true]);
        $this->commercial = PropertyCategory::create(['name' => ['ar' => 'تجاري', 'en' => 'Commercial'], 'key' => 'commercial', 'is_active' => true]);
        $this->office = UnitType::create(['name' => ['ar' => 'مكتب', 'en' => 'Office'], 'category' => 'commercial', 'is_active' => true]);
        $this->flat = UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Flat'], 'category' => 'residential', 'is_active' => true]);
        $this->city = City::create(['name' => ['ar' => 'حولي', 'en' => 'Hawalli'], 'is_active' => true]);
    }

    public function test_banner_button_opens_the_chosen_category_with_custom_text(): void
    {
        $this->save(['visible' => 1, 'button_ar' => 'شاهد المكاتب', 'link' => [
            'category' => $this->commercial->id, 'unit_type' => $this->office->id, 'city' => $this->city->id,
        ]]);

        $href = route('site.properties', ['category' => $this->commercial->id, 'unit_type' => $this->office->id, 'city' => $this->city->id]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('عروض الصيف')
            ->assertSee('href="'.e($href).'"', false)
            ->assertSee('شاهد المكاتب');
    }

    public function test_default_text_and_all_properties_when_nothing_is_chosen(): void
    {
        $this->save(['visible' => 1]);

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('href="'.route('site.properties').'"', false)
            ->assertSee('اكتشف العروض');
    }

    public function test_unit_type_of_another_category_is_ignored(): void
    {
        $this->save(['visible' => 1, 'link' => ['category' => $this->commercial->id, 'unit_type' => $this->flat->id]]);

        $this->get(route('site.home'))
            ->assertSee('href="'.e(route('site.properties', ['category' => $this->commercial->id])).'"', false);
    }

    public function test_banner_can_be_hidden_and_shown_again(): void
    {
        $this->save(['visible' => 0]);
        $this->get(route('site.home'))->assertOk()->assertDontSee('عروض الصيف');

        $this->save(['visible' => 1]);
        $this->get(route('site.home'))->assertOk()->assertSee('عروض الصيف');
    }

    public function test_dashboard_shows_the_banner_controls(): void
    {
        $this->actingAs($this->editor)->get(route('dashboard.website.index'))
            ->assertOk()
            ->assertSee('name="featured[visible]"', false)
            ->assertSee('name="featured[button_ar]"', false)
            ->assertSee('name="featured[link][category]"', false)
            ->assertSee('name="featured[link][unit_type]"', false)
            ->assertSee('name="featured[link][city]"', false);
    }

    private function save(array $featured): void
    {
        $this->actingAs($this->editor)->put(route('dashboard.website.homepage'), [
            'featured' => ['title_ar' => 'عروض الصيف', 'description_ar' => 'أفضل العروض'] + $featured,
        ])->assertSessionHasNoErrors();
    }
}
