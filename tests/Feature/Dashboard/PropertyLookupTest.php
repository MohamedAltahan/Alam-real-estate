<?php

namespace Tests\Feature\Dashboard;

use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** حقل «العقار المرتبط» في المهام والمعاينات: الرقم أو الاسم أو المبنى أو المالك */
class PropertyLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        foreach (['tasks.view', 'clients.view'] as $name) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $owner = PropertyOwner::create(['name' => 'فهد العتيبي', 'phone_code' => '+965', 'phone' => '55700001']);
        Property::create(['reference_code' => '288', 'title' => ['ar' => 'شقة غرفتين', 'en' => 'Two-bed flat'], 'building_name' => 'برج سما', 'owner_id' => $owner->id]);
        Property::create(['reference_code' => '415', 'title' => ['ar' => 'دور أرضي', 'en' => 'Ground floor']]);
    }

    /** @return array<int, string> الأرقام المرجعية في نتائج البحث */
    private function lookup(string $route, string $q): array
    {
        return collect($this->actingAs($this->user)->getJson(route($route, ['q' => $q]))->assertOk()->json())
            ->pluck('reference_code')->all();
    }

    public function test_task_property_lookup_returns_results(): void
    {
        // كان يُرسل حداً صفرياً للنتائج فلا يرجع شيئاً أبداً
        $this->assertSame(['288'], $this->lookup('dashboard.tasks.property-lookup', 'برج سما'));
        $this->assertSame(['415'], $this->lookup('dashboard.tasks.property-lookup', '415'));
        $this->assertSame(['415'], $this->lookup('dashboard.tasks.property-lookup', 'أرضي'));
        $this->assertCount(2, $this->lookup('dashboard.tasks.property-lookup', ''));
    }

    public function test_lookup_matches_owner_name_in_tasks_and_viewings(): void
    {
        $this->assertSame(['288'], $this->lookup('dashboard.tasks.property-lookup', 'العتيبي'));
        $this->assertSame(['288'], $this->lookup('dashboard.clients.property-lookup', 'فهد'));
    }

    public function test_task_dropdown_shows_building_and_owner_like_viewings(): void
    {
        $item = $this->actingAs($this->user)->getJson(route('dashboard.tasks.property-lookup', ['q' => '288']))->json('0');
        $this->assertSame('مبنى: برج سما · المالك: فهد العتيبي', $item['building']);

        // قالب القائمة في نافذة المهمة (تظهر لمن يملك إضافة المهام) يعرض حقل building
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'tasks.create', 'guard_name' => 'web']));
        $this->actingAs($this->user)->get(route('dashboard.tasks.index'))
            ->assertOk()
            ->assertSee("x-text=\"'— ' + item.building\"", false);
    }
}
