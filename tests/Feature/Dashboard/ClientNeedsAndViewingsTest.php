<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\City;
use App\Models\Client;
use App\Models\ClientAuditLog;
use App\Models\ClientType;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientNeedsAndViewingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private City $city;

    private Area $area;

    private Area $otherArea;

    private UnitType $unitType;

    private PropertyStatus $available;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        foreach (['clients.view', 'clients.create', 'clients.edit', 'clients.delete'] as $name) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $this->city = City::create(['name' => ['ar' => 'محافظة حولي', 'en' => 'Hawalli']]);
        $otherCity = City::create(['name' => ['ar' => 'محافظة الأحمدي', 'en' => 'Ahmadi']]);
        $this->area = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $this->city->id]);
        $this->otherArea = Area::create(['name' => ['ar' => 'الفنطاس', 'en' => 'Fintas'], 'city_id' => $otherCity->id]);
        $this->unitType = UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment']]);

        $this->available = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
        PropertyStatus::create(['name' => ['ar' => 'محجوز', 'en' => 'Reserved'], 'key' => 'reserved']);
        PropertyStatus::create(['name' => ['ar' => 'مباع', 'en' => 'Sold'], 'key' => 'sold']);

        $this->property = Property::create([
            'reference_code' => 'ALM-501', 'title' => ['ar' => 'شقة السالمية', 'en' => 'Salmiya Flat'],
            'status_id' => $this->available->id, 'area_id' => $this->area->id,
        ]);
    }

    public function test_store_creates_needs_viewings_and_links_the_property(): void
    {
        $scheduled = now()->addDay()->format('Y-m-d H:i');

        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل جديد',
            'phone_code' => '+965',
            'phone' => '5511 2233',
            'preferred_contact' => 'email',
            'needs' => [
                ['unit_type_id' => $this->unitType->id, 'city_id' => $this->city->id, 'area_id' => $this->area->id],
                ['unit_type_id' => $this->unitType->id, 'city_id' => '', 'area_id' => ''],
                ['unit_type_id' => '', 'city_id' => '', 'area_id' => ''], // سطر فارغ يُهمل
            ],
            'viewings' => [
                ['property_id' => $this->property->id, 'scheduled_at' => $scheduled, 'in_person' => '0', 'outcome' => 'pending', 'notes' => 'معاينة أولى'],
            ],
        ])->assertSessionHasNoErrors();

        $client = Client::where('phone', '55112233')->firstOrFail();

        $this->assertSame('+965', $client->phone_code);
        $this->assertSame('+965 55112233', $client->full_phone);
        $this->assertSame('email', $client->preferred_contact);
        $this->assertSame(ClientType::where('key', 'tenant')->value('id'), $client->type_id);
        $this->assertCount(2, $client->needs);
        $this->assertSame($this->area->id, $client->needs->first()->area_id);
        $this->assertCount(1, $client->viewings);

        $viewing = $client->viewings->first();
        $this->assertFalse($viewing->in_person);
        $this->assertSame('pending', $viewing->outcome);
        $this->assertSame($scheduled, $viewing->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame($this->user->id, $viewing->created_by);

        $this->assertDatabaseHas('client_property', ['client_id' => $client->id, 'property_id' => $this->property->id, 'relation' => 'viewed']);

        $actions = ClientAuditLog::where('client_id', $client->id)->pluck('action');
        $this->assertTrue($actions->contains('created'));
        $this->assertSame(2, $actions->filter(fn ($a) => $a === 'need_added')->count());
        $this->assertTrue($actions->contains('viewing_added'));
    }

    public function test_update_syncs_rows_by_id_and_logs_each_change(): void
    {
        $client = $this->makeClient();
        [$firstNeed, $secondNeed] = $client->needs()->orderBy('id')->get();
        $viewing = $client->viewings()->first();

        $this->actingAs($this->user)->put(route('dashboard.clients.update', $client), [
            'name' => $client->name,
            'phone_code' => '+966',
            'phone' => '501234567',
            'needs' => [
                ['id' => $firstNeed->id, 'unit_type_id' => $this->unitType->id, 'city_id' => '', 'area_id' => $this->otherArea->id],
                ['unit_type_id' => '', 'city_id' => $this->city->id, 'area_id' => ''],
            ],
            'viewings' => [
                ['id' => $viewing->id, 'property_id' => $this->property->id, 'scheduled_at' => $viewing->scheduled_at->format('Y-m-d H:i'), 'in_person' => '1', 'outcome' => 'chosen', 'notes' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $client->refresh();

        $this->assertSame('+966', $client->phone_code);
        $this->assertSame('501234567', $client->phone);
        $this->assertCount(2, $client->needs);
        $this->assertDatabaseMissing('client_property_needs', ['id' => $secondNeed->id]);
        $this->assertSame($this->otherArea->id, $firstNeed->refresh()->area_id);

        $viewing->refresh();
        $this->assertSame('chosen', $viewing->outcome);
        $this->assertNotNull($viewing->outcome_at);

        $actions = ClientAuditLog::where('client_id', $client->id)->pluck('action');
        foreach (['updated', 'need_updated', 'need_removed', 'need_added', 'viewing_updated'] as $expected) {
            $this->assertTrue($actions->contains($expected), "missing audit action {$expected}");
        }

        $updated = ClientAuditLog::where('client_id', $client->id)->where('action', 'updated')->latest('id')->first();
        $this->assertSame('+965', $updated->changes['phone_code']['old']);
        $this->assertSame('+966', $updated->changes['phone_code']['new']);
    }

    public function test_viewing_on_a_sold_property_is_rejected(): void
    {
        $this->property->update(['status_id' => PropertyStatus::where('key', 'sold')->value('id')]);

        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل',
            'phone_code' => '+965',
            'phone' => '55000000',
            'viewings' => [
                ['property_id' => $this->property->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i')],
            ],
        ])->assertSessionHasErrors('viewings.0.property_id');

        $this->assertDatabaseCount('client_viewings', 0);
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_viewing_on_a_property_reserved_for_another_client_is_rejected(): void
    {
        $other = Client::create(['name' => 'عميل آخر', 'phone' => '111']);
        $this->actingAs($this->user);
        app(ClientService::class)->reserveProperty($other, $this->property->id);

        $this->post(route('dashboard.clients.store'), [
            'name' => 'عميل',
            'phone_code' => '+965',
            'phone' => '55000001',
            'viewings' => [
                ['property_id' => $this->property->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i')],
            ],
        ])->assertSessionHasErrors('viewings.0.property_id');

        $this->assertStringContainsString('محجوز', session('errors')->first('viewings.0.property_id'));
    }

    public function test_moving_the_schedule_to_the_future_re_arms_the_reminder(): void
    {
        $client = $this->makeClient();
        $viewing = $client->viewings()->first();
        $viewing->forceFill(['reminded_at' => now()])->save();

        $this->actingAs($this->user)->put(route('dashboard.clients.update', $client), [
            'name' => $client->name,
            'phone_code' => '+965',
            'phone' => $client->phone,
            'viewings' => [
                ['id' => $viewing->id, 'property_id' => $this->property->id, 'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i'), 'in_person' => '1', 'outcome' => 'pending'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertNull($viewing->refresh()->reminded_at);
    }

    public function test_area_must_belong_to_the_selected_city(): void
    {
        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل',
            'phone_code' => '+965',
            'phone' => '55000002',
            'needs' => [
                ['unit_type_id' => '', 'city_id' => $this->city->id, 'area_id' => $this->otherArea->id],
            ],
        ])->assertSessionHasErrors('needs.0.area_id');
    }

    public function test_index_filters_by_need_city_and_notes_search(): void
    {
        $inCity = $this->makeClient('عميل حولي', '55100001', notes: 'يفضّل الدور الأرضي');
        $elsewhere = Client::create(['name' => 'عميل الأحمدي', 'phone_code' => '+965', 'phone' => '55100002', 'notes' => 'بدون ملاحظات مهمة']);
        $elsewhere->needs()->create(['area_id' => $this->otherArea->id, 'city_id' => $this->otherArea->city_id]);

        $this->actingAs($this->user)->get(route('dashboard.clients.index', ['city_id' => $this->city->id]))
            ->assertOk()
            ->assertSee('عميل حولي')
            ->assertDontSee('عميل الأحمدي');

        // أقل من الحد الأدنى → يُهمل فلتر الملاحظات
        $this->actingAs($this->user)->get(route('dashboard.clients.index', ['notes_q' => 'ال']))
            ->assertOk()
            ->assertSee('عميل حولي')
            ->assertSee('عميل الأحمدي');

        $this->actingAs($this->user)->get(route('dashboard.clients.index', ['notes_q' => 'الأرضي']))
            ->assertOk()
            ->assertSee('عميل حولي')
            ->assertDontSee('عميل الأحمدي');

        // البحث العام بالهاتف مع مفتاح الدولة
        $this->actingAs($this->user)->get(route('dashboard.clients.index', ['search' => '+965 55100002']))
            ->assertOk()
            ->assertSee('عميل الأحمدي')
            ->assertDontSee('عميل حولي');

        $this->assertSame($inCity->id, Client::where('phone', '55100001')->value('id'));
    }

    public function test_index_shows_target_property_and_row_numbers(): void
    {
        $this->makeClient();

        $this->actingAs($this->user)->get(route('dashboard.clients.index'))
            ->assertOk()
            ->assertSee('العقار المستهدف')
            ->assertSee('ALM-501')
            ->assertSee('عميل محتمل')
            ->assertDontSee('كل الوكلاء');
    }

    private function makeClient(string $name = 'عميل الاختبار', string $phone = '55112233', ?string $notes = null): Client
    {
        $this->actingAs($this->user);

        return app(ClientService::class)->create([
            'name' => $name,
            'phone_code' => '+965',
            'phone' => $phone,
            'notes' => $notes,
            'needs' => [
                ['unit_type_id' => $this->unitType->id, 'city_id' => $this->city->id, 'area_id' => $this->area->id],
                ['unit_type_id' => $this->unitType->id, 'city_id' => null, 'area_id' => null],
            ],
            'viewings' => [
                ['property_id' => $this->property->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'in_person' => '1', 'outcome' => ClientViewing::OUTCOME_PENDING],
            ],
        ]);
    }
}
