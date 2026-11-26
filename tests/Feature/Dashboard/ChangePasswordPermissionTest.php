<?php

namespace Tests\Feature\Dashboard;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** صلاحية «تغيير كلمة المرور» وتسجيل كل تغيير في سجل النشاط (بدون القيمة) */
class ChangePasswordPermissionTest extends TestCase
{
    use RefreshDatabase;

    private const NEW = 'Brand-New-Pass-2026';

    public function test_user_with_permission_changes_password_and_it_is_logged_once(): void
    {
        $user = $this->userWith([User::CHANGE_PASSWORD]);

        $this->actingAs($user)->put(route('dashboard.profile.password'), $this->form())->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
        $log = ActivityLog::where('event', 'password_changed')->sole();
        $this->assertSame($user->id, (int) $log->user_id);
        $this->assertSame($user->id, (int) $log->subject_id);
        $this->assertSame('تم التغيير من إعدادات الحساب', $log->changes['password']['new']);
        $this->assertNoSecretIn($log, $user);
    }

    public function test_user_without_permission_cannot_change_it_from_any_door(): void
    {
        $user = $this->userWith([]);
        $old = $user->password;

        $this->actingAs($user)->get(route('dashboard.profile.edit', ['tab' => 'security']))
            ->assertOk()
            ->assertSee('تغيير كلمة المرور غير متاح')
            ->assertDontSee('name="current_password"', false);

        $this->actingAs($user)->put(route('dashboard.profile.password'), $this->form())->assertForbidden();
        // مسار Fortify نفسه
        $this->actingAs($user)->put(route('user-password.update'), $this->form());
        $this->assertSame($old, $user->fresh()->password);

        // «نسيت كلمة المرور»
        auth()->logout();
        $this->post(route('password.update'), $this->resetForm($user))->assertSessionHasErrors('password');
        $this->assertSame($old, $user->fresh()->password);
        $this->assertSame(0, ActivityLog::where('event', 'password_changed')->count());
    }

    public function test_forgot_password_reset_is_logged_with_the_account_owner_as_actor(): void
    {
        $user = $this->userWith([User::CHANGE_PASSWORD]);

        $this->post(route('password.update'), $this->resetForm($user))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
        $log = ActivityLog::where('event', 'password_changed')->sole();
        $this->assertSame($user->id, (int) $log->user_id);
        $this->assertStringContainsString('نسيت كلمة المرور', $log->changes['password']['new']);
    }

    public function test_admin_change_is_logged_and_own_password_stays_blocked_without_permission(): void
    {
        Role::create(['name' => 'sales-agent', 'guard_name' => 'web', 'description' => 'مندوب مبيعات']);
        $admin = $this->userWith(['supervisors.view', 'supervisors.edit', 'activity.view']);
        $staff = $this->userWith([]);
        $form = fn (User $u) => ['name' => $u->name, 'email' => $u->email, 'password' => self::NEW, 'role' => 'sales-agent', 'status' => 'active'];

        // المدير يغيّر كلمة موظف: مسموح ويُسجَّل باسم المدير
        $this->actingAs($admin)->put(route('dashboard.supervisors.update', $staff), $form($staff))->assertSessionHasNoErrors();
        $log = ActivityLog::where('event', 'password_changed')->sole();
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame($staff->id, (int) $log->subject_id);
        $this->assertStringContainsString('بواسطة المدير', $log->changes['password']['new']);
        $this->assertNoSecretIn($log, $staff);

        // لكن لا يغيّر كلمته هو من نفس الشاشة وهو بلا الصلاحية
        $this->actingAs($admin)->put(route('dashboard.supervisors.update', $admin), $form($admin))->assertSessionHasErrors('password');
        $this->assertSame(1, ActivityLog::where('event', 'password_changed')->count());

        $this->actingAs($admin->fresh())->get(route('dashboard.activity.index', ['event' => 'password_changed']))
            ->assertOk()->assertSee('تغيير كلمة المرور')->assertSee($staff->name);
    }

    public function test_permission_is_listed_in_the_matrix_and_new_roles_start_with_it(): void
    {
        $admin = $this->userWith(['permissions.view', 'permissions.edit', 'roles.view', 'roles.create']);

        $this->actingAs($admin)->post(route('dashboard.roles.store'), ['description' => 'محاسب', 'status' => 'active'])
            ->assertSessionHasNoErrors();
        $role = Role::where('description', 'محاسب')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo(User::CHANGE_PASSWORD));

        $this->actingAs($admin)->get(route('dashboard.permissions.index', ['role' => $role->id]))
            ->assertOk()->assertSee('تغيير كلمة المرور')->assertSee('value="'.User::CHANGE_PASSWORD.'"', false);

        // سحبها من الدور
        $this->actingAs($admin)->put(route('dashboard.permissions.update'), ['role_id' => $role->id, 'permissions' => []])->assertRedirect();
        $this->assertFalse($role->refresh()->hasPermissionTo(User::CHANGE_PASSWORD));
    }

    private function form(): array
    {
        return ['current_password' => 'password', 'password' => self::NEW, 'password_confirmation' => self::NEW];
    }

    private function resetForm(User $user): array
    {
        return ['token' => Password::createToken($user), 'email' => $user->email, 'password' => self::NEW, 'password_confirmation' => self::NEW];
    }

    private function assertNoSecretIn(ActivityLog $log, User $user): void
    {
        $json = json_encode($log->changes, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString(self::NEW, $json);
        $this->assertStringNotContainsString($user->fresh()->password, $json);
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
