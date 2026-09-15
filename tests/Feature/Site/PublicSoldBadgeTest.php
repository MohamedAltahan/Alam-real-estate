<?php

namespace Tests\Feature\Site;

use App\Models\Property;
use App\Models\PropertyStatus;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** شارة «مباع» الحمراء على الموقع العام — إعداد عام يُدار من تبويب «التفضيلات» */
class PublicSoldBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Property $sold;

    private Property $plain;

    protected function setUp(): void
    {
        parent::setUp();

        $available = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
        $soldStatus = PropertyStatus::create(['name' => ['ar' => 'مباع', 'en' => 'Sold'], 'key' => 'sold']);

        $this->sold = Property::create(['reference_code' => '902', 'title' => ['ar' => 'عقار مباع', 'en' => 'Sold one'], 'status_id' => $soldStatus->id]);
        $this->plain = Property::create(['reference_code' => '903', 'title' => ['ar' => 'عقار عادي', 'en' => 'Plain one'], 'status_id' => $available->id]);
    }

    public function test_toggle_is_shown_only_to_users_who_can_edit_the_website(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.profile.edit', ['tab' => 'preferences']))
            ->assertOk()
            ->assertDontSee('name="site_busy_badge"', false);

        $this->actingAs($this->editor())
            ->get(route('dashboard.profile.edit', ['tab' => 'preferences']))
            ->assertOk()
            ->assertSee('name="site_busy_badge"', false)
            ->assertSee('شارة «مباع» على الموقع');
    }

    public function test_saving_the_toggle_requires_permission_and_persists_the_global_setting(): void
    {
        $payload = ['language' => 'ar', 'date_format' => 'd/m/Y', 'currency' => 'KWD', 'site_busy_badge' => '1'];

        $this->actingAs(User::factory()->create())
            ->put(route('dashboard.profile.preferences'), $payload)
            ->assertForbidden();
        $this->assertNull(Setting::get(SiteFlags::GROUP, SiteFlags::BUSY_BADGE));

        $editor = $this->editor();

        $this->actingAs($editor)->put(route('dashboard.profile.preferences'), $payload)
            ->assertRedirect(route('dashboard.profile.edit', ['tab' => 'preferences']));
        $this->assertTrue(Setting::get(SiteFlags::GROUP, SiteFlags::BUSY_BADGE));
        $this->assertSame('KWD', data_get($editor->refresh()->preferences, 'display.currency'));

        $this->actingAs($editor)->put(route('dashboard.profile.preferences'), ['site_busy_badge' => '0'] + $payload)->assertRedirect();
        $this->assertFalse(Setting::get(SiteFlags::GROUP, SiteFlags::BUSY_BADGE));

        // بدون الحقل (مستخدم عادي) تُحفظ التفضيلات الشخصية ولا يُلمس الإعداد العام
        $this->actingAs(User::factory()->create())
            ->put(route('dashboard.profile.preferences'), ['language' => 'en', 'date_format' => 'Y-m-d', 'currency' => 'USD'])
            ->assertRedirect();
        $this->assertFalse(Setting::get(SiteFlags::GROUP, SiteFlags::BUSY_BADGE));
    }

    public function test_badges_are_shown_only_when_the_setting_is_on(): void
    {
        Setting::set(SiteFlags::GROUP, SiteFlags::BUSY_BADGE, true);

        $list = $this->get(route('site.properties'))->assertOk();
        $html = $list->getContent();

        $this->assertSame(1, substr_count($html, 'data-badge="sold"'));
        $this->assertStringNotContainsString('data-badge="busy"', $html);
        $this->assertMatchesRegularExpression('/data-badge="sold"[^>]*>(مباع|Sold)</u', $html);

        // صفحة العقار: الشارة بجانب العنوان
        $this->get(route('site.property', $this->sold))->assertOk()
            ->assertSee('data-badge="sold"', false);

        // العقار العادي: لا شارة بجانب عنوانه (شارات «عقارات مشابهة» أسفل الصفحة لا تُحتسب)
        $plain = $this->get(route('site.property', $this->plain))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<h1[^>]*>عقار عادي<\/h1>\s*<\/div>/u', $plain);
    }

    public function test_nothing_is_shown_when_the_setting_is_off(): void
    {
        $this->get(route('site.properties'))->assertOk()->assertDontSee('data-badge', false);
        $this->get(route('site.property', $this->sold))->assertOk()->assertDontSee('data-badge', false);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'website.edit', 'guard_name' => 'web']));

        return $user;
    }
}
