<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** رصيد رسائل الباقة (المُرسل والمتبقي) في شاشة واتساب */
class WhatsAppUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.khabeersoft.api_key' => 'wag_test_key',
            'services.khabeersoft.base_url' => 'https://gateway.test/api/v1',
        ]);
    }

    public function test_the_log_shows_the_gateway_balance_and_asks_the_gateway_once(): void
    {
        Http::fake(['*/usage' => Http::response([
            'monthly' => ['used' => 320, 'limit' => 1000, 'remaining' => 680, 'percentage' => 32],
            'daily' => ['used' => 45, 'limit' => 500, 'remaining' => 455, 'percentage' => 9],
        ])]);

        $this->actingAs($this->viewer())->get(route('dashboard.whatsapp.index', ['tab' => 'messages']))
            ->assertOk()
            ->assertSee('رصيد الرسائل')
            ->assertSee('هذا الشهر')
            ->assertSee('اليوم')
            ->assertSee('320')
            ->assertSee('متبقي 680')
            ->assertSee('من 1,000')
            ->assertSee('32% من الباقة')
            ->assertSee('متبقي 455');

        // الرصيد مخزَّن مؤقتاً — إعادة فتح الصفحة لا تسأل البوابة مجدداً
        $this->actingAs($this->viewer())->get(route('dashboard.whatsapp.index', ['tab' => 'messages']))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_missing_remaining_and_percentage_are_computed_and_an_open_plan_shows_no_limit(): void
    {
        Http::fake(['*/usage' => Http::response([
            'monthly' => ['used' => 250, 'limit' => 1000],
            'daily' => ['used' => 12, 'limit' => null],
        ])]);

        $usage = app(WhatsAppService::class)->usage();

        $this->assertTrue($usage['available']);
        $this->assertSame(['monthly', 'daily'], array_column($usage['periods'], 'key'));

        [$monthly, $daily] = $usage['periods'];
        $this->assertSame(750, $monthly['remaining']);
        $this->assertSame(25, $monthly['percentage']);
        $this->assertNull($daily['limit']);
        $this->assertNull($daily['remaining']);
        $this->assertSame(0, $daily['percentage']);

        $this->actingAs($this->viewer())->get(route('dashboard.whatsapp.index', ['tab' => 'messages']))
            ->assertOk()
            ->assertSee('متبقي 750')
            ->assertSee('بلا حد');
    }

    public function test_a_gateway_failure_keeps_the_screen_working(): void
    {
        Http::fake(['*/usage' => Http::response(['error' => 'Invalid API key'], 401)]);

        $this->actingAs($this->viewer())->get(route('dashboard.whatsapp.index', ['tab' => 'messages']))
            ->assertOk()
            ->assertSee('تعذّر جلب الرصيد من البوابة')
            ->assertSee('Invalid API key')
            ->assertSee('سجل الرسائل');
    }

    public function test_the_balance_is_hidden_when_the_gateway_key_is_missing(): void
    {
        config(['services.khabeersoft.api_key' => '']);
        Http::fake();

        $this->actingAs($this->viewer())->get(route('dashboard.whatsapp.index', ['tab' => 'messages']))
            ->assertOk()
            ->assertDontSee('رصيد الرسائل');

        Http::assertNothingSent();
    }

    public function test_the_refresh_button_asks_the_gateway_for_a_fresh_balance(): void
    {
        Http::fake(['*/usage' => Http::response(['monthly' => ['used' => 5, 'limit' => 100]])]);

        $this->actingAs($this->viewer())->get(route('dashboard.whatsapp.index', ['tab' => 'messages']))->assertOk();
        Http::assertSentCount(1);

        $this->actingAs($this->viewer())->post(route('dashboard.whatsapp.messages.refresh'))
            ->assertRedirect(route('dashboard.whatsapp.index', ['tab' => 'messages']));

        Http::assertSentCount(2);
    }

    private function viewer(): User
    {
        return User::factory()->create()->givePermissionTo(
            Permission::firstOrCreate(['name' => 'whatsapp.view', 'guard_name' => 'web'])
        );
    }
}
