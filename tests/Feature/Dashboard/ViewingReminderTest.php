<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\PropertyStatus;
use App\Models\User;
use App\Notifications\ViewingReminder;
use App\Support\NotificationFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ViewingReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->create(['is_agent' => true]);
        $this->agent->givePermissionTo(Permission::where('name', 'notifications.view')->firstOrFail());

        $status = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
        $this->property = Property::create(['reference_code' => '701', 'title' => ['ar' => 'عقار', 'en' => 'Property'], 'status_id' => $status->id]);
    }

    public function test_poll_creates_a_reminder_once_when_the_viewing_is_within_lead_time(): void
    {
        $viewing = $this->viewing(now()->addMinutes(30), agentId: $this->agent->id);

        $response = $this->actingAs($this->agent)->getJson(route('dashboard.notifications.poll'))->assertOk();

        $response->assertJsonPath('viewing_unread', 1)
            ->assertJsonPath('items.0.kind', 'viewing')
            ->assertJsonPath('items.0.read', false);

        $this->assertNotNull($viewing->refresh()->reminded_at);
        $this->assertSame(1, $this->agent->notifications()->where('type', ViewingReminder::class)->count());
        $this->assertStringContainsString('701', $this->agent->notifications()->first()->data['title']);

        // استطلاع ثانٍ لا يكرّر التذكير
        $this->actingAs($this->agent)->getJson(route('dashboard.notifications.poll'))->assertOk();
        $this->assertSame(1, $this->agent->notifications()->count());
    }

    public function test_reminder_respects_the_user_lead_time_setting(): void
    {
        $this->agent->update(['preferences' => ['viewing_reminders' => ['lead_minutes' => 15, 'repeat_beep' => false, 'enabled' => true]]]);
        $viewing = $this->viewing(now()->addMinutes(30), agentId: $this->agent->id);

        $this->actingAs($this->agent)->getJson(route('dashboard.notifications.poll'))
            ->assertOk()
            ->assertJsonPath('viewing_unread', 0)
            ->assertJsonPath('repeat_beep', false);

        $this->assertNull($viewing->refresh()->reminded_at);

        $this->agent->update(['preferences' => ['viewing_reminders' => ['lead_minutes' => 120, 'repeat_beep' => true, 'enabled' => true]]]);

        $this->actingAs($this->agent->refresh())->getJson(route('dashboard.notifications.poll'))
            ->assertOk()
            ->assertJsonPath('viewing_unread', 1)
            ->assertJsonPath('repeat_beep', true);
    }

    public function test_stale_or_decided_viewings_never_fire(): void
    {
        $this->viewing(now()->subDays(2), agentId: $this->agent->id);
        $decided = $this->viewing(now()->addMinutes(20), agentId: $this->agent->id);
        $decided->update(['outcome' => ClientViewing::OUTCOME_CHOSEN]);

        $this->actingAs($this->agent)->getJson(route('dashboard.notifications.poll'))->assertOk();

        $this->assertSame(0, $this->agent->notifications()->count());
    }

    public function test_reminder_falls_back_to_property_agent_then_recorder(): void
    {
        $propertyAgent = User::factory()->create();
        $recorder = User::factory()->create();
        $this->property->update(['agent_id' => $propertyAgent->id]);

        $this->viewing(now()->addMinutes(10), agentId: null, recordedBy: $recorder->id);

        $this->actingAs($this->agent)->getJson(route('dashboard.notifications.poll'))->assertOk();
        $this->assertSame(1, $propertyAgent->notifications()->count());
        $this->assertSame(0, $recorder->notifications()->count());

        $this->property->update(['agent_id' => null]);
        $this->viewing(now()->addMinutes(12), agentId: null, recordedBy: $recorder->id, phone: '55000009');

        $this->actingAs($this->agent)->getJson(route('dashboard.notifications.poll'))->assertOk();
        $this->assertSame(1, $recorder->notifications()->count());
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_the_viewings_page(): void
    {
        $this->viewing(now()->addMinutes(30), agentId: $this->agent->id);
        $this->actingAs($this->agent)->getJson(route('dashboard.notifications.poll'))->assertOk();
        $notification = $this->agent->notifications()->firstOrFail();

        // مسار نسبي حتى لا يتجمّد الرابط على المضيف الذي أُنشئ منه الإشعار
        $this->assertSame(route('dashboard.viewings.index', absolute: false), $notification->data['url']);

        $this->actingAs($this->agent)->get(route('dashboard.notifications.open', $notification->id))
            ->assertRedirect(route('dashboard.viewings.index'));

        $this->assertNotNull($notification->refresh()->read_at);
        $this->assertSame(0, $this->agent->unreadNotifications()->count());

        $stranger = User::factory()->create();
        $stranger->givePermissionTo(Permission::where('name', 'notifications.view')->firstOrFail());
        $this->actingAs($stranger)->get(route('dashboard.notifications.open', $notification->id))->assertNotFound();
    }

    public function test_reminder_text_is_recomputed_at_display_time(): void
    {
        $this->viewing(now()->addMinutes(30), agentId: $this->agent->id);
        $this->actingAs($this->agent)->getJson(route('dashboard.notifications.poll'))->assertOk();

        $stored = $this->agent->notifications()->firstOrFail()->data['title'];
        $this->assertStringContainsString('بعد', $stored);
        $this->assertStringNotContainsString('فات موعدها', $stored);

        // بعد مرور الموعد يتغيّر النص المعروض رغم بقاء المحفوظ كما هو
        $this->travel(3)->hours();

        $item = NotificationFeed::items($this->agent->fresh())->firstWhere('kind', 'viewing');

        $this->assertStringContainsString('فات موعدها', $item['title']);
        $this->assertTrue($item['overdue']);
        $this->assertSame($stored, $this->agent->notifications()->firstOrFail()->data['title']);

        $this->actingAs($this->agent)->getJson(route('dashboard.notifications.poll'))
            ->assertOk()
            ->assertJsonPath('items.0.overdue', true);
    }

    public function test_poll_requires_the_notifications_permission(): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('dashboard.notifications.poll'))->assertForbidden();
    }

    private function viewing($at, ?int $agentId, ?int $recordedBy = null, string $phone = '55000001'): ClientViewing
    {
        $client = Client::create(['name' => 'عميل المعاينة', 'phone_code' => '+965', 'phone' => $phone, 'agent_id' => $agentId, 'recorded_by' => $recordedBy]);

        return $client->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => $at, 'in_person' => true]);
    }
}
