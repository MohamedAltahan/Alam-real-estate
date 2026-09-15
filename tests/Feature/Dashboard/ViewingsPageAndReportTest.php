<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\City;
use App\Models\Client;
use App\Models\ClientAuditLog;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\PropertyStatus;
use App\Models\UnitType;
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

    public function test_outcome_pills_above_the_table_carry_counts_that_ignore_the_outcome_filter(): void
    {
        $viewer = $this->userWith(['clients.view']);
        $this->viewing('ع1', null, now()->addDay());
        $this->viewing('ع2', null, now()->addDay(), ClientViewing::OUTCOME_INTERESTED);
        $this->viewing('ع3', null, now()->addDay(), ClientViewing::OUTCOME_INTERESTED);
        $this->viewing('ع4', null, now()->subDays(30), ClientViewing::OUTCOME_CANCELLED);

        $page = $this->actingAs($viewer)->get(route('dashboard.viewings.index', ['outcome' => 'interested']))->assertOk();
        $counts = $page->viewData('outcomeCounts');
        $this->assertSame(4, $counts['total']);
        $this->assertEqualsCanonicalizing(['pending' => 1, 'interested' => 2, 'cancelled' => 1], $counts['outcomes']);
        $page->assertSee('data-filter-set="outcome" data-filter-value="interested"', false)
            ->assertSee('data-filter-set="outcome" data-filter-value=""', false)
            ->assertSee('كل المعاينات')->assertSee('ع2')->assertDontSee('ع1');

        // الفلاتر الأخرى تُطبَّق على العدّادات
        $narrow = $this->actingAs($viewer)->get(route('dashboard.viewings.index', ['from' => now()->toDateString()]))->assertOk()->viewData('outcomeCounts');
        $this->assertSame(3, $narrow['total']);
        $this->assertArrayNotHasKey('cancelled', $narrow['outcomes']);
    }

    public function test_viewings_can_be_filtered_by_property_location_purpose_presence_and_whatsapp_state(): void
    {
        $viewer = $this->userWith(['clients.view']);
        $city = City::create(['name' => ['ar' => 'حولي', 'en' => 'Hawalli']]);
        $area = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $city->id]);
        $shop = UnitType::create(['name' => ['ar' => 'محل', 'en' => 'Shop'], 'category' => 'commercial']);
        $rental = Property::create(['reference_code' => '802', 'title' => ['ar' => 'محل للإيجار', 'en' => 'Shop'], 'purpose' => 'rent', 'city_id' => $city->id, 'area_id' => $area->id, 'unit_type_id' => $shop->id]);

        $a = $this->viewing('عميل البيع', null, now()->addDay());
        $b = $this->viewing('عميل الإيجار', null, now()->addDay());
        $b->forceFill(['property_id' => $rental->id, 'in_person' => false, 'owner_notified_at' => now(), 'client_followed_up_at' => now()])->save();

        $index = fn (array $q) => $this->actingAs($viewer)->get(route('dashboard.viewings.index', $q))->assertOk();

        $index(['city_id' => $city->id])->assertSee('عميل الإيجار')->assertDontSee('عميل البيع');
        $index(['area_id' => $area->id])->assertSee('عميل الإيجار')->assertDontSee('عميل البيع');
        $index(['unit_type_id' => $shop->id])->assertSee('عميل الإيجار')->assertDontSee('عميل البيع');
        $index(['purpose' => 'rent'])->assertSee('عميل الإيجار')->assertDontSee('عميل البيع');
        $index(['in_person' => '0'])->assertSee('عميل الإيجار')->assertDontSee('عميل البيع');
        $index(['in_person' => '1'])->assertSee('عميل البيع')->assertDontSee('عميل الإيجار');
        $index(['wa_state' => 'complete'])->assertSee('عميل الإيجار')->assertDontSee('عميل البيع');
        $index(['wa_state' => 'missing_owner'])->assertSee('عميل البيع')->assertDontSee('عميل الإيجار');
        $index(['wa_state' => 'missing_client'])->assertSee('عميل البيع')->assertDontSee('عميل الإيجار');
        $index([])->assertSee('حالة واتساب')->assertSee('محافظة العقار')->assertSee('كل مندوبي المبيعات')->assertDontSee('كل المسؤولين');
    }

    public function test_notes_can_be_edited_inline_with_permission_and_are_audited(): void
    {
        $viewer = $this->userWith(['clients.view']);
        $editor = $this->userWith(['clients.view', 'clients.edit']);
        $viewing = $this->viewing('عميل الملاحظة', null, now()->addDay());

        // المحرّر يرى زر الملاحظة بحمولته، والمشاهد يرى النص فقط
        $this->actingAs($editor)->get(route('dashboard.viewings.index'))->assertOk()
            ->assertSee('data-viewing-notes=', false)->assertSee(route('dashboard.viewings.notes', $viewing), false)->assertSee('إضافة ملاحظة');
        $this->actingAs($viewer)->get(route('dashboard.viewings.index'))->assertOk()->assertDontSee('data-viewing-notes=', false);

        $this->actingAs($viewer)->patchJson(route('dashboard.viewings.notes', $viewing), ['notes' => 'x'])->assertForbidden();

        $this->actingAs($editor)->patchJson(route('dashboard.viewings.notes', $viewing), ['notes' => '  العميل يفضّل المساء  '])
            ->assertOk()->assertJson(['ok' => true, 'notes' => 'العميل يفضّل المساء']);
        $this->assertSame('العميل يفضّل المساء', $viewing->fresh()->notes);

        $log = ClientAuditLog::where('client_id', $viewing->client_id)->where('action', 'viewing_updated')->latest('id')->firstOrFail();
        $this->assertSame('العميل يفضّل المساء', $log->changes['notes']['new']);

        $this->actingAs($editor)->patchJson(route('dashboard.viewings.notes', $viewing), ['notes' => str_repeat('a', 2001)])->assertUnprocessable();
        $this->actingAs($editor)->patchJson(route('dashboard.viewings.notes', $viewing), ['notes' => ''])->assertOk();
        $this->assertNull($viewing->fresh()->notes);

        // وفي صفحة العميل أيضاً
        $this->actingAs($editor)->get(route('dashboard.clients.show', $viewing->client))->assertOk()->assertSee('data-viewing-notes=', false);
    }

    public function test_updating_the_outcome_requires_edit_permission_and_is_audited(): void
    {
        $viewer = $this->userWith(['clients.view']);
        $editor = $this->userWith(['clients.view', 'clients.edit']);
        $viewing = $this->viewing('عميل النتيجة', null, now()->addDay());

        $this->actingAs($viewer)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'interested'])->assertForbidden();

        $this->actingAs($editor)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'interested'])
            ->assertSessionHasNoErrors();

        $viewing->refresh();
        $this->assertSame('interested', $viewing->outcome);
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

        $this->viewing('ع1', $agentA, now()->subDays(3), ClientViewing::OUTCOME_INTERESTED);
        $this->viewing('ع2', $agentA, now()->subDays(2), ClientViewing::OUTCOME_NOT_INTERESTED);
        $this->viewing('ع3', $agentA, now()->subDays(1), ClientViewing::OUTCOME_PENDING);
        $this->viewing('ع4', $agentB, now()->subDays(1), ClientViewing::OUTCOME_INTERESTED);
        $this->viewing('ع5', $agentB, now()->subDays(1), ClientViewing::OUTCOME_STUDYING);
        $this->viewing('ع6', $agentB, now()->subDays(1), ClientViewing::OUTCOME_CANCELLED);

        $response = $this->actingAs($manager)->get(route('dashboard.reports.conversion'))->assertOk();

        $report = $response->viewData('report');
        $this->assertSame(6, $report['kpis']['total']);
        $this->assertSame(2, $report['kpis']['interested']);
        $this->assertSame(1, $report['kpis']['not_interested']);
        $this->assertSame(3, $report['kpis']['undecided']); // قيد الانتظار + قيد الدراسة + إلغاء الموعد خارج المقام
        $this->assertSame(67, $report['kpis']['rate']); // 2 ÷ 3

        $byAgent = $report['byAgent']->keyBy('name');
        $this->assertSame(50, $byAgent['المسؤول أ']['rate']);
        $this->assertSame(100, $byAgent['المسؤول ب']['rate']);

        $filtered = $this->actingAs($manager)->get(route('dashboard.reports.conversion', ['agent_id' => $agentB->id]))->assertOk()->viewData('report');
        $this->assertSame(3, $filtered['kpis']['total']);
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
        $this->viewing('ع1', null, now()->subDays(2), ClientViewing::OUTCOME_INTERESTED);

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
        $this->assertSame(1, $report['kpis']['interested']);

        // التنبيه يظهر للمستخدم بدل تغيير الفترة بصمت
        $this->actingAs($manager)
            ->get(route('dashboard.reports.conversion', ['from' => $from, 'to' => $to]))
            ->assertSee('الفترة المطلوبة أطول من', false);
    }

    public function test_report_range_within_the_cap_is_left_alone(): void
    {
        $manager = $this->userWith(['reports.view']);
        $this->viewing('ع1', null, now()->subDays(2), ClientViewing::OUTCOME_INTERESTED);

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
