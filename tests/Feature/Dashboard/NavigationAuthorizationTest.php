<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NavigationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_only_shows_modules_the_user_can_view(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['dashboard.view', 'clients.view']);

        $this->actingAs($user)
            ->get(route('dashboard.profile.edit'))
            ->assertOk()
            ->assertSee('لوحة التحكم')
            ->assertSee('إدارة العملاء')
            ->assertDontSee('المهام')
            ->assertDontSee('ملاك العقارات')
            ->assertDontSee('طلبات التواصل')
            ->assertDontSee('إدارة الموقع')
            ->assertDontSee('المواقع الإلكترونية')
            ->assertDontSee('السوشال ميديا')
            ->assertDontSee('المشرفين');

        $this->actingAs($user)
            ->get(route('dashboard.owners.index'))
            ->assertForbidden();
    }

    public function test_create_property_links_are_hidden_without_create_permission(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['properties.view']);

        $this->actingAs($user)
            ->get(route('dashboard.properties.index'))
            ->assertOk()
            ->assertDontSee('إضافة عقار')
            ->assertDontSee('أضف أول عقار');
    }

    public function test_dashboard_opens_but_hides_all_data_without_dashboard_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.profile.edit'))
            ->assertOk()
            ->assertSee('لوحة التحكم');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('مرحباً')
            ->assertDontSee('اجمالي العقارات')
            ->assertDontSee('اجمالي العملاء')
            ->assertDontSee('الـ Leads حسب الشهر')
            ->assertDontSee('أحدث العقارات')
            ->assertDontSee('أحدث طلبات التواصل');
    }

    public function test_dashboard_sections_require_dashboard_and_module_permissions(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['dashboard.view', 'properties.view']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('اجمالي العقارات')
            ->assertSee('الإيراد الشهري')
            ->assertSee('أحدث العقارات')
            ->assertDontSee('اجمالي العملاء')
            ->assertDontSee('الـ Leads حسب الشهر')
            ->assertDontSee('أحدث طلبات التواصل');
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }
    }
}
