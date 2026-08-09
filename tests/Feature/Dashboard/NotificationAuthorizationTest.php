<?php

namespace Tests\Feature\Dashboard;

use App\Models\ContactRequest;
use App\Models\User;
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
        $user = User::factory()->create();
        $request = ContactRequest::create(['name' => 'عميل جديد', 'phone' => '99999999']);

        $user->givePermissionTo([
            Permission::where('name', 'notifications.view')->firstOrFail(),
            Permission::create(['name' => 'contact_requests.view', 'guard_name' => 'web']),
        ]);

        $this->actingAs($user)
            ->post(route('dashboard.notifications.read-all'))
            ->assertForbidden();

        $this->assertFalse((bool) $request->refresh()->is_read);

        $user->givePermissionTo(Permission::where('name', 'notifications.edit')->firstOrFail());

        $this->actingAs($user)
            ->post(route('dashboard.notifications.read-all'))
            ->assertRedirect();

        $this->assertTrue((bool) $request->refresh()->is_read);
    }
}
