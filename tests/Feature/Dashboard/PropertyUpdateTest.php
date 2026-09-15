<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PropertyUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_can_be_updated_with_a_gallery_image(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        foreach (['properties.view', 'properties.edit'] as $permission) {
            $user->givePermissionTo(Permission::create(['name' => $permission, 'guard_name' => 'web']));
        }

        $area = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya']]);
        $category = PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential']);
        $unitType = UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment'], 'category' => 'residential']);
        $status = PropertyStatus::create([
            'name' => ['ar' => 'متاح', 'en' => 'Available'],
            'key' => 'available',
        ]);
        $property = Property::create([
            'reference_code' => '900',
            'title' => ['ar' => 'عقار قديم', 'en' => 'Old property'],
            'area_id' => $area->id,
            'category_id' => $category->id,
            'unit_type_id' => $unitType->id,
            'status_id' => $status->id,
            'purpose' => 'sale',
            'price' => 100000,
        ]);

        $this->actingAs($user)->put(route('dashboard.properties.update', $property), [
            'title' => ['ar' => 'عقار محدث', 'en' => 'Updated property'],
            'short_description' => ['ar' => '', 'en' => ''],
            'description' => ['ar' => '', 'en' => ''],
            'specifications' => ['ar' => '', 'en' => ''],
            'area_id' => $area->id,
            'category_id' => $category->id,
            'unit_type_id' => $unitType->id,
            'purpose' => 'sale',
            'price' => 125000,
            'status_id' => $status->id,
            'gallery' => [UploadedFile::fake()->image('gallery.jpg', 800, 600)],
            'contacts' => [['phone_code' => '+965', 'phone' => '99887766', 'role' => 'الحارس', 'name' => 'أبو خالد']],
            'amenities' => [],
        ])->assertRedirect(route('dashboard.properties.show', $property))
            ->assertSessionHasNoErrors();

        $property->refresh();
        $this->assertSame('عقار محدث', $property->getTranslation('title', 'ar'));
        $this->assertSame('125000.000', $property->price);
        $this->assertCount(1, $property->getMedia('gallery'));
    }
}
