<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** «طلب مميز»: تشيك بوكس في فورم العميل + تبويب الطلبات المميزة في شاشة طلبات التواصل */
class FeaturedRequestsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        foreach (['contact_requests.view', 'clients.view', 'clients.create', 'clients.edit'] as $name) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }
    }

    public function test_store_marks_client_as_featured_when_checkbox_is_checked(): void
    {
        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل مميز', 'phone_code' => '+965', 'phone' => '55112233', 'is_featured' => '1',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل عادي', 'phone_code' => '+965', 'phone' => '55112244', 'is_featured' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Client::where('name', 'عميل مميز')->first()->is_featured);
        $this->assertFalse(Client::where('name', 'عميل عادي')->first()->is_featured);
    }

    public function test_update_can_toggle_featured_off(): void
    {
        $client = Client::create(['name' => 'عميل', 'phone_code' => '+965', 'phone' => '55112233', 'is_featured' => true]);

        $this->actingAs($this->user)->put(route('dashboard.clients.update', $client), [
            'name' => 'عميل', 'phone_code' => '+965', 'phone' => '55112233', 'is_featured' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($client->refresh()->is_featured);
        $this->assertDatabaseHas('client_audit_logs', ['client_id' => $client->id, 'action' => 'updated']);
    }

    public function test_featured_tab_lists_only_featured_clients_with_basic_filters(): void
    {
        $stage = ClientStage::create(['name' => ['ar' => 'جديد', 'en' => 'New'], 'key' => 'new', 'color' => '#111111', 'sort_order' => 1]);
        $agent = User::factory()->create(['name' => 'مندوب أ', 'is_agent' => true]);

        Client::create(['name' => 'أحمد المميز', 'phone_code' => '+965', 'phone' => '55110001', 'is_featured' => true, 'stage_id' => $stage->id, 'agent_id' => $agent->id]);
        Client::create(['name' => 'سارة المميزة', 'phone_code' => '+965', 'phone' => '55110002', 'is_featured' => true]);
        Client::create(['name' => 'خالد العادي', 'phone_code' => '+965', 'phone' => '55110003', 'is_featured' => false]);

        $this->actingAs($this->user)
            ->get(route('dashboard.requests.featured'))
            ->assertOk()
            ->assertSee('أحمد المميز')
            ->assertSee('سارة المميزة')
            ->assertDontSee('خالد العادي');

        $this->actingAs($this->user)
            ->get(route('dashboard.requests.featured', ['search' => 'سارة']))
            ->assertOk()
            ->assertSee('سارة المميزة')
            ->assertDontSee('أحمد المميز');

        $this->actingAs($this->user)
            ->get(route('dashboard.requests.featured', ['agent_id' => $agent->id]))
            ->assertOk()
            ->assertSee('أحمد المميز')
            ->assertDontSee('سارة المميزة');

        $this->actingAs($this->user)
            ->get(route('dashboard.requests.featured', ['stage_id' => $stage->id]))
            ->assertOk()
            ->assertSee('أحمد المميز')
            ->assertDontSee('سارة المميزة');
    }

    public function test_inbox_shows_tabs_with_featured_count(): void
    {
        Client::create(['name' => 'عميل مميز', 'phone_code' => '+965', 'phone' => '55110001', 'is_featured' => true]);

        $this->actingAs($this->user)
            ->get(route('dashboard.requests.index'))
            ->assertOk()
            ->assertSee('الطلبات المميزة')
            ->assertSee(route('dashboard.requests.featured'), false);
    }

    public function test_featured_tab_requires_contact_requests_permission(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('dashboard.requests.featured'))
            ->assertForbidden();
    }
}
