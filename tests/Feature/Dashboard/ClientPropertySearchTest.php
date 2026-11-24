<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** شاشة العملاء: الفلاتر ظاهرة دائماً + البحث بالعقار أو المبنى المرتبط بالعميل */
class ClientPropertySearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'clients.view', 'guard_name' => 'web']));

        $tower = Property::create(['reference_code' => '701', 'title' => ['ar' => 'شقة إطلالة بحرية', 'en' => 'Sea view'], 'building_name' => 'برج الحمرا']);
        $villa = Property::create(['reference_code' => '702', 'title' => ['ar' => 'فيلا الجابرية', 'en' => 'Jabriya villa']]);

        // مرتبط بالعقار عبر «عقارات العميل»
        Client::create(['name' => 'عميل البرج', 'phone_code' => '+965', 'phone' => '55400001'])
            ->properties()->attach($tower->id, ['relation' => 'interested']);

        // مرتبط بالعقار عبر معاينة فقط
        Client::create(['name' => 'عميل المعاينة', 'phone_code' => '+965', 'phone' => '55400002'])
            ->viewings()->create(['property_id' => $villa->id, 'scheduled_at' => now()->addDay()]);

        Client::create(['name' => 'عميل بلا عقار', 'phone_code' => '+965', 'phone' => '55400003']);
    }

    private function index(array $query = [])
    {
        return $this->actingAs($this->user)->get(route('dashboard.clients.index', $query))->assertOk();
    }

    public function test_filters_panel_is_always_visible_with_no_toggle(): void
    {
        $this->index()
            ->assertSee('name="agent_id"', false)
            ->assertSee('name="property_q"', false)
            ->assertDontSee('filtersOpen', false);
    }

    public function test_search_by_building_name_finds_the_client_linked_to_that_property(): void
    {
        $this->index(['property_q' => 'الحمرا'])
            ->assertSee('عميل البرج')
            ->assertDontSee('عميل المعاينة')
            ->assertDontSee('عميل بلا عقار');
    }

    public function test_search_by_property_name_or_reference_includes_viewed_properties(): void
    {
        $this->index(['property_q' => 'الجابرية'])
            ->assertSee('عميل المعاينة')
            ->assertDontSee('عميل البرج');

        $this->index(['property_q' => '701'])
            ->assertSee('عميل البرج')
            ->assertDontSee('عميل المعاينة');
    }
}
