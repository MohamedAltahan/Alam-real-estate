<?php

namespace Tests\Feature\Site;

use App\Models\Area;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageAreaCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_area_card_uses_the_current_property_count_instead_of_stored_content(): void
    {
        $area = Area::create(['name' => ['ar' => 'مدينة الكويت', 'en' => 'Kuwait City']]);

        foreach (range(1, 3) as $number) {
            Property::create([
                'reference_code' => 'AREA-'.$number,
                'title' => ['ar' => 'عقار '.$number, 'en' => 'Property '.$number],
                'area_id' => $area->id,
            ]);
        }

        $page = Page::create(['slug' => 'home', 'name' => 'Home']);
        PageSection::create([
            'page_id' => $page->id,
            'key' => 'areas',
            'content' => [
                'ar' => [
                    'title' => 'أفضل المناطق',
                    'items' => [['area_id' => $area->id, 'count' => '99']],
                ],
                'en' => [
                    'title' => 'Best Areas',
                    'items' => [['area_id' => $area->id, 'count' => '99']],
                ],
            ],
        ]);

        $response = $this->get(route('site.home'));

        $response->assertOk()
            ->assertViewHas('areas', fn ($areas) => $areas->get($area->id)?->properties_count === 3);

        $this->assertMatchesRegularExpression('/>3\s*\x{0639}\x{0642}\x{0627}\x{0631}</u', $response->getContent());
        $response->assertDontSee('99 عقار');
    }
}
