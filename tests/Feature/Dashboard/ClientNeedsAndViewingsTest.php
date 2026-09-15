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
use Illuminate\Support\Facades\Schema;
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
        PropertyStatus::create(['name' => ['ar' => 'مباع', 'en' => 'Sold'], 'key' => 'sold']);

        $this->property = Property::create([
            'reference_code' => '501', 'title' => ['ar' => 'شقة السالمية', 'en' => 'Salmiya Flat'],
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
                ['category' => 'residential', 'unit_type_id' => $this->unitType->id, 'city_id' => $this->city->id, 'area_id' => $this->area->id, 'area_size' => '120.5', 'rooms' => '3'],
                ['unit_type_id' => $this->unitType->id, 'city_id' => '', 'area_id' => ''],
                ['unit_type_id' => '', 'city_id' => '', 'area_id' => '', 'category' => '', 'area_size' => '', 'rooms' => ''], // سطر فارغ يُهمل
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
        $first = $client->needs->first();
        $this->assertSame($this->area->id, $first->area_id);
        $this->assertSame('residential', $first->category);
        $this->assertSame(120.5, (float) $first->area_size);
        $this->assertSame(3, $first->rooms);
        $this->assertNull($client->needs->last()->rooms);
        $this->assertCount(1, $client->viewings);

        $viewing = $client->viewings->first();
        $this->assertFalse($viewing->in_person);
        $this->assertSame('pending', $viewing->outcome);
        $this->assertSame($scheduled, $viewing->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame($this->user->id, $viewing->created_by);

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
                ['id' => $viewing->id, 'property_id' => $this->property->id, 'scheduled_at' => $viewing->scheduled_at->format('Y-m-d H:i'), 'in_person' => '1', 'outcome' => 'interested', 'notes' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $client->refresh();

        $this->assertSame('+966', $client->phone_code);
        $this->assertSame('501234567', $client->phone);
        $this->assertCount(2, $client->needs);
        $this->assertDatabaseMissing('client_property_needs', ['id' => $secondNeed->id]);
        $this->assertSame($this->otherArea->id, $firstNeed->refresh()->area_id);

        $viewing->refresh();
        $this->assertSame('interested', $viewing->outcome);
        $this->assertNotNull($viewing->outcome_at);

        $actions = ClientAuditLog::where('client_id', $client->id)->pluck('action');
        foreach (['updated', 'need_updated', 'need_removed', 'need_added', 'viewing_updated'] as $expected) {
            $this->assertTrue($actions->contains($expected), "missing audit action {$expected}");
        }

        // «مهتم» يحرّك مرحلة العميل أيضاً (سطر «تعديل» ثانٍ) — نلتقط سطر تعديل البيانات نفسه
        $updated = ClientAuditLog::where('client_id', $client->id)->where('action', 'updated')->get()
            ->first(fn (ClientAuditLog $log) => isset($log->changes['phone_code']));
        $this->assertSame('+965', $updated->changes['phone_code']['old']);
        $this->assertSame('+966', $updated->changes['phone_code']['new']);
    }

    public function test_viewing_on_a_sold_property_is_allowed_and_the_lookup_only_badges_it(): void
    {
        $this->property->update(['status_id' => PropertyStatus::where('key', 'sold')->value('id')]);

        $this->actingAs($this->user)->getJson(route('dashboard.clients.property-lookup', ['q' => '501']))->assertOk()
            ->assertJsonPath('0.badge', 'مباع')->assertJsonMissingPath('0.blocked');

        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل',
            'phone_code' => '+965',
            'phone' => '55000000',
            'viewings' => [
                ['property_id' => $this->property->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i')],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('client_viewings', 1);
        $this->assertSame('sold', $this->property->fresh()->status->key);

        // العقار المتاح بلا شارة
        $this->property->update(['status_id' => $this->available->id]);
        $this->actingAs($this->user)->getJson(route('dashboard.clients.property-lookup', ['q' => '501']))->assertOk()->assertJsonPath('0.badge', null);
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

    public function test_index_filters_by_need_category_rooms_and_area_range_on_the_same_row(): void
    {
        $shop = UnitType::create(['name' => ['ar' => 'محل', 'en' => 'Shop'], 'category' => 'commercial']);

        $small = Client::create(['name' => 'عميل صغير', 'phone_code' => '+965', 'phone' => '55200001']);
        $small->needs()->create(['category' => 'residential', 'unit_type_id' => $this->unitType->id, 'city_id' => $this->city->id, 'area_size' => 80, 'rooms' => 2]);

        $big = Client::create(['name' => 'عميل كبير', 'phone_code' => '+965', 'phone' => '55200002']);
        $big->needs()->create(['category' => 'residential', 'unit_type_id' => $this->unitType->id, 'city_id' => $this->city->id, 'area_size' => 150, 'rooms' => 3]);
        // سطر ثانٍ لنفس العميل: تجاري في محافظة أخرى بلا غرف
        $big->needs()->create(['category' => 'commercial', 'unit_type_id' => $shop->id, 'city_id' => $this->otherArea->city_id, 'area_size' => 40]);

        $index = fn (array $q) => $this->actingAs($this->user)->get(route('dashboard.clients.index', $q))->assertOk();

        $index(['category' => 'commercial'])->assertSee('عميل كبير')->assertDontSee('عميل صغير');
        $index(['rooms' => 2])->assertSee('عميل صغير')->assertDontSee('عميل كبير');
        $index(['area_from' => 100, 'area_to' => 200])->assertSee('عميل كبير')->assertDontSee('عميل صغير');
        $index(['area_to' => 100])->assertSee('عميل صغير')->assertSee('عميل كبير'); // السطر التجاري 40 م²

        // الشروط تُطبَّق على سطر الاحتياج نفسه: تجاري + 3 غرف لا يطابق أي سطر عند «عميل كبير»
        $index(['category' => 'commercial', 'rooms' => 3])->assertDontSee('عميل كبير')->assertDontSee('عميل صغير');
        $index(['category' => 'residential', 'rooms' => 3])->assertSee('عميل كبير')->assertDontSee('عميل صغير');
        $index(['unit_type_id' => $shop->id])->assertSee('عميل كبير')->assertDontSee('عميل صغير');
    }

    public function test_need_unit_type_must_match_its_category(): void
    {
        $shop = UnitType::create(['name' => ['ar' => 'محل', 'en' => 'Shop'], 'category' => 'commercial']);

        $payload = [
            'name' => 'عميل', 'phone_code' => '+965', 'phone' => '55300001',
            'needs' => [['category' => 'residential', 'unit_type_id' => $shop->id, 'area_size' => '-5', 'rooms' => '1.5']],
        ];

        $this->actingAs($this->user)->post(route('dashboard.clients.store'), $payload)
            ->assertSessionHasErrors(['needs.0.unit_type_id', 'needs.0.area_size', 'needs.0.rooms']);

        $payload['needs'][0] = ['category' => 'commercial', 'unit_type_id' => $shop->id, 'area_size' => '45', 'rooms' => ''];
        $this->actingAs($this->user)->post(route('dashboard.clients.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();

        $need = Client::where('phone', '55300001')->firstOrFail()->needs()->firstOrFail();
        $this->assertSame('commercial', $need->category);
        $this->assertSame(45.0, (float) $need->area_size);
        $this->assertNull($need->rooms);
        $this->assertSame('تجاري · محل · 45 م²', $need->describe());
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
            ->assertSee('501')
            ->assertSee('عميل محتمل')
            ->assertDontSee('كل الوكلاء');
    }

    // ===== أثر نتيجة المعاينة على العميل (حالة العقار يدوية دائماً) =====

    public function test_interested_outcome_wins_the_client_and_leaves_property_status_alone(): void
    {
        $client = $this->makeClient();
        $viewing = $client->viewings()->first();

        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'interested'])
            ->assertSessionHasNoErrors();

        $this->property->refresh();
        $this->assertSame('available', $this->property->status->key);
        $this->assertNull($this->property->sold_at);

        $client->refresh();
        $this->assertSame('closed_won', $client->stage->key);
        $this->assertNotNull($client->won_at);
        $this->assertNotNull($viewing->fresh()->outcome_at);

        $this->assertDatabaseMissing('client_audit_logs', ['client_id' => $client->id, 'action' => 'property_status_synced']);

        $stageChange = ClientAuditLog::where('client_id', $client->id)->where('action', 'updated')->get()
            ->first(fn (ClientAuditLog $log) => isset($log->changes['stage_id']));
        $this->assertNotNull($stageChange);

        // التراجع عن «مهتم» لا يُنزّل المرحلة
        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'not_interested'])->assertSessionHasNoErrors();
        $this->assertSame('closed_won', $client->refresh()->stage->key);
        $this->assertSame('available', $this->property->refresh()->status->key);
    }

    public function test_two_clients_can_both_be_interested_in_the_same_property(): void
    {
        $first = $this->makeClient('الأول', '55000001');
        $first->viewings()->update(['outcome' => ClientViewing::OUTCOME_INTERESTED]);

        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'الثاني', 'phone_code' => '+965', 'phone' => '55000002',
            'viewings' => [['property_id' => $this->property->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'outcome' => 'interested']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, ClientViewing::where('property_id', $this->property->id)->where('outcome', 'interested')->count());
        $this->assertSame('available', $this->property->refresh()->status->key);

        $this->actingAs($this->user)->getJson(route('dashboard.clients.property-lookup', ['q' => '501']))->assertOk()
            ->assertJsonPath('0.badge', null)
            ->assertJsonMissingPath('0.busy');
    }

    public function test_all_five_outcomes_are_accepted_and_legacy_values_are_rejected(): void
    {
        $client = $this->makeClient();
        $viewing = $client->viewings()->first();

        foreach (['studying', 'cancelled', 'not_interested', 'pending'] as $outcome) {
            $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => $outcome])->assertSessionHasNoErrors();
            $this->assertSame($outcome, $viewing->fresh()->outcome);
        }

        $this->assertNull($viewing->fresh()->outcome_at);
        $this->assertSame('new', $client->refresh()->stage?->key ?? 'new');

        foreach (['chosen', 'rejected', 'vacated'] as $legacy) {
            $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => $legacy])->assertSessionHasErrors('outcome');
        }

        // بلا حقل تاريخ انتهاء العقد في الفورم ولا في القاعدة
        $this->assertFalse(Schema::hasColumn('client_viewings', 'contract_ends_at'));
        $this->actingAs($this->user)->get(route('dashboard.clients.show', $client))->assertOk()->assertDontSee('انتهاء العقد');
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
