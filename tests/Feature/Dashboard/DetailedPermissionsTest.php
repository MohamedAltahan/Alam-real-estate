<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * الصلاحيات التفصيلية التي يمكن سحبها من مندوب المبيعات:
 * عرض كل العملاء · تغيير مندوب العميل · عرض نسبة عمولة المالك.
 */
class DetailedPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_view_all_an_agent_sees_only_their_own_clients(): void
    {
        $agent = $this->userWith(['clients.view', 'clients.edit'], ['is_agent' => true, 'name' => 'المندوب أحمد']);
        $other = User::factory()->create(['is_agent' => true]);

        $mine = Client::create(['name' => 'عميل باسمي', 'phone_code' => '+965', 'phone' => '55000001', 'agent_id' => $agent->id]);
        $recorded = Client::create(['name' => 'عميل سجلته', 'phone_code' => '+965', 'phone' => '55000002', 'recorded_by' => $agent->id]);
        $theirs = Client::create(['name' => 'عميل لغيري', 'phone_code' => '+965', 'phone' => '55000003', 'agent_id' => $other->id]);

        $this->actingAs($agent)->get(route('dashboard.clients.index'))
            ->assertOk()->assertSee('عميل باسمي')->assertSee('عميل سجلته')->assertDontSee('عميل لغيري');

        // ولا يفتح عميل غيره بالرابط المباشر، ولا يعدّله
        $this->actingAs($agent)->get(route('dashboard.clients.show', $mine))->assertOk();
        $this->actingAs($agent)->get(route('dashboard.clients.show', $theirs))->assertNotFound();
        $this->actingAs($agent)->patch(route('dashboard.clients.stage', $theirs), ['stage_id' => 1])->assertNotFound();

        // ومعايناته كذلك
        $property = Property::create(['reference_code' => '9', 'title' => ['ar' => 'عقار', 'en' => 'P']]);
        $myViewing = $mine->viewings()->create(['property_id' => $property->id, 'scheduled_at' => now()->addDay()]);
        $theirViewing = $theirs->viewings()->create(['property_id' => $property->id, 'scheduled_at' => now()->addDay()]);
        $this->actingAs($agent)->patchJson(route('dashboard.viewings.notes', $theirViewing), ['notes' => 'x'])->assertNotFound();
        $this->actingAs($agent)->patchJson(route('dashboard.viewings.notes', $myViewing), ['notes' => 'ملاحظة'])->assertOk();

        // مع الصلاحية: الكل
        $agent->givePermissionTo(Permission::firstOrCreate(['name' => 'clients.view_all', 'guard_name' => 'web']));
        $this->actingAs($agent->fresh())->get(route('dashboard.clients.index'))->assertSee('عميل لغيري');
        $this->actingAs($agent->fresh())->get(route('dashboard.clients.show', $theirs))->assertOk();
    }

    public function test_without_assign_the_agent_field_is_locked_and_new_clients_go_to_the_agent(): void
    {
        $agent = $this->userWith(['clients.view', 'clients.view_all', 'clients.create', 'clients.edit'], ['is_agent' => true, 'name' => 'المندوب سامي']);
        $other = User::factory()->create(['is_agent' => true]);

        $this->actingAs($agent)->get(route('dashboard.clients.index'))
            ->assertOk()->assertDontSee('<select name="agent_id" class=', false)->assertSee('المندوب سامي');

        // يحاول تسجيل العميل باسم مندوب آخر ⇒ يُسجَّل باسمه هو
        $this->actingAs($agent)->post(route('dashboard.clients.store'), [
            'name' => 'عميل جديد', 'phone_code' => '+965', 'phone' => '55000011', 'agent_id' => $other->id,
        ])->assertSessionHasNoErrors();
        $client = Client::where('name', 'عميل جديد')->firstOrFail();
        $this->assertSame($agent->id, (int) $client->agent_id);

        // ولا يغيّره عند التعديل
        $this->actingAs($agent)->put(route('dashboard.clients.update', $client), [
            'name' => 'عميل جديد', 'phone_code' => '+965', 'phone' => '55000011', 'agent_id' => $other->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($agent->id, (int) $client->fresh()->agent_id);

        // مع الصلاحية يستطيع
        $agent->givePermissionTo(Permission::firstOrCreate(['name' => 'clients.assign', 'guard_name' => 'web']));
        $this->actingAs($agent->fresh())->put(route('dashboard.clients.update', $client), [
            'name' => 'عميل جديد', 'phone_code' => '+965', 'phone' => '55000011', 'agent_id' => $other->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($other->id, (int) $client->fresh()->agent_id);
    }

    public function test_without_view_commission_the_rate_is_hidden_and_kept_on_save(): void
    {
        $user = $this->userWith(['properties.view', 'properties.edit']);
        $property = Property::create(['reference_code' => '77', 'title' => ['ar' => 'شقة العمولة', 'en' => 'Flat'], 'owner_commission_rate' => 2.5]);

        $this->actingAs($user)->get(route('dashboard.properties.index'))
            ->assertOk()->assertSee('شقة العمولة')->assertDontSee('نسبة العمولة')->assertDontSee('2.5%');
        $this->actingAs($user)->get(route('dashboard.properties.show', $property))
            ->assertOk()->assertDontSee('نسبة العمولة من المالك');
        $this->actingAs($user)->get(route('dashboard.properties.edit', $property))
            ->assertOk()->assertDontSee('name="owner_commission_rate"', false);

        // فلتر العمولة يُتجاهل
        $other = Property::create(['reference_code' => '78', 'title' => ['ar' => 'شقة أخرى', 'en' => 'Other']]);
        $this->actingAs($user)->get(route('dashboard.properties.index', ['commission' => 'none']))
            ->assertOk()->assertSee('شقة العمولة')->assertSee('شقة أخرى');

        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'properties.view_commission', 'guard_name' => 'web']));
        $this->actingAs($user->fresh())->get(route('dashboard.properties.show', $property))
            ->assertOk()->assertSee('نسبة العمولة من المالك')->assertSee('2.5%');
    }

    public function test_permission_matrix_lists_and_saves_the_detailed_permissions(): void
    {
        $admin = $this->userWith(['permissions.view', 'permissions.edit']);
        $role = Role::create(['name' => 'sales-agent', 'guard_name' => 'web', 'description' => 'مندوب مبيعات']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'clients.view', 'guard_name' => 'web']));

        $this->actingAs($admin)->get(route('dashboard.permissions.index', ['role' => $role->id]))
            ->assertOk()
            ->assertSee('صلاحيات تفصيلية')
            ->assertSee('عرض كل العملاء')->assertSee('تغيير مندوب المبيعات للعميل')->assertSee('عرض نسبة عمولة المالك')
            ->assertSee('value="clients.view_all"', false);

        $this->actingAs($admin)->put(route('dashboard.permissions.update'), [
            'role_id' => $role->id,
            'permissions' => ['clients.view', 'properties.view', 'properties.view_commission'],
        ])->assertRedirect();

        $role->refresh();
        $this->assertTrue($role->hasPermissionTo('properties.view_commission'));
        $this->assertFalse($role->hasPermissionTo('clients.view_all'));
    }

    private function userWith(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        return $user;
    }
}
