<?php

namespace Tests\Feature\Site;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** قسم «العقارات المميزة» في الصفحة الرئيسية — المعلَّم عليها «عقار مميّز» من لوحة التحكم */
class HomepageFeaturedPropertiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_featured_properties_are_listed_with_the_listing_card(): void
    {
        $featured = Property::create(['reference_code' => '701', 'title' => ['ar' => 'فيلا مميزة', 'en' => 'Featured villa'], 'is_featured' => true]);
        Property::create(['reference_code' => '702', 'title' => ['ar' => 'شقة عادية', 'en' => 'Plain flat']]);

        $this->get(route('site.home'))->assertOk()
            ->assertViewHas('featuredProperties', fn ($list) => $list->map(fn ($p) => (int) $p->id)->all() === [(int) $featured->id])
            ->assertSee('id="featured-properties"', false)
            ->assertSee('العقارات المميزة')
            ->assertSee('فيلا مميزة')
            ->assertDontSee('شقة عادية')
            // الكارت نفسه المستخدم في صفحة العقارات
            ->assertSee('data-property-url="'.route('site.property', $featured).'"', false);
    }

    public function test_cms_header_replaces_the_default_title(): void
    {
        Property::create(['reference_code' => '703', 'title' => ['ar' => 'فيلا', 'en' => 'Villa'], 'is_featured' => true]);
        $this->section(['ar' => ['title' => 'مختارات علم'], 'en' => ['title' => 'Alam picks']]);

        $this->get(route('site.home'))->assertOk()
            ->assertSee('مختارات علم')
            ->assertDontSee('العقارات المميزة');
    }

    public function test_section_is_absent_without_featured_properties_or_when_hidden(): void
    {
        $this->get(route('site.home'))->assertOk()->assertDontSee('id="featured-properties"', false);

        Property::create(['reference_code' => '704', 'title' => ['ar' => 'فيلا', 'en' => 'Villa'], 'is_featured' => true]);
        $this->section(['ar' => [], 'en' => []])->update(['is_visible' => false]);

        $this->get(route('site.home'))->assertOk()
            ->assertViewHas('featuredProperties', fn ($list) => $list->isEmpty())
            ->assertDontSee('id="featured-properties"', false);
    }

    private function section(array $content): PageSection
    {
        $page = Page::create(['slug' => 'home', 'name' => 'Home']);

        return PageSection::create(['page_id' => $page->id, 'key' => 'featured_properties', 'content' => $content]);
    }
}
