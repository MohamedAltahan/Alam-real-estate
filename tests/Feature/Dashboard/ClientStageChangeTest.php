<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientAuditLog;
use App\Models\ClientStage;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\User;
use App\Services\ViewingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** قائمة «حالة الطلب» في صفحة العميل: ربح ⇒ اختيار العقار · خسارة ⇒ كل العقارات «غير مهتم» */
class ClientStageChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Property $flat;

    private Property $villa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->userWith(['clients.view', 'clients.edit']);

        foreach ([['new', 'طلب جديد', 0], ['viewing', 'معاينة العقار', 2], ['closed_won', 'ربح', 3], ['closed_lost', 'خسارة', 4]] as [$key, $name, $order]) {
            ClientStage::firstOrCreate(['key' => $key], ['name' => ['ar' => $name, 'en' => $key], 'color' => '#2E7D5B', 'sort_order' => $order, 'is_active' => true]);
        }

        $this->flat = Property::create(['reference_code' => '601', 'title' => ['ar' => 'شقة الربح', 'en' => 'Flat']]);
        $this->villa = Property::create(['reference_code' => '602', 'title' => ['ar' => 'فيلا أخرى', 'en' => 'Villa']]);

        $this->client = Client::create(['name' => 'عميل الحالة', 'phone_code' => '+965', 'phone' => '55001100', 'stage_id' => $this->stage('new')->id]);
        foreach ([[$this->flat, 'interested'], [$this->flat, 'studying'], [$this->villa, 'cancelled']] as [$property, $outcome]) {
            $this->client->viewings()->create(['property_id' => $property->id, 'scheduled_at' => now()->subDays(2), 'outcome' => $outcome]);
        }
    }

    public function test_the_stage_dropdown_and_won_dialog_show_on_the_client_page_for_editors_only(): void
    {
        $html = $this->actingAs($this->user)->get(route('dashboard.clients.show', $this->client))
            ->assertOk()
            ->assertSee('حالة الطلب:')
            ->assertSee('clientStage(', false)
            ->assertSee(route('dashboard.clients.stage', $this->client), false)
            ->assertSee('تم الربح على أي عقار؟')
            ->assertSee('تأكيد الخسارة')
            ->getContent();

        // «ربح» لا يُعرض كخيار يدوي في قوائم نتيجة المعاينة — يبقى فقط خيار فورم التعديل المخفي
        $this->assertSame(1, substr_count($html, '<option value="won"'));
        $this->assertStringContainsString('<option value="won" :hidden="row.outcome !== \'won\'"', $html);

        $this->actingAs($this->userWith(['clients.view']))->get(route('dashboard.clients.show', $this->client))
            ->assertOk()->assertDontSee('clientStage(', false)->assertSee('طلب جديد');
    }

    public function test_won_marks_the_chosen_property_won_and_the_rest_not_interested(): void
    {
        // بلا اختيار عقار (والعميل له معاينات) أو عقار ليس من معايناته ⇒ خطأ
        $this->patchStage('closed_won')->assertSessionHasErrors('won_property_id');
        $other = Property::create(['reference_code' => '699', 'title' => ['ar' => 'غريب', 'en' => 'X']]);
        $this->patchStage('closed_won', $other->id)->assertSessionHasErrors('won_property_id');
        $this->assertSame('new', $this->client->refresh()->stage->key);

        $this->patchStage('closed_won', $this->flat->id)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->client->refresh();
        $this->assertSame('closed_won', $this->client->stage->key);
        $this->assertNotNull($this->client->won_at);

        $outcomes = $this->client->viewings()->get()->map(fn (ClientViewing $v) => [(int) $v->property_id, $v->outcome])->all();
        $this->assertEqualsCanonicalizing([
            [(int) $this->flat->id, 'won'], [(int) $this->flat->id, 'won'], [(int) $this->villa->id, 'not_interested'],
        ], $outcomes);

        // كل نتيجة تغيّرت تُسجَّل، وتغيير الحالة كذلك
        $this->assertSame(3, ClientAuditLog::where('client_id', $this->client->id)->where('action', 'outcome_updated')->count());
        $this->assertTrue(ClientAuditLog::where('client_id', $this->client->id)->where('action', 'updated')->get()
            ->contains(fn (ClientAuditLog $log) => isset($log->changes['stage_id'])));

        // صفحة العميل تعرض «ربح» كنتيجة، ويمكن تغييرها يدوياً بعد ذلك
        $this->actingAs($this->user)->get(route('dashboard.clients.show', $this->client))->assertOk()->assertSee('<option value="won" selected', false);
    }

    public function test_lost_turns_every_viewing_not_interested(): void
    {
        $this->patchStage('closed_lost')->assertSessionHasNoErrors();

        $this->assertSame('closed_lost', $this->client->refresh()->stage->key);
        $this->assertSame(['not_interested'], $this->client->viewings()->pluck('outcome')->unique()->values()->all());
    }

    public function test_other_stages_change_only_the_stage(): void
    {
        $before = $this->client->viewings()->orderBy('id')->pluck('outcome')->all();

        $this->patchStage('viewing')->assertSessionHasNoErrors();

        $this->assertSame('viewing', $this->client->refresh()->stage->key);
        $this->assertSame($before, $this->client->viewings()->orderBy('id')->pluck('outcome')->all());
    }

    public function test_won_without_viewings_needs_no_property_and_inactive_stages_are_rejected(): void
    {
        $bare = Client::create(['name' => 'بلا معاينات', 'phone_code' => '+965', 'phone' => '55001199']);

        $this->actingAs($this->user)->patch(route('dashboard.clients.stage', $bare), ['stage_id' => $this->stage('closed_won')->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('closed_won', $bare->refresh()->stage->key);

        $old = ClientStage::create(['key' => 'legacy', 'name' => ['ar' => 'قديمة', 'en' => 'Old'], 'is_active' => false]);
        $this->patchStage($old->key)->assertSessionHasErrors('stage_id');
    }

    public function test_won_counts_as_positive_in_the_viewings_conversion_report(): void
    {
        $this->patchStage('closed_won', $this->flat->id)->assertSessionHasNoErrors();

        $kpis = app(ViewingService::class)->conversionReport([])['kpis'];
        $this->assertSame(2, $kpis['interested']);        // معاينتا الربح
        $this->assertSame(1, $kpis['not_interested']);
        $this->assertSame(67, $kpis['rate']);
    }

    public function test_changing_the_stage_requires_edit_permission(): void
    {
        $this->actingAs($this->userWith(['clients.view']))
            ->patch(route('dashboard.clients.stage', $this->client), ['stage_id' => $this->stage('closed_lost')->id])
            ->assertForbidden();

        $this->assertSame('new', $this->client->refresh()->stage->key);
    }

    private function patchStage(string $key, ?int $propertyId = null)
    {
        return $this->actingAs($this->user)->from(route('dashboard.clients.show', $this->client))
            ->patch(route('dashboard.clients.stage', $this->client), array_filter([
                'stage_id' => $this->stage($key)->id,
                'won_property_id' => $propertyId,
            ]));
    }

    private function stage(string $key): ClientStage
    {
        return ClientStage::where('key', $key)->firstOrFail();
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
