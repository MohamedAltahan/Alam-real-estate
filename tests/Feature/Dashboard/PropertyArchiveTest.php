<?php

namespace Tests\Feature\Dashboard;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ViewingReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** أرشفة العقار (soft delete): يختفي من الموقع والقوائم، يحتفظ بحالته، ويمكن استرجاعه — لا حذف نهائي */
class PropertyArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->userWith(['properties.view', 'properties.view_commission', 'properties.edit', 'properties.delete']);
        $this->property = Property::create(['reference_code' => '777', 'title' => ['ar' => 'شقة الأرشيف', 'en' => 'Archived flat']]);
    }

    public function test_archiving_hides_the_property_instead_of_removing_it(): void
    {
        $this->actingAs($this->admin)->delete(route('dashboard.properties.destroy', $this->property))
            ->assertRedirect(route('dashboard.properties.index'));

        $this->assertSoftDeleted('properties', ['id' => $this->property->id, 'deleted_by' => $this->admin->id]);

        // اختفى من القائمة والموقع
        $this->actingAs($this->admin)->get(route('dashboard.properties.index'))
            ->assertOk()->assertDontSee('شقة الأرشيف');
        $this->get(route('site.property', $this->property))->assertNotFound();
        $this->get(route('site.properties'))->assertDontSee('شقة الأرشيف');

        // وظهر في الأرشيف باسم من أرشفه
        $this->actingAs($this->admin)->get(route('dashboard.properties.archive'))
            ->assertOk()
            ->assertSee('شقة الأرشيف')
            ->assertSee($this->admin->name)
            ->assertSee(route('dashboard.properties.restore', $this->property), false);

        $this->assertSame(1, ActivityLog::where('event', 'archived')->where('subject_id', $this->property->id)->count());
    }

    public function test_restore_brings_the_property_back_everywhere(): void
    {
        $this->actingAs($this->admin)->delete(route('dashboard.properties.destroy', $this->property));

        $this->actingAs($this->admin)
            ->from(route('dashboard.properties.archive'))
            ->patch(route('dashboard.properties.restore', $this->property))
            ->assertRedirect(route('dashboard.properties.archive'));

        $restored = Property::find($this->property->id);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_by);
        $this->get(route('site.property', $restored))->assertOk();

        // «استرجاع» في سجل النشاط، بدون «تعديل» زائد
        $this->assertSame(1, ActivityLog::where('event', 'restored')->where('subject_id', $this->property->id)->count());
        $this->assertSame(0, ActivityLog::where('event', 'updated')->where('subject_id', $this->property->id)->count());
    }

    public function test_archive_has_no_permanent_delete_and_needs_the_delete_permission(): void
    {
        $this->property->delete();

        $html = $this->actingAs($this->admin)->get(route('dashboard.properties.archive'))->assertOk()->getContent();
        $this->assertStringNotContainsString('name="_method" value="DELETE"', $html);

        $viewer = $this->userWith(['properties.view', 'properties.view_commission']);
        $this->actingAs($viewer)->get(route('dashboard.properties.archive'))->assertForbidden();
        $this->actingAs($viewer)->patch(route('dashboard.properties.restore', $this->property))->assertForbidden();
        $this->assertSoftDeleted('properties', ['id' => $this->property->id]);

        // زر الأرشيف يظهر لمن يملك الأرشفة فقط
        $this->actingAs($this->admin)->get(route('dashboard.properties.index'))->assertSee(route('dashboard.properties.archive'), false);
        $this->actingAs($viewer)->get(route('dashboard.properties.index'))->assertDontSee(route('dashboard.properties.archive'), false);
    }

    public function test_archived_property_page_still_opens_with_a_restore_banner(): void
    {
        $this->property->delete();

        $this->actingAs($this->admin)->get(route('dashboard.properties.show', $this->property))
            ->assertOk()
            ->assertSee('هذا العقار في «الأرشيف»')
            ->assertSee(route('dashboard.properties.restore', $this->property), false)
            ->assertDontSee(route('dashboard.properties.edit', $this->property), false);

        // التعديل مغلق حتى يُسترجع
        $this->actingAs($this->admin)->get(route('dashboard.properties.edit', $this->property))->assertNotFound();
    }

    public function test_viewings_keep_their_property_and_deleted_ones_get_no_reminder(): void
    {
        $agent = User::factory()->create(['is_agent' => true]);
        $agent->givePermissionTo(Permission::firstOrCreate(['name' => 'notifications.view', 'guard_name' => 'web']));
        $client = Client::create(['name' => 'عميل', 'phone_code' => '+965', 'phone' => '55000009', 'agent_id' => $agent->id]);
        $viewing = $client->viewings()->create(['property_id' => $this->property->id, 'scheduled_at' => now()->addMinutes(30), 'in_person' => true]);

        $this->property->delete();

        // المعاينة ما زالت تعرف عقارها (لا ينكسر عرضها)
        $this->assertSame('777', $viewing->fresh()->property?->reference_code);

        $this->actingAs($agent)->getJson(route('dashboard.notifications.poll'))->assertOk();
        $this->assertSame(0, $agent->notifications()->where('type', ViewingReminder::class)->count());
        $this->assertNull($viewing->fresh()->reminded_at);
    }

    public function test_new_reference_codes_skip_deleted_properties(): void
    {
        $this->property->delete();

        $this->assertNotSame('777', app(\App\Services\PropertyService::class)->generateReferenceCode());
        $this->assertGreaterThan(777, (int) app(\App\Services\PropertyService::class)->generateReferenceCode());
    }

    public function test_archiving_keeps_the_status_it_had_and_shows_it_in_the_archive(): void
    {
        $sold = \App\Models\PropertyStatus::create(['name' => ['ar' => 'مباع', 'en' => 'Sold'], 'key' => 'sold', 'color' => '#DC2626']);
        $this->property->update(['status_id' => $sold->id]);
        $soldAt = $this->property->fresh()->sold_at;

        $this->actingAs($this->admin)->delete(route('dashboard.properties.destroy', $this->property));

        $archived = Property::withTrashed()->find($this->property->id);
        $this->assertSame($sold->id, (int) $archived->status_id);
        $this->assertEquals($soldAt, $archived->sold_at);

        $this->actingAs($this->admin)->get(route('dashboard.properties.archive'))
            ->assertOk()
            ->assertSee('مباع')
            // كل بيانات العقار المؤرشف متاحة من صفحة تفاصيله
            ->assertSee(route('dashboard.properties.show', $this->property), false);

        $this->actingAs($this->admin)->patch(route('dashboard.properties.restore', $this->property));
        $this->assertSame($sold->id, (int) Property::find($this->property->id)->status_id);
    }

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        return $user;
    }
}
