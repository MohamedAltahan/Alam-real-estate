<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientAuditLog;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\PropertyStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ViewingsPageAndReportTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $status = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
        $this->property = Property::create(['reference_code' => '801', 'title' => ['ar' => 'عقار التقرير', 'en' => 'Report Property'], 'status_id' => $status->id]);
    }

    public function test_viewings_page_lists_filters_and_numbers_rows(): void
    {
        $viewer = $this->userWith(['clients.view']);
        $agentA = User::factory()->create(['is_agent' => true, 'name' => 'المسؤول أ']);
        $agentB = User::factory()->create(['is_agent' => true, 'name' => 'المسؤول ب']);

        $this->viewing('عميل الأول', $agentA, now()->addDays(1));
        $this->viewing('عميل الثاني', $agentB, now()->addDays(10));

        $this->actingAs($viewer)->get(route('dashboard.viewings.index'))
            ->assertOk()
            ->assertSee('مواعيد المعاينات')
            ->assertSee('عميل الأول')
            ->assertSee('عميل الثاني')
            ->assertSee('tabular-nums">1<', false)
            ->assertSee('tabular-nums">2<', false);

        $this->actingAs($viewer)->get(route('dashboard.viewings.index', ['agent_id' => $agentA->id]))
            ->assertOk()
            ->assertSee('عميل الأول')
            ->assertDontSee('عميل الثاني');

        $this->actingAs($viewer)->get(route('dashboard.viewings.index', [
            'from' => now()->addDays(5)->format('Y-m-d'),
            'to' => now()->addDays(15)->format('Y-m-d'),
        ]))
            ->assertOk()
            ->assertSee('عميل الثاني')
            ->assertDontSee('عميل الأول');

        $this->actingAs(User::factory()->create())->get(route('dashboard.viewings.index'))->assertForbidden();
    }

    public function test_updating_the_outcome_requires_edit_permission_and_is_audited(): void
    {
        $viewer = $this->userWith(['clients.view']);
        $editor = $this->userWith(['clients.view', 'clients.edit']);
        $viewing = $this->viewing('عميل النتيجة', null, now()->addDay());

        $this->actingAs($viewer)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'chosen'])->assertForbidden();

        $this->actingAs($editor)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'chosen'])
            ->assertSessionHasNoErrors();

        $viewing->refresh();
        $this->assertSame('chosen', $viewing->outcome);
        $this->assertNotNull($viewing->outcome_at);
        $this->assertDatabaseHas('client_audit_logs', ['client_id' => $viewing->client_id, 'action' => 'outcome_updated', 'user_id' => $editor->id]);

        $this->actingAs($editor)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'invalid'])
            ->assertSessionHasErrors('outcome');
    }

    public function test_conversion_report_computes_rates_and_requires_permission(): void
    {
        $manager = $this->userWith(['reports.view']);
        $agentA = User::factory()->create(['is_agent' => true, 'name' => 'المسؤول أ']);
        $agentB = User::factory()->create(['is_agent' => true, 'name' => 'المسؤول ب']);

        $this->viewing('ع1', $agentA, now()->subDays(3), ClientViewing::OUTCOME_CHOSEN);
        $this->viewing('ع2', $agentA, now()->subDays(2), ClientViewing::OUTCOME_REJECTED);
        $this->viewing('ع3', $agentA, now()->subDays(1), ClientViewing::OUTCOME_PENDING);
        $this->viewing('ع4', $agentB, now()->subDays(1), ClientViewing::OUTCOME_CHOSEN);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.conversion'))->assertOk();

        $report = $response->viewData('report');
        $this->assertSame(4, $report['kpis']['total']);
        $this->assertSame(2, $report['kpis']['chosen']);
        $this->assertSame(1, $report['kpis']['rejected']);
        $this->assertSame(1, $report['kpis']['pending']);
        $this->assertSame(67, $report['kpis']['rate']); // 2 ÷ 3

        $byAgent = $report['byAgent']->keyBy('name');
        $this->assertSame(50, $byAgent['المسؤول أ']['rate']);
        $this->assertSame(100, $byAgent['المسؤول ب']['rate']);

        $filtered = $this->actingAs($manager)->get(route('dashboard.reports.conversion', ['agent_id' => $agentB->id]))->assertOk()->viewData('report');
        $this->assertSame(1, $filtered['kpis']['total']);
        $this->assertSame(100, $filtered['kpis']['rate']);

        $this->assertNotEmpty($report['monthly']['labels']);
        $this->assertCount(count($report['monthly']['labels']), $report['monthly']['line']);

        $this->actingAs($this->userWith(['clients.view']))->get(route('dashboard.reports.conversion'))->assertForbidden();
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        return $user;
    }

    public function test_report_range_longer_than_the_cap_is_shortened_from_the_start_date(): void
    {
        $manager = $this->userWith(['reports.view']);
        $this->viewing('ع1', null, now()->subDays(2), ClientViewing::OUTCOME_CHOSEN);

        $from = now()->subMonths(6)->format('Y-m-d');
        $to = now()->addYears(4)->format('Y-m-d');

        $report = $this->actingAs($manager)
            ->get(route('dashboard.reports.conversion', ['from' => $from, 'to' => $to]))
            ->assertOk()
            ->viewData('report');

        // البداية تبقى كما اختارها المستخدم، والنهاية تُقصَّر — لا نافذة في المستقبل بلا بيانات
        $this->assertTrue($report['shortened']);
        $this->assertSame($from, $report['from']->format('Y-m-d'));
        $this->assertTrue($report['to']->lt(now()->addYears(4)));
        $this->assertSame(1, $report['kpis']['chosen']);

        // التنبيه يظهر للمستخدم بدل تغيير الفترة بصمت
        $this->actingAs($manager)
            ->get(route('dashboard.reports.conversion', ['from' => $from, 'to' => $to]))
            ->assertSee('الفترة المطلوبة أطول من', false);
    }

    public function test_report_range_within_the_cap_is_left_alone(): void
    {
        $manager = $this->userWith(['reports.view']);
        $this->viewing('ع1', null, now()->subDays(2), ClientViewing::OUTCOME_CHOSEN);

        $from = now()->subMonths(3)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $report = $this->actingAs($manager)
            ->get(route('dashboard.reports.conversion', ['from' => $from, 'to' => $to]))
            ->assertOk()
            ->viewData('report');

        $this->assertFalse($report['shortened']);
        $this->assertSame($from, $report['from']->format('Y-m-d'));
        $this->assertSame($to, $report['to']->format('Y-m-d'));

        $this->actingAs($manager)
            ->get(route('dashboard.reports.conversion', ['from' => $from, 'to' => $to]))
            ->assertDontSee('الفترة المطلوبة أطول من', false);
    }

    public function test_whatsapp_report_reports_the_same_shortening_flag(): void
    {
        $manager = $this->userWith(['reports.view']);

        $report = $this->actingAs($manager)
            ->get(route('dashboard.reports.viewings', ['from' => now()->subMonth()->format('Y-m-d'), 'to' => now()->addYears(4)->format('Y-m-d')]))
            ->assertOk()
            ->viewData('report');

        $this->assertTrue($report['shortened']);
    }

    private function viewing(string $clientName, ?User $agent, $at, string $outcome = ClientViewing::OUTCOME_PENDING): ClientViewing
    {
        $client = Client::create(['name' => $clientName, 'phone_code' => '+965', 'phone' => (string) random_int(50000000, 59999999), 'agent_id' => $agent?->id]);

        return $client->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => $at, 'outcome' => $outcome]);
    }
}
