<?php

namespace Tests\Feature\Site;

use App\Http\Middleware\SiteMaintenance;
use App\Models\ContactRequest;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** زر «إيقاف الموقع مؤقتاً» في تبويب «التفضيلات»: 503 نظيف للزوار، والداشبورد وفريق العمل كالمعتاد */
class SiteMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_ROUTES = ['site.home', 'site.properties', 'site.about', 'site.contact', 'site.list-property', 'site.faq', 'site.terms', 'site.privacy'];

    public function test_guests_get_a_temporary_503_on_every_public_page_while_the_site_is_stopped(): void
    {
        $this->stopSite();

        foreach (self::PUBLIC_ROUTES as $route) {
            $response = $this->get(route($route))
                ->assertStatus(503)
                ->assertHeader('Retry-After', (string) SiteMaintenance::RETRY_AFTER)
                ->assertSee('نعود قريباً');

            // noindex أو تحويل كانا سيضرّان الفهرسة — الحالة 503 وحدها هي الإشارة
            $response->assertDontSee('noindex', false);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_the_page_shows_the_office_contacts(): void
    {
        Setting::set('contact', 'phone', '+965 2222 3333');
        Setting::set('contact', 'whatsapp', '+965 5555 6666');
        $this->stopSite();

        $this->get(route('site.home'))->assertStatus(503)
            ->assertSee('+965 2222 3333')
            ->assertSee('https://wa.me/96555556666', false);
    }

    public function test_forms_are_not_accepted_while_the_site_is_stopped(): void
    {
        $this->stopSite();

        $this->post(route('site.contact.store'), ['name' => 'زائر', 'phone' => '55112233', 'message' => 'استفسار'])
            ->assertStatus(503);

        $this->assertSame(0, ContactRequest::count());
    }

    public function test_the_language_switch_still_works_on_the_maintenance_page(): void
    {
        $this->stopSite();

        $this->from(route('site.home'))->get(route('site.locale', 'en'))->assertRedirect(route('site.home'));

        $this->get(route('site.home'))->assertStatus(503)->assertSee('back shortly');
    }

    public function test_logged_in_staff_browse_the_site_normally_with_a_reminder(): void
    {
        $this->stopSite();

        $this->actingAs(User::factory()->create())
            ->get(route('site.faq'))
            ->assertOk()
            ->assertSee('وضع الصيانة مفعّل');
    }

    public function test_the_dashboard_and_login_are_not_affected(): void
    {
        $this->stopSite();

        $this->get(route('login'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('dashboard.profile.edit'))->assertOk();
    }

    public function test_nothing_changes_while_the_site_is_running(): void
    {
        $this->get(route('site.faq'))->assertOk()->assertDontSee('نعود قريباً');

        $this->actingAs(User::factory()->create())
            ->get(route('site.faq'))
            ->assertOk()
            ->assertDontSee('وضع الصيانة مفعّل');
    }

    public function test_stopping_the_site_requires_website_edit_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('dashboard.profile.site-maintenance'), ['enabled' => '1'])->assertForbidden();
        $this->actingAs($user)->get(route('dashboard.profile.site-maintenance.preview'))->assertForbidden();

        $this->assertNull(SiteFlags::maintenance());
    }

    public function test_an_editor_can_stop_and_restart_the_site(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->put(route('dashboard.profile.site-maintenance'), ['enabled' => '1'])
            ->assertRedirect(route('dashboard.profile.edit', ['tab' => 'preferences']));

        $state = SiteFlags::maintenance();
        $this->assertNotNull($state);
        $this->assertSame($editor->name, $state['by']);

        // ضغطة مكررة بعد ساعتين لا تُصفّر وقت الإيقاف
        $this->travel(2)->hours();
        $this->actingAs($editor)->put(route('dashboard.profile.site-maintenance'), ['enabled' => '1'])->assertRedirect();
        $this->assertTrue($state['since']->equalTo(SiteFlags::maintenance()['since']));

        auth()->logout();
        $this->get(route('site.faq'))->assertStatus(503);

        $this->actingAs($editor)->put(route('dashboard.profile.site-maintenance'), ['enabled' => '0'])->assertRedirect();
        $this->assertNull(SiteFlags::maintenance());

        auth()->logout();
        $this->get(route('site.faq'))->assertOk();
    }

    public function test_the_status_card_is_shown_only_to_editors(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.profile.edit', ['tab' => 'preferences']))
            ->assertOk()
            ->assertDontSee('إيقاف الموقع مؤقتاً');

        $editor = $this->editor();

        $this->actingAs($editor)
            ->get(route('dashboard.profile.edit', ['tab' => 'preferences']))
            ->assertOk()
            ->assertSee('إيقاف الموقع مؤقتاً')
            ->assertDontSee('الموقع متوقف');

        $this->stopSite();

        $this->actingAs($editor)
            ->get(route('dashboard.profile.edit', ['tab' => 'preferences']))
            ->assertOk()
            ->assertSee('تشغيل الموقع')
            ->assertSee('الموقع متوقف')
            ->assertSee(route('dashboard.profile.site-maintenance.preview'), false)
            ->assertDontSee('أكثر من يومين');
    }

    public function test_a_long_stop_warns_the_editor(): void
    {
        $this->stopSite(since: now()->subHours(SiteFlags::MAINTENANCE_WARN_HOURS + 1));

        $this->actingAs($this->editor())
            ->get(route('dashboard.profile.edit', ['tab' => 'preferences']))
            ->assertSee('أكثر من يومين');
    }

    public function test_the_editor_can_preview_what_visitors_see(): void
    {
        $this->actingAs($this->editor())
            ->get(route('dashboard.profile.site-maintenance.preview'))
            ->assertOk()
            ->assertSee('نعود قريباً')
            ->assertSee('معاينة');
    }

    private function stopSite(?\DateTimeInterface $since = null): void
    {
        Setting::set(SiteFlags::GROUP, SiteFlags::MAINTENANCE, [
            'since' => ($since ?? now())->format(DATE_ATOM),
            'by' => 'مدير الموقع',
        ]);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'website.edit', 'guard_name' => 'web']));

        return $user;
    }
}
