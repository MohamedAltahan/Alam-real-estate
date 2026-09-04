<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientAuditLog;
use App\Models\ClientStage;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_changes_are_logged_with_user_old_and_new_values(): void
    {
        $user = User::factory()->create(['name' => 'موظف السجل']);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'clients.view', 'guard_name' => 'web']));
        $this->actingAs($user);

        $service = app(ClientService::class);
        $client = $service->create(['name' => 'عميل قبل', 'phone_code' => '+965', 'phone' => '55000000']);

        $this->assertDatabaseHas('client_audit_logs', ['client_id' => $client->id, 'action' => 'created', 'user_id' => $user->id]);

        $viewing = ClientStage::where('key', 'viewing')->firstOrFail();
        $service->update($client, ['name' => 'عميل بعد', 'stage_id' => $viewing->id]);

        $log = ClientAuditLog::where('client_id', $client->id)->where('action', 'updated')->latest('id')->firstOrFail();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('عميل قبل', $log->changes['name']['old']);
        $this->assertSame('عميل بعد', $log->changes['name']['new']);
        $this->assertSame((string) $viewing->id, (string) $log->changes['stage_id']['new']);

        // لا يسجَّل شيء عند حفظ بدون تغيير
        $count = ClientAuditLog::where('client_id', $client->id)->count();
        $service->update($client, ['name' => 'عميل بعد']);
        $this->assertSame($count, ClientAuditLog::where('client_id', $client->id)->count());

        // نهايات الأسطر التي يرسلها المتصفح (CRLF) ليست تغييراً في الملاحظات
        $service->update($client, ['notes' => "سطر\nثانٍ"]);
        $count = ClientAuditLog::where('client_id', $client->id)->count();
        $service->update($client, ['notes' => "سطر\r\nثانٍ"]);
        $this->assertSame($count, ClientAuditLog::where('client_id', $client->id)->count());

        $this->get(route('dashboard.clients.show', $client))
            ->assertOk()
            ->assertSee('سجل التعديلات')
            ->assertSee('تعديل البيانات')
            ->assertSee('عميل قبل')
            ->assertSee('معاينة العقار')
            ->assertSee('موظف السجل');
    }

    public function test_stage_change_through_interaction_is_logged(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $service = app(ClientService::class);
        $client = $service->create(['name' => 'عميل', 'phone_code' => '+965', 'phone' => '55000001']);
        $won = ClientStage::where('key', 'closed_won')->firstOrFail();

        $service->logInteraction($client, ['type' => 'call', 'notes' => 'اتفقنا', 'stage_id' => $won->id]);

        $actions = ClientAuditLog::where('client_id', $client->id)->pluck('action');
        $this->assertTrue($actions->contains('interaction_logged'));
        $this->assertTrue($actions->contains('updated'));
    }

    public function test_deleting_a_client_keeps_its_audit_trail(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $service = app(ClientService::class);
        $client = $service->create(['name' => 'عميل محذوف', 'phone_code' => '+965', 'phone' => '55000002']);
        $service->delete($client);

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('client_audit_logs', ['action' => 'deleted', 'user_id' => $user->id]);
        $this->assertSame(0, Client::count());
    }
}
