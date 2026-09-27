<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\City;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** «إدارة الموقع ← الصفحة الرئيسية»: قسما «العقارات المميزة» و«المحافظات» ومفتاح إظهارهما */
class WebsiteHomeSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_saves_governorates_and_section_visibility(): void
    {
        $capital = City::create(['name' => ['ar' => 'محافظة العاصمة', 'en' => 'Capital']]);
        $hawalli = City::create(['name' => ['ar' => 'محافظة حولي', 'en' => 'Hawalli']]);

        $this->actingAs($this->editor())
            ->put(route('dashboard.website.homepage'), [
                'areas' => ['title_ar' => 'المحافظات', 'title_en' => 'Governorates', 'visible' => '0'],
                'area_items' => [
                    'area-a' => ['city_id' => (string) $hawalli->id],
                    'area-b' => ['city_id' => (string) $capital->id],
                    'area-c' => ['city_id' => (string) $hawalli->id],   // مكررة ⇒ تُتجاهل
                    'area-d' => ['city_id' => ''],
                ],
                'featured_properties' => ['title_ar' => 'مختارات علم', 'title_en' => 'Alam picks', 'visible' => '1'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $areas = PageSection::where('key', 'areas')->firstOrFail();
        $this->assertFalse($areas->is_visible);
        $this->assertSame([
            ['city_id' => (string) $hawalli->id, 'collection' => 'area-a'],
            ['city_id' => (string) $capital->id, 'collection' => 'area-b'],
        ], $areas->getTranslation('content', 'en')['items']);

        $featured = PageSection::where('key', 'featured_properties')->firstOrFail();
        $this->assertTrue($featured->is_visible);
        $this->assertSame('مختارات علم', $featured->getTranslation('content', 'ar')['title']);
        $this->assertSame('Alam picks', $featured->getTranslation('content', 'en')['title']);
    }

    public function test_sections_stay_visible_when_the_switch_is_not_sent(): void
    {
        $this->actingAs($this->editor())->put(route('dashboard.website.homepage'), [])->assertRedirect();

        $this->assertTrue(PageSection::where('key', 'areas')->firstOrFail()->is_visible);
        $this->assertTrue(PageSection::where('key', 'featured_properties')->firstOrFail()->is_visible);
    }

    public function test_editor_page_shows_governorates_with_live_counts_and_the_switches(): void
    {
        $capital = City::create(['name' => ['ar' => 'محافظة العاصمة', 'en' => 'Capital']]);
        $area = Area::create(['name' => ['ar' => 'شرق', 'en' => 'Sharq'], 'city_id' => $capital->id]);
        Property::create(['reference_code' => '801', 'title' => ['ar' => 'فيلا', 'en' => 'Villa'], 'area_id' => $area->id, 'city_id' => $capital->id, 'is_featured' => true]);

        $page = Page::create(['slug' => 'home', 'name' => 'الرئيسية']);
        $items = ['items' => [['city_id' => (string) $capital->id, 'collection' => 'area-1']]];
        PageSection::create(['page_id' => $page->id, 'key' => 'areas', 'is_visible' => false, 'content' => ['ar' => $items, 'en' => $items]]);

        $this->actingAs($this->editor())->get(route('dashboard.website.index'))->assertOk()
            ->assertSee('name="areas[visible]" value="0"', false)
            ->assertSee('name="featured_properties[visible]" value="1"', false)
            ->assertSee('name="area_items[area-1][city_id]"', false)
            ->assertSee('محافظة العاصمة')
            ->assertSee('إضافة محافظة')
            ->assertViewHas('featuredCount', 1)
            ->assertViewHas('cities', fn ($cities) => (int) $cities->firstWhere('id', $capital->id)->properties_count === 1);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        foreach (['website.view', 'website.edit'] as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $user;
    }
}
