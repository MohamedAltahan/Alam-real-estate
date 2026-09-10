<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\WhatsAppService;
use App\Support\WhatsAppTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** حالة تسليم رسائل واتساب (أُرسلت · وصلت · قُرئت): الويب هوك، الاستعلام الدوري، والعرض في السجل */
class WhatsAppMessageStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.khabeersoft.api_key' => 'wag_test_key',
            'services.khabeersoft.base_url' => 'https://gateway.test/api/v1',
            'services.khabeersoft.webhook_secret' => 'whsec_test',
        ]);
    }

    public function test_webhook_moves_the_message_forward_and_never_backwards(): void
    {
        $message = $this->message();

        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 123, 'status' => 'delivered', 'wa_message_id' => '3EB0ABC'], 'sent_at' => '2026-09-09T10:00:00Z'])
            ->assertOk()
            ->assertJson(['ok' => true, 'handled' => true]);

        $message->refresh();
        $this->assertSame(WhatsappMessage::DELIVERED, $message->status);
        $this->assertSame('3EB0ABC', $message->wa_message_id);
        $this->assertNotNull($message->delivered_at);
        $this->assertNotNull($message->sent_at); // بلوغ «وصلت» يعني أنها أُرسلت أيضاً
        $this->assertNull($message->read_at);

        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 123, 'status' => 'read'], 'sent_at' => '2026-09-09T10:05:00Z'])->assertOk();
        $this->assertSame(WhatsappMessage::READ, $message->refresh()->status);
        $this->assertNotNull($message->read_at);

        // حدث متأخر (أُرسلت) لا يُرجع الحالة للخلف
        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 123, 'status' => 'sent']])->assertOk();
        $this->assertSame(WhatsappMessage::READ, $message->refresh()->status);

        // الفشل يُطبَّق دائماً مع نص الخطأ
        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 123, 'status' => 'failed', 'error' => 'Number not on WhatsApp']])->assertOk();
        $message->refresh();
        $this->assertSame(WhatsappMessage::FAILED, $message->status);
        $this->assertSame('Number not on WhatsApp', $message->error);

        // الفشل نهائي: حدث «وصلت» متأخر لا يُحييها ولا يمسح الخطأ
        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 123, 'status' => 'delivered']])->assertOk();
        $message->refresh();
        $this->assertSame(WhatsappMessage::FAILED, $message->status);
        $this->assertSame('Number not on WhatsApp', $message->error);
    }

    public function test_a_late_failure_clears_the_viewing_mark_and_is_audited(): void
    {
        $viewing = $this->viewing();
        $this->message(['viewing_id' => $viewing->id, 'client_id' => $viewing->client_id]);

        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 123, 'status' => 'failed', 'error' => 'Number not on WhatsApp']])->assertOk();

        $this->assertNull($viewing->fresh()->owner_notified_at);
        $log = \App\Models\ClientAuditLog::where('client_id', $viewing->client_id)->where('action', 'whatsapp_failed')->firstOrFail();
        $this->assertSame('Number not on WhatsApp', $log->changes['whatsapp_error']['new']);
        $this->assertSame('تفاصيل المعاينة للمالك', $log->changes['whatsapp_kind']['new']);

        // الاستطلاع الدوري يمرّ بالمسار نفسه
        $again = $this->viewing();
        $this->message(['provider_id' => '321', 'viewing_id' => $again->id, 'client_id' => $again->client_id]);
        Http::fake(['*/messages/321' => Http::response(['status' => 'failed', 'error' => 'Blocked'])]);

        app(WhatsAppService::class)->refreshMessageStatuses(force: true);

        $this->assertNull($again->fresh()->owner_notified_at);
    }

    public function test_the_mark_survives_when_another_message_of_the_same_kind_succeeded(): void
    {
        $viewing = $this->viewing();
        $this->message(['provider_id' => '123', 'viewing_id' => $viewing->id, 'client_id' => $viewing->client_id]);
        $sent = $this->message(['provider_id' => '124', 'status' => WhatsappMessage::SENT, 'viewing_id' => $viewing->id, 'client_id' => $viewing->client_id]);
        // رسالة من النوع الآخر لا تُحتسب
        $this->message(['provider_id' => '125', 'kind' => WhatsAppTemplates::KIND_CLIENT, 'status' => WhatsappMessage::SENT, 'viewing_id' => $viewing->id, 'client_id' => $viewing->client_id]);

        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 123, 'status' => 'failed']])->assertOk();

        $mark = $viewing->fresh()->owner_notified_at;
        $this->assertNotNull($mark);
        $this->assertSame($sent->created_at->toDateTimeString(), $mark->toDateTimeString());

        // فشل الرسالة الناجحة الوحيدة المتبقية يمسح العلامة
        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 124, 'status' => 'failed']])->assertOk();
        $this->assertNull($viewing->fresh()->owner_notified_at);
    }

    public function test_webhook_rejects_bad_signatures_and_ignores_unknown_messages_or_events(): void
    {
        $message = $this->message();

        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 123, 'status' => 'read']], secret: 'wrong')->assertStatus(401);
        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 123, 'status' => 'read']], secret: null)->assertStatus(401);
        $this->assertSame(WhatsappMessage::QUEUED, $message->refresh()->status);

        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 999, 'status' => 'read']])->assertOk()->assertJson(['handled' => false]);
        $this->webhook(['event' => 'instance.status', 'data' => ['instance_id' => 5, 'status' => 'connected']])->assertOk()->assertJson(['handled' => false]);

        config(['services.khabeersoft.webhook_secret' => null]);
        $this->webhook(['event' => 'message.status', 'data' => ['message_id' => 123, 'status' => 'read']], secret: 'whsec_test')->assertStatus(503);
    }

    public function test_pending_messages_are_refreshed_from_the_gateway_and_final_ones_are_skipped(): void
    {
        Http::fake([
            '*/messages/123' => Http::response(['data' => [
                'id' => 123, 'status' => 'read', 'wa_message_id' => '3EB0DEF',
                'sent_at' => '2026-09-09T09:00:00Z', 'delivered_at' => '2026-09-09T09:00:05Z', 'read_at' => '2026-09-09T09:30:00Z',
            ]]),
            '*/messages/124' => Http::response(['id' => 124, 'status' => 'sent']),
        ]);

        $queued = $this->message(['provider_id' => '123']);
        $sent = $this->message(['provider_id' => '124', 'status' => WhatsappMessage::SENT]);
        $read = $this->message(['provider_id' => '125', 'status' => WhatsappMessage::READ]);
        $failed = $this->message(['provider_id' => null, 'status' => WhatsappMessage::FAILED, 'error' => 'HTTP 429']);

        $updated = app(WhatsAppService::class)->refreshMessageStatuses(force: true);

        $this->assertSame(1, $updated);
        Http::assertSentCount(2);

        $queued->refresh();
        $this->assertSame(WhatsappMessage::READ, $queued->status);
        $this->assertSame('3EB0DEF', $queued->wa_message_id);
        // أوقات البوابة UTC تُحفظ بتوقيت التطبيق
        $this->assertSame(Carbon::parse('2026-09-09T09:30:00Z')->setTimezone(config('app.timezone'))->toDateTimeString(), $queued->read_at->toDateTimeString());
        $this->assertSame(Carbon::parse('2026-09-09T09:00:05Z')->setTimezone(config('app.timezone'))->toDateTimeString(), $queued->delivered_at->toDateTimeString());
        $this->assertNotNull($queued->status_checked_at);

        $this->assertSame(WhatsappMessage::SENT, $sent->refresh()->status);
        $this->assertNotNull($sent->status_checked_at);
        $this->assertNull($read->refresh()->status_checked_at);
        $this->assertNull($failed->refresh()->status_checked_at);
    }

    public function test_notification_poll_refreshes_statuses_and_the_log_shows_delivery_states(): void
    {
        Http::fake(['*/messages/77' => Http::response(['status' => 'delivered'])]);

        $user = User::factory()->create();
        foreach (['notifications.view', 'whatsapp.view'] as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $pending = $this->message(['provider_id' => '77']);
        $this->message(['provider_id' => '78', 'status' => WhatsappMessage::READ, 'read_at' => now()]);
        $this->message(['provider_id' => null, 'status' => WhatsappMessage::FAILED, 'error' => 'Hourly limit reached']);

        $this->actingAs($user)->get(route('dashboard.notifications.poll'))->assertOk();
        $this->assertSame(WhatsappMessage::DELIVERED, $pending->refresh()->status);

        $this->actingAs($user)->get(route('dashboard.whatsapp.index', ['tab' => 'messages']))
            ->assertOk()
            ->assertSee('وصلت')
            ->assertSee('قُرئت')
            ->assertSee('فشلت')
            ->assertSee('Hourly limit reached')
            ->assertSee('تحديث الحالات');

        // زر التحديث اليدوي
        $this->actingAs($user)->post(route('dashboard.whatsapp.messages.refresh'))
            ->assertRedirect(route('dashboard.whatsapp.index', ['tab' => 'messages']))
            ->assertSessionHas('success');
    }

    // ===== Helpers =====

    /** معاينة أُبلغ مالكها بالفعل (العلامة مضبوطة كما تضبطها send عند القبول) */
    private function viewing(): ClientViewing
    {
        $client = Client::create(['name' => 'عميل المعاينة', 'phone' => '66'.random_int(100000, 999999)]);
        $property = Property::create(['reference_code' => (string) random_int(1000, 9999), 'title' => ['ar' => 'شقة', 'en' => 'Flat']]);
        $viewing = $client->viewings()->create(['property_id' => $property->id, 'scheduled_at' => now()->addDay()]);
        $viewing->forceFill(['owner_notified_at' => now()])->save();

        return $viewing;
    }

    private function message(array $overrides = []): WhatsappMessage
    {
        return WhatsappMessage::create(array_merge([
            'kind' => WhatsAppTemplates::KIND_OWNER,
            'to_phone' => '96555110000',
            'to_label' => 'المالك · أبو خالد — +965 55110000',
            'body' => 'تفاصيل المعاينة',
            'status' => WhatsappMessage::QUEUED,
            'provider_id' => '123',
        ], $overrides));
    }

    private function webhook(array $payload, ?string $secret = 'whsec_test'): TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($secret !== null) {
            $headers['HTTP_X_KHABEERSOFT_SIGNATURE'] = 'sha256='.hash_hmac('sha256', $body, $secret);
        }

        return $this->call('POST', route('webhooks.khabeersoft'), [], [], [], $headers, $body);
    }
}
