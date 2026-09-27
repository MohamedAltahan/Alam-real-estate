<?php

namespace Tests\Feature\Site;

use App\Models\Area;
use App\Models\City;
use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** تحويل بنود «أفضل المناطق» المحفوظة إلى «المحافظات» على البيانات القائمة */
class HomeGovernoratesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_items_become_their_governorates_once_and_the_copy_is_renamed(): void
    {
        $capital = City::create(['name' => ['ar' => 'محافظة العاصمة', 'en' => 'Capital']]);
        $hawalli = City::create(['name' => ['ar' => 'محافظة حولي', 'en' => 'Hawalli']]);
        $salmiya = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $hawalli->id]);
        $sharq = Area::create(['name' => ['ar' => 'شرق', 'en' => 'Sharq'], 'city_id' => $capital->id]);
        $jabriya = Area::create(['name' => ['ar' => 'الجابرية', 'en' => 'Jabriya'], 'city_id' => $hawalli->id]);

        $items = [
            ['area_id' => (string) $salmiya->id, 'count' => '٣٢', 'collection' => 'area-1'],
            ['area_id' => (string) $sharq->id, 'collection' => 'area-2'],
            ['area_id' => (string) $jabriya->id, 'collection' => 'area-3'],   // محافظة حولي مكررة
        ];
        $page = Page::create(['slug' => 'home', 'name' => 'الرئيسية']);
        PageSection::create(['page_id' => $page->id, 'key' => 'areas', 'content' => [
            'ar' => ['items' => $items, 'title' => 'أفضل المناطق', 'description' => 'استعرض العقارات حسب أكثر المناطق طلبًا في الكويت.'],
            'en' => ['items' => $items, 'title' => 'Top Areas', 'description' => 'Our own words'],
        ]]);

        $this->runMigration();
        $this->runMigration();   // إعادة التشغيل لا تغيّر شيئاً

        $expected = [
            ['city_id' => (string) $hawalli->id, 'collection' => 'area-1'],
            ['city_id' => (string) $capital->id, 'collection' => 'area-2'],
        ];
        $section = PageSection::where('key', 'areas')->firstOrFail();
        $ar = $section->getTranslation('content', 'ar');
        $en = $section->getTranslation('content', 'en');

        $this->assertSame($expected, $ar['items']);
        $this->assertSame($expected, $en['items']);
        $this->assertSame('المحافظات', $ar['title']);
        $this->assertSame('Governorates', $en['title']);
        // الوصف الافتراضي القديم يُستبدل، والمكتوب يدوياً يبقى
        $this->assertSame('استعرض العقارات المتاحة في كل محافظة من محافظات الكويت.', $ar['description']);
        $this->assertSame('Our own words', $en['description']);

        $featured = PageSection::where('key', 'featured_properties')->sole();
        $this->assertTrue($featured->is_visible);
        $this->assertSame('العقارات المميزة', $featured->getTranslation('content', 'ar')['title']);
        $this->assertSame('Featured Properties', $featured->getTranslation('content', 'en')['title']);
    }

    public function test_it_does_nothing_without_a_home_page(): void
    {
        $this->runMigration();

        $this->assertSame(0, PageSection::count());
    }

    private function runMigration(): void
    {
        (require database_path('migrations/2026_09_27_000001_convert_home_areas_to_governorates.php'))->up();
    }
}
