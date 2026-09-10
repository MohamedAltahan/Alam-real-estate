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

        // «تم اختيار العقار» يحرّك مرحلة العميل أيضاً (سطر «تعديل» ثانٍ) — نلتقط سطر تعديل البيانات نفسه
        $updated = ClientAuditLog::where('client_id', $client->id)->where('action', 'updated')->get()
            ->first(fn (ClientAuditLog $log) => isset($log->changes['phone_code']));
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

    public function test_property_chosen_by_another_client_is_busy_and_cannot_be_scheduled(): void
    {
        $other = Client::create(['name' => 'المشتري', 'phone' => '123']);
        $other->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => now()->subDay(), 'outcome' => ClientViewing::OUTCOME_CHOSEN]);

        // البحث يعلّم العقار مشغولاً ويذكر من اختاره
        $this->actingAs($this->user)->getJson(route('dashboard.clients.property-lookup', ['q' => '501']))
            ->assertOk()
            ->assertJsonPath('0.busy', 'اختاره العميل المشتري');

        // العميل نفسه لا يُحجب عن عقاره (شاشة التعديل تمرر client)
        $this->actingAs($this->user)->getJson(route('dashboard.clients.property-lookup', ['q' => '501', 'client' => $other->id]))
            ->assertOk()
            ->assertJsonPath('0.busy', null);

        $payload = [
            'name' => 'عميل جديد', 'phone_code' => '+965', 'phone' => '55000000',
            'viewings' => [['property_id' => $this->property->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i')]],
        ];

        // جدولة معاينة لعميل جديد على عقار مشغول تُرفض
        $this->actingAs($this->user)->post(route('dashboard.clients.store'), $payload)
            ->assertSessionHasErrors(['viewings.0.property_id' => 'العقار مشغول: اختاره العميل المشتري.']);
        $this->assertDatabaseCount('clients', 1);

        // لو تراجع عن اختياره يتحرر العقار
        $other->viewings()->update(['outcome' => ClientViewing::OUTCOME_REJECTED]);

        $this->actingAs($this->user)->post(route('dashboard.clients.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clients', 2);
    }

    public function test_pending_viewing_with_another_client_does_not_make_the_property_busy(): void
    {
        $other = Client::create(['name' => 'عميل آخر', 'phone' => '124']);
        $other->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => now()->addDay(), 'outcome' => ClientViewing::OUTCOME_PENDING]);

        $this->actingAs($this->user)->getJson(route('dashboard.clients.property-lookup', ['q' => '501']))
            ->assertJsonPath('0.busy', null);

        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل جديد', 'phone_code' => '+965', 'phone' => '55000000',
            'viewings' => [['property_id' => $this->property->id, 'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i')]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, ClientViewing::where('property_id', $this->property->id)->count());
    }

    public function test_chosen_rental_viewing_requires_a_contract_end_date_and_stores_it(): void
    {
        $rental = Property::create([
            'reference_code' => '777', 'title' => ['ar' => 'شقة للإيجار', 'en' => 'Rental'],
            'status_id' => $this->available->id, 'purpose' => 'rent',
        ]);
        $ends = now()->addYear()->format('Y-m-d');
        $payload = [
            'name' => 'مستأجر', 'phone_code' => '+965', 'phone' => '55000001',
            'viewings' => [['property_id' => $rental->id, 'scheduled_at' => now()->subDay()->format('Y-m-d H:i'), 'outcome' => 'chosen']],
        ];

        // إيجار + تم اختيار العقار بدون تاريخ → مرفوض
        $this->actingAs($this->user)->post(route('dashboard.clients.store'), $payload)
            ->assertSessionHasErrors('viewings.0.contract_ends_at');

        $payload['viewings'][0]['contract_ends_at'] = $ends;
        $this->actingAs($this->user)->post(route('dashboard.clients.store'), $payload)->assertSessionHasNoErrors();

        $viewing = ClientViewing::where('property_id', $rental->id)->firstOrFail();
        $this->assertSame($ends, $viewing->contract_ends_at->format('Y-m-d'));

        // البحث يذكر تاريخ انتهاء العقد مع «مشغول»
        $this->actingAs($this->user)->getJson(route('dashboard.clients.property-lookup', ['q' => '777']))
            ->assertJsonPath('0.busy', "اختاره العميل مستأجر · ينتهي العقد {$ends}");

        // عقار للبيع لا يحتاج التاريخ
        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'مشترٍ', 'phone_code' => '+965', 'phone' => '55000002',
            'viewings' => [['property_id' => $this->property->id, 'scheduled_at' => now()->subDay()->format('Y-m-d H:i'), 'outcome' => 'chosen']],
        ])->assertSessionHasNoErrors();
    }

    public function test_inline_outcome_update_requires_the_contract_end_date_for_rentals_and_keeps_it_on_vacate(): void
    {
        $rental = Property::create([
            'reference_code' => '778', 'title' => ['ar' => 'شقة للإيجار', 'en' => 'Rental'],
            'status_id' => $this->available->id, 'purpose' => 'rent',
        ]);
        $client = Client::create(['name' => 'مستأجر', 'phone' => '125']);
        $viewing = $client->viewings()->create(['property_id' => $rental->id, 'scheduled_at' => now()->subDay()]);
        $ends = now()->addMonths(6)->format('Y-m-d');

        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'chosen'])
            ->assertSessionHasErrors('contract_ends_at');

        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'chosen', 'contract_ends_at' => $ends])
            ->assertSessionHasNoErrors();
        $this->assertSame($ends, $viewing->fresh()->contract_ends_at->format('Y-m-d'));

        // الإخلاء يحرّر العقار ويحتفظ بالتاريخ كسجل
        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'vacated'])->assertSessionHasNoErrors();
        $viewing->refresh();
        $this->assertSame('vacated', $viewing->outcome);
        $this->assertSame($ends, $viewing->contract_ends_at->format('Y-m-d'));

        $this->actingAs($this->user)->getJson(route('dashboard.clients.property-lookup', ['q' => '778']))
            ->assertJsonPath('0.busy', null);
    }

    public function test_daily_check_vacates_viewings_whose_contract_ended(): void
    {
        $rental = Property::create([
            'reference_code' => '779', 'title' => ['ar' => 'شقة للإيجار', 'en' => 'Rental'],
            'status_id' => $this->available->id, 'purpose' => 'rent',
        ]);
        $client = Client::create(['name' => 'مستأجر قديم', 'phone' => '126']);
        $expired = $client->viewings()->create(['property_id' => $rental->id, 'scheduled_at' => now()->subYear(), 'outcome' => 'chosen', 'contract_ends_at' => now()->subDay()->toDateString()]);
        $endsToday = $client->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => now()->subYear(), 'outcome' => 'chosen', 'contract_ends_at' => now()->toDateString()]);
        $current = $client->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => now()->subMonth(), 'outcome' => 'chosen', 'contract_ends_at' => now()->addMonth()->toDateString()]);
        $openEnded = $client->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => now()->subMonth(), 'outcome' => 'chosen']);

        $this->artisan('viewings:vacate-expired')->assertSuccessful();

        $this->assertSame('vacated', $expired->fresh()->outcome);
        $this->assertNotNull($expired->fresh()->outcome_at);
        $this->assertSame('chosen', $endsToday->fresh()->outcome); // اليوم الأخير من العقد ما زال سارياً
        $this->assertSame('chosen', $current->fresh()->outcome);
        $this->assertSame('chosen', $openEnded->fresh()->outcome);
        $this->assertDatabaseHas('client_audit_logs', ['client_id' => $client->id, 'action' => 'outcome_updated']);

        // العقار المخلى لم يعد مشغولاً، ومن أخلى ما زال محسوباً «تم اختيار العقار» في التقرير
        $this->actingAs($this->user)->getJson(route('dashboard.clients.property-lookup', ['q' => '779']))
            ->assertJsonPath('0.busy', null);
        $report = app(\App\Services\ViewingService::class)->conversionReport(['from' => now()->subYears(2)->toDateString(), 'to' => now()->toDateString()]);
        $this->assertSame(4, $report['kpis']['chosen']);
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
            ->assertSee('501')
            ->assertSee('عميل محتمل')
            ->assertDontSee('كل الوكلاء');
    }

    // ===== أثر نتيجة المعاينة على العميل والعقار =====

    public function test_choosing_a_sale_property_sells_it_and_wins_the_client(): void
    {
        $client = $this->makeClient();
        $viewing = $client->viewings()->first();

        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'chosen'])
            ->assertSessionHasNoErrors();

        $this->property->refresh();
        $this->assertSame('sold', $this->property->status->key);
        $this->assertNotNull($this->property->sold_at);

        $client->refresh();
        $this->assertSame('closed_won', $client->stage->key);
        $this->assertNotNull($client->won_at);

        $synced = ClientAuditLog::where('client_id', $client->id)->where('action', 'property_status_synced')->firstOrFail();
        $this->assertSame('متاح', $synced->changes['status']['old']);
        $this->assertSame('مباع', $synced->changes['status']['new']);
        $this->assertSame(Property::class, $synced->subject_type);

        $stageChange = ClientAuditLog::where('client_id', $client->id)->where('action', 'updated')->get()
            ->first(fn (ClientAuditLog $log) => isset($log->changes['stage_id']));
        $this->assertNotNull($stageChange);
        $this->assertArrayNotHasKey('won_at', $stageChange->changes);

        // إعادة حفظ فورم العميل بنفس المعاينة المختارة لا تُحجب بسبب «عقار مباع»
        $this->actingAs($this->user)->put(route('dashboard.clients.update', $client), [
            'name' => $client->name, 'phone_code' => '+965', 'phone' => $client->phone,
            'viewings' => [['id' => $viewing->id, 'property_id' => $this->property->id, 'scheduled_at' => $viewing->scheduled_at->format('Y-m-d H:i'), 'outcome' => 'chosen']],
        ])->assertSessionHasNoErrors();
    }

    public function test_choosing_a_rental_property_wins_the_client_but_keeps_it_available(): void
    {
        $rental = Property::create([
            'reference_code' => '780', 'title' => ['ar' => 'شقة للإيجار', 'en' => 'Rental'],
            'status_id' => $this->available->id, 'purpose' => 'rent',
        ]);
        $client = Client::create(['name' => 'مستأجر', 'phone' => '127']);
        $viewing = $client->viewings()->create(['property_id' => $rental->id, 'scheduled_at' => now()->subDay()]);

        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), [
            'outcome' => 'chosen', 'contract_ends_at' => now()->addYear()->format('Y-m-d'),
        ])->assertSessionHasNoErrors();

        $rental->refresh();
        $this->assertSame('available', $rental->status->key);
        $this->assertNull($rental->sold_at);
        $this->assertSame('closed_won', $client->refresh()->stage->key);
        $this->assertDatabaseMissing('client_audit_logs', ['client_id' => $client->id, 'action' => 'property_status_synced']);
    }

    public function test_reverting_a_chosen_sale_viewing_frees_the_property_but_not_the_client(): void
    {
        $client = $this->makeClient();
        $viewing = $client->viewings()->first();

        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'chosen'])->assertSessionHasNoErrors();
        $this->assertSame('sold', $this->property->refresh()->status->key);

        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $viewing), ['outcome' => 'rejected'])->assertSessionHasNoErrors();

        $this->property->refresh();
        $this->assertSame('available', $this->property->status->key);
        $this->assertNull($this->property->sold_at);
        $this->assertSame('closed_won', $client->refresh()->stage->key); // المرحلة لا تُنزَّل تلقائياً
    }

    public function test_vacating_a_chosen_sale_viewing_frees_the_property(): void
    {
        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'مشترٍ', 'phone_code' => '+965', 'phone' => '55000003',
            'viewings' => [['property_id' => $this->property->id, 'scheduled_at' => now()->subMonth()->format('Y-m-d H:i'), 'outcome' => 'chosen', 'contract_ends_at' => now()->subDay()->format('Y-m-d')]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('sold', $this->property->refresh()->status->key);

        $this->artisan('viewings:vacate-expired')->assertSuccessful();

        $this->assertSame('vacated', ClientViewing::where('property_id', $this->property->id)->value('outcome'));
        $this->assertSame('available', $this->property->refresh()->status->key);
        $this->assertNull($this->property->sold_at);
    }

    public function test_removing_a_chosen_viewing_from_the_client_form_frees_the_property(): void
    {
        $second = Property::create([
            'reference_code' => '502', 'title' => ['ar' => 'شقة ثانية', 'en' => 'Second flat'],
            'status_id' => $this->available->id, 'area_id' => $this->area->id,
        ]);
        $scheduled = now()->addDay()->format('Y-m-d H:i');

        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'مشترٍ', 'phone_code' => '+965', 'phone' => '55000004',
            'viewings' => [
                ['property_id' => $this->property->id, 'scheduled_at' => now()->subDay()->format('Y-m-d H:i'), 'outcome' => 'chosen'],
                ['property_id' => $second->id, 'scheduled_at' => $scheduled, 'outcome' => 'pending'],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame('sold', $this->property->refresh()->status->key);

        $client = Client::where('phone', '55000004')->firstOrFail();
        $kept = $client->viewings()->where('property_id', $second->id)->firstOrFail();

        $this->actingAs($this->user)->put(route('dashboard.clients.update', $client), [
            'name' => $client->name, 'phone_code' => '+965', 'phone' => $client->phone,
            'viewings' => [['id' => $kept->id, 'property_id' => $second->id, 'scheduled_at' => $scheduled, 'outcome' => 'pending']],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('client_viewings', ['client_id' => $client->id, 'property_id' => $this->property->id]);
        $this->assertSame('available', $this->property->refresh()->status->key);
    }

    public function test_a_second_chosen_viewing_keeps_the_property_sold(): void
    {
        $first = Client::create(['name' => 'الأول', 'phone' => '128']);
        $secondClient = Client::create(['name' => 'الثاني', 'phone' => '129']);
        $a = $first->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => now()->subDay(), 'outcome' => ClientViewing::OUTCOME_CHOSEN]);
        $b = $secondClient->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => now()->subDay(), 'outcome' => ClientViewing::OUTCOME_CHOSEN]);
        $this->property->update(['status_id' => PropertyStatus::where('key', 'sold')->value('id')]);

        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $a), ['outcome' => 'rejected'])->assertSessionHasNoErrors();
        $this->assertSame('sold', $this->property->refresh()->status->key);

        $this->actingAs($this->user)->patch(route('dashboard.viewings.outcome', $b), ['outcome' => 'rejected'])->assertSessionHasNoErrors();
        $this->assertSame('available', $this->property->refresh()->status->key);
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
