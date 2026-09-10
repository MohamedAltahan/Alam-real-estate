<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientAuditLog;
use App\Models\ClientStage;
use App\Models\Property;
use App\Models\PropertyStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** تواريخ الصفقات الحقيقية (sold_at / won_at) بدل updated_at في مؤشرات الداشبورد */
class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $availableId;

    private int $soldId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        foreach (['dashboard.view', 'properties.view', 'clients.view', 'contact_requests.view', 'notifications.view'] as $name) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $this->availableId = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available'])->id;
        $this->soldId = PropertyStatus::create(['name' => ['ar' => 'مباع', 'en' => 'Sold'], 'key' => 'sold'])->id;
    }

    public function test_sold_at_follows_the_status_and_survives_unrelated_edits(): void
    {
        $property = Property::create(['reference_code' => '1', 'title' => ['ar' => 'شقة', 'en' => 'Flat'], 'status_id' => $this->availableId]);
        $this->assertNull($property->sold_at);

        Carbon::setTestNow('2026-07-01 10:00:00');
        $property->update(['status_id' => $this->soldId]);
        $this->assertSame('2026-07-01 10:00:00', $property->fresh()->sold_at->toDateTimeString());

        // تعديل لاحق (عنوان) لا يحرّك تاريخ البيع رغم تحرّك updated_at
        Carbon::setTestNow('2026-09-10 12:00:00');
        $property->update(['title' => ['ar' => 'شقة معدّلة', 'en' => 'Edited flat']]);
        $property->refresh();
        $this->assertSame('2026-07-01 10:00:00', $property->sold_at->toDateTimeString());
        $this->assertSame('2026-09-10 12:00:00', $property->updated_at->toDateTimeString());

        $property->update(['status_id' => $this->availableId]);
        $this->assertNull($property->fresh()->sold_at);

        $property->update(['status_id' => $this->soldId]);
        $this->assertSame('2026-09-10 12:00:00', $property->fresh()->sold_at->toDateTimeString());
    }

    public function test_won_at_follows_the_stage_and_is_not_audited(): void
    {
        $this->actingAs($this->user);
        $newId = ClientStage::where('key', 'new')->value('id');
        $wonId = ClientStage::where('key', 'closed_won')->value('id');

        $client = Client::create(['name' => 'عميل', 'phone' => '1', 'stage_id' => $newId]);
        $this->assertNull($client->won_at);

        Carbon::setTestNow('2026-08-01 09:00:00');
        $client->update(['stage_id' => $wonId]);
        $this->assertSame('2026-08-01 09:00:00', $client->fresh()->won_at->toDateTimeString());

        Carbon::setTestNow('2026-09-10 12:00:00');
        $client->update(['name' => 'عميل معدّل']);
        $this->assertSame('2026-08-01 09:00:00', $client->fresh()->won_at->toDateTimeString());

        $client->update(['stage_id' => ClientStage::where('key', 'viewing')->value('id')]);
        $this->assertNull($client->fresh()->won_at);

        ClientAuditLog::where('client_id', $client->id)->where('action', 'updated')->get()
            ->each(fn (ClientAuditLog $log) => $this->assertArrayNotHasKey('won_at', $log->changes));
    }

    public function test_dashboard_month_stats_use_deal_timestamps_not_updated_at(): void
    {
        $real = CarbonImmutable::now();
        $twoMonthsAgo = $real->subMonths(2)->startOfMonth()->addDays(10)->setTime(10, 0);
        $wonId = ClientStage::where('key', 'closed_won')->value('id');

        // بيع وربح قبل شهرين
        Carbon::setTestNow($twoMonthsAgo);
        $property = Property::create(['reference_code' => '1', 'title' => ['ar' => 'فيلا', 'en' => 'Villa'], 'status_id' => $this->soldId, 'price' => 100000]);
        $client = Client::create(['name' => 'عميل رابح', 'phone' => '1', 'stage_id' => $wonId]);

        // تعديلات اليوم لا تخصّ الصفقة
        Carbon::setTestNow($real);
        $property->update(['title' => ['ar' => 'فيلا معدّلة', 'en' => 'Edited villa']]);
        $client->update(['name' => 'عميل رابح معدّل']);

        $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        $stats = collect($response->viewData('stats'))->keyBy('key');
        $this->assertStringContainsString('100,000', $stats['revenue']['value']);
        $this->assertSame('0', $stats['revenue']['sub_value']); // إيرادات هذا الشهر
        $this->assertSame('1', $stats['deals']['value']);
        $this->assertSame('0', $stats['deals']['sub_value']); // صفقات الشهر الحالي

        $revenue = collect($response->viewData('charts')['revenue']['data'])->values();
        $this->assertEqualsWithDelta(100.0, $revenue[$revenue->count() - 3], 0.001); // قبل شهرين (بالآلاف)
        $this->assertEqualsWithDelta(0.0, $revenue->last(), 0.001); // الشهر الحالي
    }
}
