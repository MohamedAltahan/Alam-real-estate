<?php

namespace Tests\Feature\Site;

use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** أسهم التنقل: على جانبي شريط العقارات المميزة، وبين صور كل عقار داخل الكارت */
class PropertyCardArrowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_featured_carousel_has_side_arrows_and_cards_have_photo_arrows(): void
    {
        $withPhotos = $this->featuredProperty('401', photos: 3);
        $this->featuredProperty('402', photos: 1);

        $html = $this->get(route('site.home'))->assertOk()->getContent();

        // سهما الشريط على الجانبين (لم يعودا في سطر العنوان)
        $this->assertStringContainsString('@click="nav(-1)" aria-label="السابق"', $html);
        $this->assertStringContainsString('@click="nav(1)" aria-label="التالي"', $html);

        // سهما الصور يظهران فقط للعقار الذي له أكثر من صورة
        $this->assertSame(1, substr_count($html, 'aria-label="الصورة التالية"'));
        $this->assertSame(1, substr_count($html, 'aria-label="الصورة السابقة"'));
        $this->assertStringContainsString('i = (i + 1) % n', $html);
        $this->assertStringContainsString($withPhotos->reference_code, $html);
    }

    public function test_share_buttons_are_gone_from_cards_and_the_property_page(): void
    {
        $property = $this->featuredProperty('403', photos: 2);

        $this->get(route('site.home'))->assertOk()->assertDontSee('aria-label="مشاركة"', false);
        $this->get(route('site.properties'))->assertOk()->assertDontSee('aria-label="مشاركة"', false);
        $this->get(route('site.property', $property))->assertOk()->assertDontSee('مشاركة');
    }

    private function featuredProperty(string $ref, int $photos): Property
    {
        $property = Property::create([
            'reference_code' => $ref,
            'title' => ['ar' => 'عقار '.$ref, 'en' => 'Property '.$ref],
            'is_featured' => true,
        ]);

        foreach (range(1, $photos) as $k) {
            $path = tempnam(sys_get_temp_dir(), 'card').'.png';
            $image = imagecreatetruecolor(400, 300);
            imagepng($image, $path);
            $property->addMedia($path)->toMediaCollection($k === 1 ? 'cover' : 'gallery');
        }

        return $property;
    }
}
