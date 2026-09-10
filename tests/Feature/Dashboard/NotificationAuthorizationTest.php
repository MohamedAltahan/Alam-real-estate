<?php

namespace Tests\Feature\Dashboard;

use App\Models\ContactRequest;
use App\Models\User;
use App\Support\NotificationFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NotificationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_bell_is_hidden_without_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.profile.edit'))
            ->assertOk()
            ->assertDontSee('aria-label="الإشعارات"', false);

        $user->givePermissionTo(Permission::where('name', 'notifications.view')->firstOrFail());

        $this->actingAs($user)
            ->get(route('dashboard.profile.edit'))
            ->assertOk()
            ->assertSee('aria-label="الإشعارات"', false);
    }

    public function test_marking_notifications_read_requires_edit_permission(): void
    {
        $user = $this->userWith(['notifications.view', 'contact_requests.view']);
        $request = ContactRequest::create(['name' => 'عميل جديد', 'phone' => '99999999']);

        $this->actingAs($user)
            ->post(route('dashboard.notifications.read-all'))
            ->assertForbidden();

        $this->assertSame(1, NotificationFeed::unreadCount($user));
        $this->assertDatabaseMissing('contact_request_reads', ['contact_request_id' => $request->id, 'user_id' => $user->id]);

        $user->givePermissionTo(Permission::where('name', 'notifications.edit')->firstOrFail());

        $this->actingAs($user)
            ->post(route('dashboard.notifications.read-all'))
            ->assertRedirect();

        $this->assertDatabaseHas('contact_request_reads', ['contact_request_id' => $request->id, 'user_id' => $user->id]);
        $this->assertSame(0, NotificationFeed::unreadCount($user->refresh()));
        // العمود العام القديم لا يُلمس — القراءة صارت لكل مستخدم
        $this->assertFalse((bool) $request->refresh()->is_read);
    }

    public function test_read_state_is_per_user_and_contacted_counts_for_everyone(): void
    {
        $alice = $this->userWith(['notifications.view', 'contact_requests.view']);
        $bob = $this->userWith(['notifications.view', 'contact_requests.view', 'contact_requests.edit']);
        $request = ContactRequest::create(['name' => 'عميل جديد', 'phone' => '99999999']);

        $this->assertSame(1, NotificationFeed::unreadCount($alice));
        $this->assertSame(1, NotificationFeed::unreadCount($bob));

        // قراءة أليس لا تُخفي التنبيه عن بوب
        $this->actingAs($alice)->post(route('dashboard.notifications.read-mine'))->assertRedirect();

        $this->assertSame(0, NotificationFeed::unreadCount($alice->refresh()));
        $this->assertSame(1, NotificationFeed::unreadCount($bob->refresh()));

        $this->actingAs($bob)->get(route('dashboard.requests.index'))->assertOk()
            ->assertSee('ring-1 ring-primary-200', false)
            ->assertSee('1 غير مقروء');
        $this->actingAs($alice)->get(route('dashboard.requests.index'))->assertOk()
            ->assertDontSee('ring-1 ring-primary-200', false)
            ->assertSee('0 غير مقروء');

        // «تم التواصل» = مقروء للجميع
        $this->actingAs($bob)->put(route('dashboard.requests.contacted', $request))->assertRedirect();

        $this->assertSame(0, NotificationFeed::unreadCount($bob->refresh()));
        $this->assertSame(0, NotificationFeed::unreadCount($alice->refresh()));
        $this->assertFalse(NotificationFeed::items($alice)->firstWhere('kind', 'request')['unread']);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        return $user;
    }
}
