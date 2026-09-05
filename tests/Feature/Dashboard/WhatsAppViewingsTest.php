<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Models\WhatsappInstance;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\WhatsAppTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WhatsAppViewingsTest extends TestCase
{
    use RefreshDatabase;

    private PropertyOwner $owner;

    private Property $property;

    private Client $client;

    private ClientViewing $viewing;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.khabeersoft.api_key' => 'wag_test_key', 'services.khabeersoft.base_url' => 'https://gateway.test/api/v1']);

        $agent = User::factory()->create(['name' => 'دلال', 'phone' => '+96599000005', 'is_agent' => true]);

        $this->owner = PropertyOwner::create(['name' => 'أبو خالد', 'phone_code' => '+965', 'phone' => '55110000']);
        $this->owner->contacts()->create(['phone_code' => '+965', 'phone' => '55110000', 'role' => 'المالك', 'name' => 'أبو خالد', 'sort_order' => 0]);
        $this->owner->contacts()->create(['phone_code' => '+965', 'phone' => '55220000', 'role' => 'الوكيل', 'name' => 'سالم', 'sort_order' => 1]);

        $this->property = Property::create(['reference_code' => '12', 'title' => ['ar' => 'شقة السالمية', 'en' => 'Salmiya flat'], 'owner_id' => $this->owner->id]);
        $this->client = Client::create(['name' => 'عميل المعاينة', 'phone_code' => '+965', 'phone' => '66000001', 'agent_id' => $agent->id]);
        $this->viewing = $this->client->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => now()->addDay()->setTime(17, 0)]);
    }

    public function test_whatsapp_screen_and_status_are_permission_gated(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard.whatsapp.index'))->assertForbidden();

        $viewer = $this->userWith(['whatsapp.view', 'notifications.view']);
        $this->actingAs($viewer)->get(route('dashboard.whatsapp.index'))
            ->assertOk()
            ->assertSee('ربط رقم واتساب المكتب')
            ->assertSee('لم يُربط رقم واتساب بعد')
            ->assertDontSee('إنشاء الجلسة وعرض QR');

        $this->actingAs($viewer)->post(route('dashboard.whatsapp.connect'), ['name' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->put(route('dashboard.whatsapp.templates.update', WhatsAppTemplates::KIND_OWNER), ['body' => 'x'])->assertForbidden();

        // أيقونة الحالة بجوار الجرس: رابط سليم لمن يملك الصلاحية، وبلا رابط لغيره
        $this->actingAs($viewer)->get(route('dashboard.profile.edit'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard.whatsapp.index').'"', false);

        $this->actingAs($this->userWith(['notifications.view']))->get(route('dashboard.profile.edit'))
            ->assertOk()
            ->assertSee('data-whatsapp-status', false)
            ->assertDontSee(route('dashboard.whatsapp.index'));

        // الاستطلاع يحمل حالة واتساب للأيقونة
        $this->actingAs($viewer)->get(route('dashboard.notifications.poll'))
            ->assertOk()
            ->assertJsonPath('whatsapp.connected', false)
            ->assertJsonPath('whatsapp.status', 'none')
            ->assertJsonPath('whatsapp.tone', 'muted');
    }

    public function test_connecting_creates_a_gateway_session_and_qr_polling_reports_connection(): void
    {
        Http::fake(function (ClientRequest $request) {
            return match (true) {
                $request->method() === 'GET' && str_ends_with($request->url(), '/instances') => Http::response([], 200),
                $request->method() === 'POST' && str_ends_with($request->url(), '/instances') => Http::response(['id' => 5, 'status' => 'waiting_scan', 'qr_url' => '/api/v1/instances/5/qr'], 201),
                str_ends_with($request->url(), '/instances/5/qr') => Http::response(WhatsappInstance::query()->exists() && WhatsappInstance::first()->checked_at?->lt(now()->subSecond())
                    ? ['status' => 'connected', 'phone' => '96599000000']
                    : ['status' => 'waiting_scan', 'qr_base64' => 'iVBORw0KGgo='], 200),
                str_ends_with($request->url(), '/instances/5') => Http::response(['id' => 5, 'name' => 'رقم المكتب', 'status' => 'connected', 'phone' => '96599000000'], 200),
                default => Http::response(['error' => 'unexpected '.$request->url()], 500),
            };
        });

        $admin = $this->userWith(['whatsapp.view', 'whatsapp.edit', 'notifications.view']);

        $this->actingAs($admin)->post(route('dashboard.whatsapp.connect'), ['name' => 'رقم المكتب'])
            ->assertRedirect(route('dashboard.whatsapp.index'))
            ->assertSessionHas('success');

        $instance = WhatsappInstance::firstOrFail();
        $this->assertSame('5', $instance->external_id);
        $this->assertSame('qr', $instance->status);
        Http::assertSent(fn (ClientRequest $r) => $r->hasHeader('x-api-key', 'wag_test_key') && $r->method() === 'POST' && $r['name'] === 'رقم المكتب');

        // أول استطلاع: رمز QR بانتظار المسح
        $this->actingAs($admin)->get(route('dashboard.whatsapp.qr'))
            ->assertOk()
            ->assertJsonPath('status', 'qr')
            ->assertJsonPath('qr', 'data:image/png;base64,iVBORw0KGgo=');

        // بعد المسح: متصل — والأيقونة تصبح خضراء في الاستطلاع الدوري
        $instance->forceFill(['checked_at' => now()->subMinute()])->save();

        $this->actingAs($admin)->get(route('dashboard.whatsapp.qr'))
            ->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('phone', '96599000000')
            ->assertJsonPath('qr', null);

        $this->actingAs($admin)->get(route('dashboard.notifications.poll'))
            ->assertOk()
            ->assertJsonPath('whatsapp.connected', true)
            ->assertJsonPath('whatsapp.tone', 'success')
            ->assertJsonPath('whatsapp.phone', '96599000000');

        $this->actingAs($admin)->get(route('dashboard.whatsapp.index'))->assertOk()->assertSee('واتساب متصل');
    }

    public function test_status_poll_marks_the_number_disconnected_when_the_gateway_rejects_the_session(): void
    {
        WhatsappInstance::create(['external_id' => '9', 'name' => 'المكتب', 'status' => 'connected', 'phone' => '96599000000', 'connected_at' => now()]);
        Http::fake(['*' => Http::response(['error' => 'Instance not found'], 404)]);

        $this->actingAs($this->userWith(['notifications.view']))->get(route('dashboard.notifications.poll'))
            ->assertOk()
            ->assertJsonPath('whatsapp.connected', false)
            ->assertJsonPath('whatsapp.tone', 'danger');

        $this->assertSame('disconnected', WhatsappInstance::first()->status);
    }

    public function test_owner_message_offers_every_owner_number_and_marks_the_viewing_when_queued(): void
    {
        $this->connectedInstance();
        Http::fake(['*/messages/send' => Http::response(['id' => 123, 'status' => 'queued', 'delay_ms' => 2400], 202)]);
        $user = $this->userWith(['clients.view', 'clients.edit']);

        // زر الإرسال يحمل كل أرقام المالك بصفاتها ونص القالب معبّأً
        $payload = app(WhatsAppService::class)->sendPayload($this->viewing->load('property.owner.contacts', 'client.agent'), WhatsAppTemplates::KIND_OWNER);
        $this->assertSame(['96555110000', '96555220000'], array_column($payload['recipients'], 'phone'));
        $this->assertSame('الوكيل · سالم', $payload['recipients'][1]['label']);
        $this->assertStringContainsString('السلام عليكم سالم', $payload['bodies']['96555220000']);
        $this->assertStringContainsString('عميل المعاينة', $payload['bodies']['96555220000']);
        $this->assertStringContainsString('+965 66000001', $payload['bodies']['96555220000']);
        $this->assertStringContainsString('12 — شقة السالمية', $payload['bodies']['96555220000']);

        $this->actingAs($user)->get(route('dashboard.clients.show', $this->client))
            ->assertOk()
            ->assertSee('إبلاغ المالك')
            ->assertSee('data-wa-send=', false);

        $this->actingAs($user)->from(route('dashboard.clients.show', $this->client))
            ->post(route('dashboard.viewings.whatsapp', $this->viewing), [
                'kind' => WhatsAppTemplates::KIND_OWNER,
                'to' => '96555220000',
                'body' => "موعد معاينة للعقار 12\nالعميل: عميل المعاينة",
            ])
            ->assertRedirect(route('dashboard.clients.show', $this->client))
            ->assertSessionHas('success');

        Http::assertSent(fn (ClientRequest $r) => $r['instance_id'] === 9 && $r['to'] === '96555220000' && $r['type'] === 'text' && str_contains($r['text'], 'عميل المعاينة'));

        $message = WhatsappMessage::firstOrFail();
        $this->assertSame(WhatsappMessage::QUEUED, $message->status);
        $this->assertSame('123', $message->provider_id);
        $this->assertSame('الوكيل · سالم — +965 55220000', $message->to_label);
        $this->assertSame($user->id, $message->sent_by);

        $this->viewing->refresh();
        $this->assertNotNull($this->viewing->owner_notified_at);
        $this->assertNull($this->viewing->client_followed_up_at);
        $this->assertDatabaseHas('client_audit_logs', ['client_id' => $this->client->id, 'action' => 'whatsapp_sent', 'user_id' => $user->id]);

        $this->actingAs($user)->get(route('dashboard.clients.show', $this->client))->assertOk()->assertSee('أُبلغ المالك');
        $this->actingAs($this->userWith(['whatsapp.view']))->get(route('dashboard.whatsapp.index', ['tab' => 'messages']))
            ->assertOk()->assertSee('الوكيل · سالم')->assertSee('أُرسلت');
    }

    public function test_targeted_properties_panel_carries_the_outcome_select_and_whatsapp_buttons(): void
    {
        $this->connectedInstance();
        $editor = $this->userWith(['clients.view', 'clients.edit']);

        // القائمة لم تعد تحمل الحمولات — النافذة تُجلب عند الفتح
        $this->actingAs($editor)->get(route('dashboard.clients.index'))
            ->assertOk()
            ->assertSee('openTargets(', false)
            ->assertDontSee('data-wa-send=', false);

        $this->actingAs($editor)->get(route('dashboard.clients.viewings', $this->client))
            ->assertOk()
            ->assertSee('12')
            ->assertSee('شقة السالمية')
            ->assertSee('إبلاغ المالك')
            ->assertSee('متابعة العميل')
            ->assertSee('data-wa-send=', false)
            ->assertSee(route('dashboard.viewings.outcome', $this->viewing), false)
            ->assertSee('saveOutcome($event)', false);

        // بلا صلاحية التعديل: لا أزرار إرسال ولا تغيير للنتيجة
        $this->actingAs($this->userWith(['clients.view']))->get(route('dashboard.clients.viewings', $this->client))
            ->assertOk()
            ->assertSee('قيد الانتظار')
            ->assertDontSee('data-wa-send=', false)
            ->assertDontSee('saveOutcome($event)', false);

        $this->actingAs(User::factory()->create())->get(route('dashboard.clients.viewings', $this->client))->assertForbidden();
    }

    public function test_gateway_failure_logs_the_attempt_without_marking_the_viewing(): void
    {
        $this->connectedInstance();
        Http::fake(['*/messages/send' => Http::response(['error' => 'Hourly limit reached'], 429)]);
        $user = $this->userWith(['clients.view', 'clients.edit']);

        $this->actingAs($user)->post(route('dashboard.viewings.whatsapp', $this->viewing), [
            'kind' => WhatsAppTemplates::KIND_OWNER, 'to' => '96555110000', 'body' => 'نص',
        ])->assertRedirect()->assertSessionHas('error');

        $message = WhatsappMessage::firstOrFail();
        $this->assertSame(WhatsappMessage::FAILED, $message->status);
        $this->assertSame('Hourly limit reached', $message->error);
        $this->assertNull($this->viewing->fresh()->owner_notified_at);
        $this->assertDatabaseMissing('client_audit_logs', ['action' => 'whatsapp_sent']);
    }

    public function test_sending_requires_a_connected_number_and_a_listed_recipient(): void
    {
        Http::fake();
        $user = $this->userWith(['clients.view', 'clients.edit']);

        // رقم من خارج القائمة
        $this->actingAs($user)->post(route('dashboard.viewings.whatsapp', $this->viewing), [
            'kind' => WhatsAppTemplates::KIND_OWNER, 'to' => '96599999999', 'body' => 'نص',
        ])->assertSessionHasErrors('to');

        // بلا رقم مربوط: تُسجَّل محاولة فاشلة بلا علامة
        $this->actingAs($user)->post(route('dashboard.viewings.whatsapp', $this->viewing), [
            'kind' => WhatsAppTemplates::KIND_OWNER, 'to' => '96555110000', 'body' => 'نص',
        ])->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(WhatsappMessage::FAILED, WhatsappMessage::firstOrFail()->status);
        $this->assertStringContainsString('غير متصل', WhatsappMessage::first()->error);

        // بلا صلاحية تعديل العملاء
        $this->actingAs($this->userWith(['clients.view']))->post(route('dashboard.viewings.whatsapp', $this->viewing), [
            'kind' => WhatsAppTemplates::KIND_OWNER, 'to' => '96555110000', 'body' => 'نص',
        ])->assertForbidden();
    }

    public function test_client_follow_up_goes_to_the_client_only_after_the_outcome_is_recorded(): void
    {
        $this->connectedInstance();
        Http::fake(['*/messages/send' => Http::response(['id' => 7, 'status' => 'queued'], 202)]);
        $user = $this->userWith(['clients.view', 'clients.edit']);

        $this->actingAs($user)->post(route('dashboard.viewings.whatsapp', $this->viewing), [
            'kind' => WhatsAppTemplates::KIND_CLIENT, 'to' => '96566000001', 'body' => 'متابعة',
        ])->assertSessionHasErrors('kind');

        $this->viewing->forceFill(['outcome' => ClientViewing::OUTCOME_CHOSEN, 'outcome_at' => now()])->save();

        $payload = app(WhatsAppService::class)->sendPayload($this->viewing->fresh()->load('client.agent', 'property'), WhatsAppTemplates::KIND_CLIENT);
        $this->assertSame([['phone' => '96566000001', 'name' => 'عميل المعاينة', 'label' => 'العميل · عميل المعاينة', 'display' => '+965 66000001']], $payload['recipients']);
        $this->assertStringContainsString('اختار العقار', $payload['bodies']['96566000001']);

        $this->actingAs($user)->post(route('dashboard.viewings.whatsapp', $this->viewing), [
            'kind' => WhatsAppTemplates::KIND_CLIENT, 'to' => '96566000001', 'body' => 'متابعة',
        ])->assertSessionHas('success');

        $this->assertNotNull($this->viewing->fresh()->client_followed_up_at);
        Http::assertSent(fn (ClientRequest $r) => $r['to'] === '96566000001');
    }

    public function test_templates_are_editable_and_used_for_new_messages(): void
    {
        $admin = $this->userWith(['whatsapp.view', 'whatsapp.edit']);

        $this->actingAs($admin)->get(route('dashboard.whatsapp.index', ['tab' => 'templates']))
            ->assertOk()->assertSee('{اسم_العميل}')->assertSee('حفظ القالب');

        $this->actingAs($admin)->put(route('dashboard.whatsapp.templates.update', WhatsAppTemplates::KIND_OWNER), [
            'body' => "معاينة {رقم_العقار} للعميل {اسم_العميل} يوم {موعد_المعاينة} — {اسم_المستلم}",
        ])->assertRedirect(route('dashboard.whatsapp.index', ['tab' => 'templates']))->assertSessionHas('success');

        $body = app(WhatsAppService::class)
            ->sendPayload($this->viewing->load('property.owner.contacts', 'client.agent'), WhatsAppTemplates::KIND_OWNER)['bodies']['96555110000'];

        $this->assertSame('معاينة 12 للعميل عميل المعاينة يوم '.$this->viewing->scheduled_at->format('Y-m-d — h:i A').' — أبو خالد', $body);

        $this->actingAs($admin)->put(route('dashboard.whatsapp.templates.update', 'nope'), ['body' => 'x'])->assertNotFound();
    }

    public function test_viewings_whatsapp_report_counts_both_marks(): void
    {
        $reporter = $this->userWith(['reports.view']);

        $this->viewing->forceFill(['owner_notified_at' => now(), 'client_followed_up_at' => now(), 'outcome' => 'chosen'])->save();
        $other = Client::create(['name' => 'عميل بلا رسائل', 'phone_code' => '+965', 'phone' => '66000002']);
        $other->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => now()->addDays(2)]);

        $this->actingAs($reporter)->get(route('dashboard.reports.viewings'))
            ->assertOk()
            ->assertSee('واتساب المعاينات')
            ->assertSee('عميل المعاينة')
            ->assertSee('عميل بلا رسائل')
            ->assertSee('لم يُرسل')
            ->assertSee('بانتظار النتيجة');

        $this->actingAs($reporter)->get(route('dashboard.reports.viewings', ['state' => 'complete']))
            ->assertOk()->assertSee('عميل المعاينة')->assertDontSee('عميل بلا رسائل');

        $this->actingAs($reporter)->get(route('dashboard.reports.viewings', ['state' => 'missing_owner']))
            ->assertOk()->assertSee('عميل بلا رسائل')->assertDontSee('عميل المعاينة');

        $this->actingAs(User::factory()->create())->get(route('dashboard.reports.viewings'))->assertForbidden();
    }

    private function connectedInstance(): WhatsappInstance
    {
        return WhatsappInstance::create(['external_id' => '9', 'name' => 'المكتب', 'status' => 'connected', 'phone' => '96599000000', 'connected_at' => now(), 'checked_at' => now()]);
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
