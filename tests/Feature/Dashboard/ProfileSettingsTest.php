<?php

namespace Tests\Feature\Dashboard;

use App\Models\ContactRequest;
use App\Models\User;
use App\Support\NotificationFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_profile_settings(): void
    {
        $this->get('/dashboard/profile')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_open_each_settings_tab(): void
    {
        $user = User::factory()->create();

        foreach (['profile', 'security', 'notifications', 'preferences'] as $tab) {
            $this->actingAs($user)
                ->get(route('dashboard.profile.edit', ['tab' => $tab]))
                ->assertOk()
                ->assertSee('إعدادات الملف الشخصي');
        }
    }

    public function test_user_can_update_profile_information(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('dashboard.profile.update'), [
            'name' => 'محمد الإداري',
            'email' => 'manager@example.com',
            'phone' => '+965 9900 0001',
            'job_title' => 'مدير النظام',
            'bio' => 'نبذة مختصرة عن المستخدم.',
        ])->assertRedirect(route('dashboard.profile.edit', ['tab' => 'profile']));

        $user->refresh();

        $this->assertSame('محمد الإداري', $user->name);
        $this->assertSame('manager@example.com', $user->email);
        $this->assertSame('+965 9900 0001', $user->phone);
        $this->assertSame('مدير النظام', $user->job_title);
        $this->assertSame('نبذة مختصرة عن المستخدم.', $user->getTranslation('bio', 'ar'));
    }

    public function test_user_can_update_password_with_current_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($user)->put(route('dashboard.profile.password'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('dashboard.profile.edit', ['tab' => 'security']));

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_user_can_persist_notification_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('dashboard.profile.notifications'), [
            'contact_requests' => '0',
            'new_clients' => '1',
            'closed_deals' => '0',
            'follow_up_reminders' => '1',
        ])->assertRedirect(route('dashboard.profile.edit', ['tab' => 'notifications']));

        $this->assertSame([
            'contact_requests' => false,
            'new_clients' => true,
            'closed_deals' => false,
            'follow_up_reminders' => true,
        ], data_get($user->refresh()->preferences, 'notifications'));
    }

    public function test_user_can_persist_viewing_reminder_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('dashboard.profile.notifications'), [
            'contact_requests' => '1',
            'new_clients' => '1',
            'closed_deals' => '1',
            'follow_up_reminders' => '0',
            'viewing_enabled' => '1',
            'viewing_lead_minutes' => '45',
            'viewing_repeat_beep' => '0',
        ])->assertRedirect(route('dashboard.profile.edit', ['tab' => 'notifications']));

        $user->refresh();
        $this->assertSame(45, $user->viewingLeadMinutes());
        $this->assertFalse($user->viewingRepeatBeep());
        $this->assertTrue($user->viewingRemindersEnabled());

        $this->actingAs($user)->put(route('dashboard.profile.notifications'), [
            'contact_requests' => '1', 'new_clients' => '1', 'closed_deals' => '1', 'follow_up_reminders' => '0',
            'viewing_lead_minutes' => '2',
        ])->assertSessionHasErrors('viewing_lead_minutes');

        $this->actingAs($user)->get(route('dashboard.profile.edit', ['tab' => 'notifications']))
            ->assertOk()
            ->assertSee('تذكير مواعيد المعاينات');
    }

    public function test_user_can_persist_display_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('dashboard.profile.preferences'), [
            'language' => 'en',
            'date_format' => 'Y-m-d',
            'currency' => 'USD',
        ])->assertRedirect(route('dashboard.profile.edit', ['tab' => 'preferences']))
            ->assertSessionHas('locale', 'en');

        $this->assertSame([
            'language' => 'en',
            'date_format' => 'Y-m-d',
            'currency' => 'USD',
        ], data_get($user->refresh()->preferences, 'display'));
        $this->assertSame('$', $user->currencySymbol());
    }

    public function test_disabled_contact_notifications_are_removed_from_the_feed_and_counter(): void
    {
        $user = User::factory()->create();
        $permission = Permission::create(['name' => 'contact_requests.view', 'guard_name' => 'web']);
        $notificationPermission = Permission::where('name', 'notifications.view')->firstOrFail();
        $user->givePermissionTo([$permission, $notificationPermission]);
        ContactRequest::create(['name' => 'عميل جديد', 'phone' => '99999999']);

        $this->assertSame(1, NotificationFeed::unreadCount($user));
        $this->assertTrue(NotificationFeed::items($user)->contains(
            fn (array $item) => str_contains($item['title'], 'عميل جديد')
        ));

        $user->update(['preferences' => [
            'notifications' => ['contact_requests' => false],
        ]]);

        $this->assertSame(0, NotificationFeed::unreadCount($user->refresh()));
        $this->assertFalse(NotificationFeed::items($user)->contains(
            fn (array $item) => str_contains($item['title'], 'عميل جديد')
        ));
    }
}
