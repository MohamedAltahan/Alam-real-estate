<?php

namespace Tests\Feature\Site;

use App\Models\Area;
use App\Models\City;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** قسم «المحافظات» في الصفحة الرئيسية (كان «أفضل المناطق») وفلتر المحافظة في صفحة العقارات */
class HomepageGovernoratesTest extends TestCase
{
    use RefreshDatabase;

    private City $capital;

    private City $hawalli;

    private Area $salmiya;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capital = City::create(['name' => ['ar' => 'محافظة العاصمة', 'en' => 'Capital Governorate']]);
        $this->hawalli = City::create(['name' => ['ar' => 'محافظة حولي', 'en' => 'Hawalli Governorate']]);

        // عقاران في منطقتين مختلفتين من العاصمة + عقار في حولي
        $sharq = Area::create(['name' => ['ar' => 'شرق', 'en' => 'Sharq'], 'city_id' => $this->capital->id]);
        $dasma = Area::create(['name' => ['ar' => 'الدسمة', 'en' => 'Dasma'], 'city_id' => $this->capital->id]);
        $this->salmiya = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $this->hawalli->id]);

        $this->property('CAP-1', 'شقة شرق', $sharq);
        $this->property('CAP-2', 'فيلا الدسمة', $dasma);
        $this->property('HAW-1', 'شقة السالمية', $this->salmiya);
    }

    public function test_each_card_counts_the_whole_governorate_live_and_opens_its_listing(): void
    {
        $this->homeSection(['items' => [['city_id' => (string) $this->capital->id, 'count' => '99']]]);

        $response = $this->get(route('site.home'))->assertOk();

        $response->assertViewHas('governorates', fn ($cards) => $cards->count() === 1
                && (int) $cards->first()['city']->properties_count === 2)
            ->assertSee('id="governorates"', false)
            ->assertSee('المحافظات')
            ->assertSee('محافظة العاصمة')
            ->assertSee('href="'.route('site.properties', ['city' => $this->capital->id]).'"', false)
            ->assertSee('href="'.route('site.home').'#governorates"', false)   // رابط الفوتر
            ->assertDontSee('99 عقار');

        $this->assertMatchesRegularExpression('/>2\s*\x{0639}\x{0642}\x{0627}\x{0631}</u', $response->getContent());
    }

    public function test_hidden_section_and_its_footer_link_are_not_rendered(): void
    {
        $this->homeSection(['items' => [['city_id' => (string) $this->capital->id]]])
            ->update(['is_visible' => false]);

        $this->get(route('site.home'))->assertOk()
            ->assertViewHas('governorates', fn ($cards) => $cards->isEmpty())
            ->assertDontSee('id="governorates"', false)
            ->assertDontSee('#governorates', false);
    }

    public function test_inactive_unknown_and_legacy_area_items_are_skipped(): void
    {
        $this->hawalli->update(['is_active' => false]);
        $this->homeSection(['items' => [
            ['city_id' => (string) $this->hawalli->id],
            ['city_id' => '999'],
            ['area_id' => (string) $this->salmiya->id],
        ]]);

        $this->get(route('site.home'))->assertOk()
            ->assertViewHas('governorates', fn ($cards) => $cards->isEmpty())
            ->assertDontSee('id="governorates"', false);
    }

    public function test_listing_filters_by_governorate_and_narrows_the_area_filter(): void
    {
        $this->get(route('site.properties', ['city' => $this->capital->id]))->assertOk()
            ->assertSee('شقة شرق')
            ->assertSee('فيلا الدسمة')
            ->assertDontSee('شقة السالمية')
            ->assertViewHas('areas', fn ($areas) => $areas->count() === 2 && ! $areas->contains('id', $this->salmiya->id))
            // الترتيب يحتفظ بالمحافظة المختارة
            ->assertSee('<input type="hidden" name="city" value="'.$this->capital->id.'">', false);
    }

    private function property(string $ref, string $title, Area $area): Property
    {
        return Property::create([
            'reference_code' => $ref,
            'title' => ['ar' => $title, 'en' => $ref],
            'area_id' => $area->id,
            'city_id' => $area->city_id,
        ]);
    }

    private function homeSection(array $content): PageSection
    {
        $page = Page::create(['slug' => 'home', 'name' => 'Home']);

        return PageSection::create([
            'page_id' => $page->id,
            'key' => 'areas',
            'content' => ['ar' => $content, 'en' => $content],
        ]);
    }
}
