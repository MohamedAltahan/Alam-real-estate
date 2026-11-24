<?php

namespace Tests\Feature\Dashboard;

use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** فلتر التصنيف (سكني / تجاري) في شاشة العقارات */
class PropertyCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_properties_screen_filters_by_category(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'properties.view', 'guard_name' => 'web']));

        $residential = PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential', 'is_active' => true]);
        $commercial = PropertyCategory::create(['name' => ['ar' => 'تجاري', 'en' => 'Commercial'], 'key' => 'commercial', 'is_active' => true]);
        UnitType::create(['name' => ['ar' => 'محل', 'en' => 'Shop'], 'category' => 'commercial', 'is_active' => true]);

        Property::create(['reference_code' => '901', 'title' => ['ar' => 'شقة عائلية', 'en' => 'Family flat'], 'category_id' => $residential->id]);
        Property::create(['reference_code' => '902', 'title' => ['ar' => 'محل تجاري', 'en' => 'Shop unit'], 'category_id' => $commercial->id]);

        $index = fn (array $q) => $this->actingAs($user)->get(route('dashboard.properties.index', $q))->assertOk();

        // القائمة تعرض التصنيفات، وأنواع الوحدات تُمرَّر بتصنيفها لتتبع الاختيار
        $index([])
            ->assertSee('name="category_id"', false)
            ->assertSee('كل التصنيفات')
            ->assertSee("fits('commercial')", false);

        $index(['category_id' => $commercial->id])->assertSee('محل تجاري')->assertDontSee('شقة عائلية');
        $index(['category_id' => $residential->id])->assertSee('شقة عائلية')->assertDontSee('محل تجاري');
    }
}
