<?php

namespace Tests\Feature\Dashboard;

use App\Models\ActivityLog;
use App\Models\Area;
use App\Models\City;
use App\Models\Client;
use App\Models\ClientAuditLog;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\Task;
use App\Models\UnitType;
use App\Models\User;
use App\Support\ActivityPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** سجل النشاط العام: يلتقط إضافة/تعديل/حذف الوحدات الأساسية ويعرضها بشاشة مستقلة بفلاتر */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_changes_are_logged_with_arabic_labels_and_without_derived_noise(): void
    {
        $editor = $this->userWith(['properties.view', 'properties.edit']);
        $available = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
        $sold = PropertyStatus::create(['name' => ['ar' => 'مباع', 'en' => 'Sold'], 'key' => 'sold']);

        $this->actingAs($editor);

        $property = Property::create([
            'reference_code' => '12', 'title' => ['ar' => 'شقة السالمية', 'en' => 'Salmiya flat'],
            'building_name' => 'برج السالمية', 'price' => 1000, 'purpose' => 'sale', 'status_id' => $available->id,
        ]);

        $created = ActivityLog::where('module', 'properties')->where('event', 'created')->firstOrFail();
        $this->assertSame($editor->id, $created->user_id);
        $this->assertSame(Property::class, $created->subject_type);
        $this->assertSame($property->id, (int) $created->subject_id);
        $this->assertSame('12 — مبنى: برج السالمية', $created->subject_label);
        $this->assertSame('شقة السالمية', $created->changes['title']['new']); // النص العربي لا JSON الترجمة
        $this->assertSame('12', $created->changes['reference_code']['new']);

        // تعديل السعر والحالة → صف واحد بسطرين
        $property->update(['price' => 1200, 'status_id' => $sold->id]);

        $updated = ActivityLog::where('module', 'properties')->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame(['price', 'status_id'], array_keys($updated->changes));

        $lines = collect(ActivityPresenter::present(collect([$updated->load('user')]))->first()['lines'])->keyBy('field');
        $this->assertSame('1000', $lines['السعر']['old']);
        $this->assertSame('1200', $lines['السعر']['new']);
        $this->assertSame('متاح', $lines['الحالة']['old']);
        $this->assertSame('مباع', $lines['الحالة']['new']);

        // التقييم وعدد التقييمات وsold_at أعمدة مشتقة — لا صف لها
        $property->update(['rating' => 4.5, 'reviews_count' => 3]);
        $this->assertSame(1, ActivityLog::where('module', 'properties')->where('event', 'updated')->count());

        // الحقول المترجمة تُقارَن بنصّها العربي
        $property->update(['title' => ['ar' => 'شقة السالمية الجديدة', 'en' => 'Salmiya flat']]);
        $title = ActivityLog::where('module', 'properties')->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame(['old' => 'شقة السالمية', 'new' => 'شقة السالمية الجديدة'], $title->changes['title']);

        // الحذف يحتفظ باسم السجل رغم زواله
        $property->delete();
        $deleted = ActivityLog::where('module', 'properties')->where('event', 'deleted')->firstOrFail();
        $this->assertSame('12 — مبنى: برج السالمية', $deleted->subject_label);
        $this->assertSame('12', $deleted->changes['reference_code']['old']);
        $this->assertNull(ActivityPresenter::present(collect([$deleted]))->first()['url']);
    }

    public function test_owner_client_viewing_and_task_writes_are_logged_and_old_logs_stay_untouched(): void
    {
        $user = $this->userWith([
            'property_owners.view', 'property_owners.create', 'property_owners.edit', 'property_owners.delete',
            'clients.view', 'clients.create', 'tasks.view', 'tasks.create',
        ]);
        $city = City::create(['name' => ['ar' => 'حولي', 'en' => 'Hawalli']]);
        $area = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $city->id]);
        UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment']]);
        PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
        $property = Property::create(['reference_code' => '501', 'title' => ['ar' => 'شقة', 'en' => 'Flat']]);

        // المالك
        $this->actingAs($user)->post(route('dashboard.owners.store'), [
            'name' => 'مالك الاختبار', 'area_id' => $area->id,
            'contacts' => [['phone_code' => '+965', 'phone' => '99001122', 'role' => 'المالك']],
        ])->assertSessionHasNoErrors();
        $owner = PropertyOwner::where('name', 'مالك الاختبار')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['module' => 'property_owners', 'event' => 'created', 'subject_id' => $owner->id, 'subject_label' => 'مالك الاختبار', 'user_id' => $user->id]);

        $this->actingAs($user)->put(route('dashboard.owners.update', $owner), [
            'name' => 'مالك معدّل', 'area_id' => $area->id,
            'contacts' => [['id' => $owner->contacts->first()->id, 'phone_code' => '+965', 'phone' => '99001122', 'role' => 'المالك']],
        ])->assertSessionHasNoErrors();
        $ownerUpdate = ActivityLog::where('module', 'property_owners')->where('event', 'updated')->firstOrFail();
        $this->assertSame(['old' => 'مالك الاختبار', 'new' => 'مالك معدّل'], $ownerUpdate->changes['name']);

        $this->actingAs($user)->delete(route('dashboard.owners.destroy', $owner));
        $this->assertDatabaseHas('activity_logs', ['module' => 'property_owners', 'event' => 'deleted', 'subject_label' => 'مالك معدّل']);

        // العميل + المعاينة: صف عام لكل منهما، وسجل العميل القديم يبقى كما هو
        $this->actingAs($user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل السجل', 'phone_code' => '+965', 'phone' => '55112233',
            'viewings' => [['property_id' => $property->id, 'scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'outcome' => 'pending']],
        ])->assertSessionHasNoErrors();
        $client = Client::where('name', 'عميل السجل')->firstOrFail();
        $viewing = ClientViewing::where('client_id', $client->id)->firstOrFail();

        $this->assertDatabaseHas('activity_logs', ['module' => 'clients', 'event' => 'created', 'subject_id' => $client->id, 'subject_label' => 'عميل السجل']);
        $viewingLog = ActivityLog::where('module', 'viewings')->where('event', 'created')->firstOrFail();
        $this->assertSame($viewing->id, (int) $viewingLog->subject_id);
        $this->assertStringContainsString('عميل السجل · 501', $viewingLog->subject_label);
        $this->assertDatabaseHas('client_audit_logs', ['client_id' => $client->id, 'action' => 'created']);
        $this->assertDatabaseHas('client_audit_logs', ['client_id' => $client->id, 'action' => 'viewing_added']);
        $this->assertSame(2, ClientAuditLog::where('client_id', $client->id)->count());

        // المهمة
        $this->actingAs($user)->post(route('dashboard.tasks.store'), ['title' => 'تصوير الشقة', 'priority' => 'high', 'property_id' => $property->id])
            ->assertSessionHasNoErrors();
        $task = Task::firstOrFail();
        $taskLog = ActivityLog::where('module', 'tasks')->where('event', 'created')->firstOrFail();
        $this->assertSame('#'.$task->id.' — تصوير الشقة', $taskLog->subject_label);
        $this->assertSame((string) $property->id, (string) $taskLog->changes['property_id']['new']);
        $this->assertDatabaseHas('task_audit_logs', ['task_id' => $task->id, 'action' => 'created']);
    }

    public function test_supervisor_role_and_permission_changes_are_logged_without_secrets(): void
    {
        $admin = $this->userWith(['supervisors.view', 'supervisors.create', 'supervisors.edit', 'supervisors.delete', 'roles.view', 'roles.create', 'roles.edit', 'roles.delete', 'permissions.view', 'permissions.edit']);
        $agent = Role::create(['name' => 'sales-agent', 'guard_name' => 'web', 'description' => 'مندوب مبيعات', 'status' => 'active']);
        $manager = Role::create(['name' => 'property-manager', 'guard_name' => 'web', 'description' => 'مدير عقارات', 'status' => 'active']);

        // إضافة مستخدم: الدور يظهر، وكلمة المرور لا تظهر بأي شكل
        $this->actingAs($admin)->post(route('dashboard.supervisors.store'), [
            'name' => 'موظف جديد', 'email' => 'new@alam.test', 'password' => 'S3cretPass!', 'role' => 'sales-agent', 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $staff = User::where('email', 'new@alam.test')->firstOrFail();
        $created = ActivityLog::where('module', 'supervisors')->where('event', 'created')->firstOrFail();
        $this->assertSame($staff->id, (int) $created->subject_id);
        $this->assertSame('مندوب مبيعات', $created->changes['role']['new']);
        $this->assertArrayNotHasKey('password', $created->changes);
        $this->assertStringNotContainsString('S3cretPass', json_encode($created->changes));

        // تعديل الاسم + الدور + كلمة المرور → صف واحد
        $this->actingAs($admin)->put(route('dashboard.supervisors.update', $staff), [
            'name' => 'موظف معدّل', 'email' => 'new@alam.test', 'password' => 'An0therPass!', 'role' => 'property-manager', 'status' => 'suspended',
        ])->assertSessionHasNoErrors();
        $updates = ActivityLog::where('module', 'supervisors')->where('event', 'updated')->get();
        $this->assertCount(1, $updates);
        $changes = $updates->first()->changes;
        $this->assertSame(['old' => 'موظف جديد', 'new' => 'موظف معدّل'], $changes['name']);
        $this->assertSame(['old' => 'مندوب مبيعات', 'new' => 'مدير عقارات'], $changes['role']);
        $this->assertSame(['old' => null, 'new' => 'تم التغيير'], $changes['password']);
        $this->assertSame('suspended', $changes['status']['new']);
        $this->assertStringNotContainsString('An0therPass', json_encode($changes));
        $this->assertStringNotContainsString($staff->fresh()->password, json_encode($changes));

        $this->actingAs($admin)->delete(route('dashboard.supervisors.destroy', $staff));
        $this->assertDatabaseHas('activity_logs', ['module' => 'supervisors', 'event' => 'deleted', 'subject_label' => 'موظف معدّل']);

        // الأدوار
        $this->actingAs($admin)->post(route('dashboard.roles.store'), ['description' => 'محاسب', 'status' => 'active'])->assertSessionHasNoErrors();
        $accountant = Role::where('description', 'محاسب')->firstOrFail();
        $this->assertDatabaseHas('activity_logs', ['module' => 'roles', 'event' => 'created', 'subject_id' => $accountant->id, 'subject_label' => 'محاسب']);

        $this->actingAs($admin)->put(route('dashboard.roles.update', $accountant), ['description' => 'محاسب أول', 'status' => 'inactive'])->assertSessionHasNoErrors();
        $roleUpdate = ActivityLog::where('module', 'roles')->where('event', 'updated')->firstOrFail();
        $this->assertSame(['old' => 'محاسب', 'new' => 'محاسب أول'], $roleUpdate->changes['description']);
        $this->assertSame('inactive', $roleUpdate->changes['status']['new']);

        $this->actingAs($admin)->delete(route('dashboard.roles.destroy', $accountant));
        $this->assertDatabaseHas('activity_logs', ['module' => 'roles', 'event' => 'deleted', 'subject_label' => 'محاسب أول']);

        // مصفوفة الصلاحيات: ما أُضيف وما أُزيل بالعربي — ولا صف لو لم يتغير شيء
        Permission::firstOrCreate(['name' => 'clients.view', 'guard_name' => 'web']);
        $this->actingAs($admin)->put(route('dashboard.permissions.update'), ['role_id' => $agent->id, 'permissions' => ['clients.view', 'clients.edit']])->assertRedirect();
        $granted = ActivityLog::where('module', 'roles')->where('subject_id', $agent->id)->latest('id')->firstOrFail();
        $this->assertSame('إدارة العملاء: عرض · إدارة العملاء: تعديل', $granted->changes['permissions_added']['new']);
        $this->assertArrayNotHasKey('permissions_removed', $granted->changes);

        $this->actingAs($admin)->put(route('dashboard.permissions.update'), ['role_id' => $agent->id, 'permissions' => ['clients.view']])->assertRedirect();
        $revoked = ActivityLog::where('module', 'roles')->where('subject_id', $agent->id)->latest('id')->firstOrFail();
        $this->assertSame('إدارة العملاء: تعديل', $revoked->changes['permissions_removed']['old']);

        $before = ActivityLog::count();
        $this->actingAs($admin)->put(route('dashboard.permissions.update'), ['role_id' => $agent->id, 'permissions' => ['clients.view']])->assertRedirect();
        $this->assertSame($before, ActivityLog::count());
        $this->assertSame(0, ActivityLog::where('subject_id', $manager->id)->where('module', 'roles')->count());
    }

    public function test_activity_page_requires_permission_and_filters_by_module_event_user_date_and_search(): void
    {
        $viewer = $this->userWith(['activity.view']);
        $other = User::factory()->create(['name' => 'مستخدم آخر']);
        PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);

        $this->actingAs(User::factory()->create())->get(route('dashboard.activity.index'))->assertForbidden();

        $this->actingAs($viewer);
        $kept = Property::create(['reference_code' => '77', 'title' => ['ar' => 'عقار باقٍ', 'en' => 'Kept']]);
        $gone = Property::create(['reference_code' => '88', 'title' => ['ar' => 'عقار محذوف', 'en' => 'Gone']]);
        $gone->delete();
        PropertyOwner::create(['name' => 'مالك للفلترة', 'phone_code' => '+965', 'phone' => '99887766']);

        $old = ActivityLog::where('subject_label', '77')->firstOrFail();
        $old->forceFill(['created_at' => now()->subDays(20), 'user_id' => $other->id])->save();

        $page = $this->actingAs($viewer)->get(route('dashboard.activity.index'))->assertOk()
            ->assertSee('سجل النشاط')->assertSee('مالك للفلترة')->assertSee('77')->assertSee('88')
            ->assertSee(route('dashboard.properties.show', $kept), false) // الباقي له رابط
            ->assertSee('(محذوف)')->assertSee('data-filter-set="event"', false);
        $this->assertSame(4, $page->viewData('eventCounts')['total']);
        $this->assertSame(['created' => 3, 'deleted' => 1], $page->viewData('eventCounts')['events']);

        $index = fn (array $q) => $this->actingAs($viewer)->get(route('dashboard.activity.index', $q))->assertOk();

        $index(['module' => 'property_owners'])->assertSee('مالك للفلترة')->assertDontSee('عقار باقٍ')->assertDontSee('>77<', false);
        $index(['event' => 'deleted'])->assertSee('88')->assertDontSee('مالك للفلترة');
        $index(['user_id' => $other->id])->assertSee('77')->assertDontSee('مالك للفلترة');
        $index(['from' => now()->subDays(2)->toDateString()])->assertSee('مالك للفلترة')->assertDontSee('>77<', false);
        $index(['to' => now()->subDays(10)->toDateString()])->assertSee('77')->assertDontSee('مالك للفلترة');
        $index(['search' => 'للفلترة'])->assertSee('مالك للفلترة')->assertDontSee('>77<', false);

        // عدّادات الأزرار تتجاهل فلتر العملية نفسه
        $counts = $index(['event' => 'deleted', 'module' => 'properties'])->viewData('eventCounts');
        $this->assertSame(3, $counts['total']);
        $this->assertSame(['created' => 2, 'deleted' => 1], $counts['events']);

        // الشريط الجانبي: يظهر لصاحب الصلاحية فقط
        $this->actingAs($viewer)->get(route('dashboard.profile.edit'))->assertOk()->assertSee(route('dashboard.activity.index'), false);
        $this->actingAs(User::factory()->create())->get(route('dashboard.profile.edit'))->assertOk()->assertDontSee(route('dashboard.activity.index'), false);
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
