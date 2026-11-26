<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use App\Support\Watermark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WatermarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_sidebar_shows_the_watermark_tab_only_with_permission(): void
    {
        $viewer = $this->userWith(['watermark.view']);
        $this->actingAs($viewer)->get(route('dashboard.watermark.index'))
            ->assertOk()
            ->assertSee('واتر مارك')
            ->assertSee(route('dashboard.watermark.index'), false);

        $stranger = $this->userWith(['properties.view', 'properties.view_commission']);
        $this->actingAs($stranger)->get(route('dashboard.watermark.index'))->assertForbidden();
        $this->actingAs($stranger)->get(route('dashboard.properties.index'))
            ->assertOk()
            ->assertDontSee(route('dashboard.watermark.index'), false);
    }

    public function test_view_only_user_cannot_change_settings(): void
    {
        $viewer = $this->userWith(['watermark.view']);

        $this->actingAs($viewer)->put(route('dashboard.watermark.update'), [
            'enabled' => 1, 'position' => 'center', 'size' => 'small',
        ])->assertForbidden();

        $this->actingAs($viewer)->postJson(route('dashboard.watermark.apply'))->assertForbidden();
    }

    public function test_editor_can_upload_logo_and_choose_position_and_size(): void
    {
        $editor = $this->userWith(['watermark.view', 'watermark.edit']);

        $this->actingAs($editor)->put(route('dashboard.watermark.update'), [
            'enabled' => 1,
            'position' => 'top-left',
            'size' => 'small',
            'logo' => $this->logoUpload(),
        ])->assertRedirect(route('dashboard.watermark.index'))->assertSessionHasNoErrors();

        $config = Watermark::settings();
        $this->assertTrue($config['enabled']);
        $this->assertSame('top-left', $config['position']);
        $this->assertSame('small', $config['size']);
        Storage::disk('public')->assertExists($config['path']);

        // استبدال الشعار يحذف القديم من القرص
        $oldPath = $config['path'];
        $this->actingAs($editor)->put(route('dashboard.watermark.update'), [
            'enabled' => 1, 'position' => 'top-left', 'size' => 'small', 'logo' => $this->logoUpload(),
        ])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_logo_validation(): void
    {
        $editor = $this->userWith(['watermark.view', 'watermark.edit']);

        // JPG بدون شفافية مرفوض
        $this->actingAs($editor)->put(route('dashboard.watermark.update'), [
            'enabled' => 1, 'position' => 'center', 'size' => 'medium',
            'logo' => UploadedFile::fake()->image('logo.jpg', 900, 300),
        ])->assertSessionHasErrors('logo');

        // صغير جداً
        $this->actingAs($editor)->put(route('dashboard.watermark.update'), [
            'enabled' => 1, 'position' => 'center', 'size' => 'medium',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 60),
        ])->assertSessionHasErrors('logo');

        // تفعيل بدون شعار
        $this->actingAs($editor)->put(route('dashboard.watermark.update'), [
            'enabled' => 1, 'position' => 'center', 'size' => 'medium',
        ])->assertSessionHasErrors('logo');

        // مكان أو حجم غير معروف
        $this->actingAs($editor)->put(route('dashboard.watermark.update'), [
            'position' => 'somewhere', 'size' => 'huge',
        ])->assertSessionHasErrors(['position', 'size']);
    }

    public function test_property_images_get_the_watermark_at_the_chosen_corner(): void
    {
        $this->saveWatermark(enabled: true, position: 'bottom-right', size: 'large');

        $media = $this->propertyImage();

        [$width, $height] = getimagesize($media->getPath('web'));
        $this->assertSame([1600, 900], [$width, $height]);

        // كبير = ٣٥٪ من العرض (٥٦٠px) · مسافة ٣٪ → المنطقة تقريباً x: 992–1552 · y: 733–873
        $this->assertWatermarked($media, 1300, 800);
        $this->assertPlain($media, 100, 100);
        $this->assertPlain($media, 1300, 100);

        // الأصل يبقى بدون علامة
        $this->assertPlainAt($media->getPath(), 1300, 800);
    }

    public function test_position_and_size_follow_the_settings(): void
    {
        $this->saveWatermark(enabled: true, position: 'top-left', size: 'small');

        $media = $this->propertyImage();

        // صغير = ١٥٪ (٢٤٠px) · مسافة ٣٪ → x: 48–288 · y: 27–87
        $this->assertWatermarked($media, 150, 55);
        $this->assertPlain($media, 400, 55);
        $this->assertPlain($media, 1300, 800);
    }

    public function test_disabled_watermark_and_other_models_are_left_alone(): void
    {
        $this->saveWatermark(enabled: false, position: 'bottom-right', size: 'large');
        $this->assertPlain($this->propertyImage(), 1300, 800);

        // صور الموديلات الأخرى (مثل صور المستخدمين) لا تأخذ العلامة أبداً
        $this->saveWatermark(enabled: true, position: 'bottom-right', size: 'large');
        $user = User::factory()->create();
        $avatar = $user->addMedia($this->plainImagePath())->toMediaCollection('avatar');
        $this->assertPlainAt($avatar->getPath('web'), 1300, 800);
    }

    public function test_existing_images_can_be_regenerated_in_batches(): void
    {
        $editor = $this->userWith(['watermark.view', 'watermark.edit']);

        // صور قديمة اترفعت والعلامة متوقفة
        $this->saveWatermark(enabled: false, position: 'bottom-right', size: 'large');
        $property = $this->makeProperty();
        $images = collect(range(1, 7))->map(fn () => $property->addMedia($this->plainImagePath())->toMediaCollection('gallery'));
        $this->assertPlain($images->first(), 1300, 800);

        $this->saveWatermark(enabled: true, position: 'bottom-right', size: 'large');

        $first = $this->actingAs($editor)->postJson(route('dashboard.watermark.apply'), ['after' => 0])
            ->assertOk()
            ->assertJson(['processed' => 5, 'failed' => 0, 'remaining' => 2, 'done' => false]);

        $this->actingAs($editor)->postJson(route('dashboard.watermark.apply'), ['after' => $first->json('next')])
            ->assertOk()
            ->assertJson(['processed' => 2, 'failed' => 0, 'remaining' => 0, 'done' => true]);

        $images->each(fn (Media $media) => $this->assertWatermarked($media->fresh(), 1300, 800));
    }

    // ===== Helpers =====

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $user;
    }

    private function saveWatermark(bool $enabled, string $position, string $size): void
    {
        $path = Watermark::settings()['path'];
        if (! $path) {
            $path = Watermark::DIR.'/logo.png';
            Storage::disk('public')->put($path, $this->pngBytes(800, 200, [255, 0, 0]));
        }

        Watermark::save(compact('enabled', 'path', 'position', 'size'));
    }

    private function makeProperty(): Property
    {
        $area = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya']]);
        $category = PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential']);
        $unitType = UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment'], 'category' => 'residential']);
        $status = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);

        return Property::create([
            'reference_code' => (string) random_int(1000, 9999),
            'title' => ['ar' => 'عقار', 'en' => 'Property'],
            'area_id' => $area->id,
            'category_id' => $category->id,
            'unit_type_id' => $unitType->id,
            'status_id' => $status->id,
            'purpose' => 'sale',
            'price' => 100000,
        ]);
    }

    private function propertyImage(): Media
    {
        return $this->makeProperty()->addMedia($this->plainImagePath())->toMediaCollection('gallery');
    }

    /** صورة عقار زرقاء بالكامل 1600×900 (PNG حتى لا تشوّش ضغطة JPG على قراءة البكسل) */
    private function plainImagePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wm').'.png';
        file_put_contents($path, $this->pngBytes(1600, 900, [0, 0, 255]));

        return $path;
    }

    private function logoUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
        file_put_contents($path, $this->pngBytes(800, 200, [255, 0, 0]));

        return new UploadedFile($path, 'logo.png', 'image/png', null, true);
    }

    private function pngBytes(int $width, int $height, array $rgb): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    private function pixel(string $path, int $x, int $y): array
    {
        $image = imagecreatefromstring(file_get_contents($path));
        $rgb = imagecolorat($image, $x, $y);

        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }

    /** أحمر الشعار بشفافية ٧٠٪ فوق الأزرق: R≈178 · B≈76 */
    private function assertWatermarked(Media $media, int $x, int $y): void
    {
        [$r, , $b] = $this->pixel($media->getPath('web'), $x, $y);
        $this->assertGreaterThan(150, $r, "Expected watermark at ({$x},{$y}), got R={$r} B={$b}");
        $this->assertLessThan(120, $b, "Expected watermark at ({$x},{$y}), got R={$r} B={$b}");
    }

    private function assertPlain(Media $media, int $x, int $y): void
    {
        $this->assertPlainAt($media->getPath('web'), $x, $y);
    }

    private function assertPlainAt(string $path, int $x, int $y): void
    {
        $this->assertSame([0, 0, 255], $this->pixel($path, $x, $y), "Expected no watermark at ({$x},{$y})");
    }
}
