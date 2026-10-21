<?php

namespace Tests\Feature\Site;

use App\Models\Property;
use App\Models\User;
use App\Support\PropertyViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** عدد مشاهدات صفحة العقار: زائر واحد مرة في اليوم، بلا موظفين ولا بوتات — ويظهر للشركة فقط */
class PropertyViewsCountTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36';

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::create(['reference_code' => '41', 'title' => ['ar' => 'شقة المشاهدات', 'en' => 'Views flat']]);
    }

    public function test_a_visitor_is_counted_once_per_day_per_property(): void
    {
        $first = $this->withHeader('User-Agent', self::BROWSER)->get(route('site.property', $this->property))->assertOk();
        $this->assertSame(1, $this->views());

        $cookie = $first->getCookie(PropertyViews::COOKIE)->getValue();

        // زائر آخر (بلا كوكي) ⇒ يُحسب
        $this->withHeader('User-Agent', self::BROWSER)->get(route('site.property', $this->property))->assertOk();
        $this->assertSame(2, $this->views());

        // نفس الزائر الأول (نفس الكوكي) في نفس اليوم ⇒ لا يزيد
        $this->withHeader('User-Agent', self::BROWSER)->withCookie(PropertyViews::COOKIE, $cookie)
            ->get(route('site.property', $this->property))->assertOk();
        $this->assertSame(2, $this->views());

        // نفس الزائر في يوم جديد ⇒ يُحسب من جديد
        Carbon::setTestNow(now()->addDay());
        $this->withHeader('User-Agent', self::BROWSER)->withCookie(PropertyViews::COOKIE, $cookie)
            ->get(route('site.property', $this->property))->assertOk();
        $this->assertSame(3, $this->views());
        Carbon::setTestNow();
    }

    public function test_staff_bots_and_link_previews_are_not_counted_and_updated_at_is_untouched(): void
    {
        $updatedAt = $this->property->fresh()->updated_at;

        $this->actingAs(User::factory()->create())->withHeader('User-Agent', self::BROWSER)
            ->get(route('site.property', $this->property))->assertOk();
        auth()->logout();

        foreach (['Googlebot/2.1 (+http://www.google.com/bot.html)', 'WhatsApp/2.23.20.0 A', 'facebookexternalhit/1.1', ''] as $agent) {
            $this->withHeader('User-Agent', $agent)->get(route('site.property', $this->property))->assertOk();
        }

        $this->assertSame(0, $this->views());

        $this->travel(5)->minutes();
        $this->withHeader('User-Agent', self::BROWSER)->get(route('site.property', $this->property))->assertOk();
        $this->assertSame(1, $this->views());
        $this->assertEquals($updatedAt, $this->property->fresh()->updated_at);
    }

    public function test_views_are_shown_in_the_dashboard_only(): void
    {
        $this->property->forceFill(['views_count' => 1234])->saveQuietly();

        $this->withHeader('User-Agent', self::BROWSER)->get(route('site.property', $this->property))
            ->assertOk()->assertDontSee('1,234')->assertDontSee('1,235');
        $this->assertArrayNotHasKey('views_count', $this->property->fresh()->toArray());

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'properties.view', 'guard_name' => 'web']));

        $this->actingAs($user)->get(route('dashboard.properties.index'))->assertOk()->assertSee('المشاهدات')->assertSee('1,235');
        $this->actingAs($user)->get(route('dashboard.properties.show', $this->property))->assertOk()->assertSee('1,235 مشاهدة');
    }

    private function views(): int
    {
        return (int) Property::whereKey($this->property->id)->value('views_count');
    }
}
