<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** تقرير تحول العملاء: ربح ÷ كل العملاء المسجّلين في الفترة، لكل مندوب مبيعات */
class ClientsConversionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_conversion_groups_by_agent_and_computes_win_rate(): void
    {
        $manager = $this->userWith(['reports.view']);
        $agentA = User::factory()->create(['is_agent' => true, 'name' => 'مندوب أ']);
        $agentB = User::factory()->create(['is_agent' => true, 'name' => 'مندوب ب']);

        $won = ClientStage::where('key', 'closed_won')->value('id');
        $lost = ClientStage::where('key', 'closed_lost')->value('id');
        $new = ClientStage::where('key', 'new')->value('id');
        $this->assertNotNull($won);

        $this->client('ع1', $agentA, $won, now()->subDays(3));
        $this->client('ع2', $agentA, $lost, now()->subDays(2));
        $this->client('ع3', $agentA, $new, now()->subDays(1));
        $this->client('ع4', $agentA, $won, now()->subDays(1));
        $this->client('ع5', $agentB, $won, now()->subDays(1));
        $this->client('ع6', null, $new, now()->subDays(1));
        $this->client('قديم', $agentB, $won, now()->subYears(2)); // خارج الفترة الافتراضية

        $report = $this->actingAs($manager)->get(route('dashboard.reports.clients-conversion'))->assertOk()->viewData('report');

        $this->assertSame(6, $report['kpis']['total']);
        $this->assertSame(3, $report['kpis']['won']);
        $this->assertSame(1, $report['kpis']['lost']);
        $this->assertSame(2, $report['kpis']['open']);
        $this->assertSame(50, $report['kpis']['rate']); // 3 ÷ 6 — كل العملاء لا المحسوم فقط

        $byAgent = $report['byAgent']->keyBy('name');
        $this->assertSame(50, $byAgent['مندوب أ']['rate']); // 2 ÷ 4
        $this->assertSame(4, $byAgent['مندوب أ']['total']);
        $this->assertSame(100, $byAgent['مندوب ب']['rate']);
        $this->assertSame(0, $byAgent['بدون مندوب']['rate']);
        $this->assertSame('مندوب أ', $report['byAgent']->first()['name']); // الأكثر عملاء أولاً

        $filtered = $this->actingAs($manager)->get(route('dashboard.reports.clients-conversion', ['agent_id' => $agentB->id]))->assertOk()->viewData('report');
        $this->assertSame(1, $filtered['kpis']['total']);
        $this->assertSame(100, $filtered['kpis']['rate']);

        $this->assertNotEmpty($report['monthly']['labels']);
        $this->assertCount(count($report['monthly']['labels']), $report['monthly']['line']);
        $this->assertCount(count($report['monthly']['labels']), $report['monthly']['bars']);
        $this->assertSame(6, array_sum($report['monthly']['bars']));

        $this->actingAs($this->userWith(['clients.view']))->get(route('dashboard.reports.clients-conversion'))->assertForbidden();
    }

    public function test_report_pages_show_the_tabs_and_sales_rep_labels(): void
    {
        $manager = $this->userWith(['reports.view']);

        $this->actingAs($manager)->get(route('dashboard.reports.conversion'))->assertOk()
            ->assertSee('تحول المعاينات')->assertSee('تحول العملاء')
            ->assertSee(route('dashboard.reports.clients-conversion'), false)
            ->assertSee('كل مندوبي المبيعات')->assertDontSee('كل المسؤولين')->assertDontSee('>المسؤول<', false)
            ->assertSee('مهتم')->assertDontSee('تم اختيار العقار');

        $this->actingAs($manager)->get(route('dashboard.reports.clients-conversion'))->assertOk()
            ->assertSee('تقرير تحول العملاء')->assertSee('ربح ÷ كل العملاء')
            ->assertSee('كل مندوبي المبيعات')->assertDontSee('كل المسؤولين');

        $this->actingAs($manager)->get(route('dashboard.reports.viewings'))->assertOk()
            ->assertSee('كل مندوبي المبيعات')->assertDontSee('كل المسؤولين')->assertSee('أُبلغ المسؤول')->assertDontSee('أُبلغ المالك');
    }

    private function client(string $name, ?User $agent, ?int $stageId, $createdAt): Client
    {
        $client = Client::create(['name' => $name, 'phone_code' => '+965', 'phone' => (string) random_int(50000000, 59999999), 'agent_id' => $agent?->id, 'stage_id' => $stageId]);
        $client->forceFill(['created_at' => $createdAt])->saveQuietly();

        return $client;
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
