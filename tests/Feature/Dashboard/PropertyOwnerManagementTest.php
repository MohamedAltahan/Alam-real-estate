<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\City;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PropertyOwnerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_requires_at_least_one_contact_and_copies_the_first_one(): void
    {
        $user = $this->userWith(['property_owners.view', 'property_owners.create', 'property_owners.edit']);

        $this->actingAs($user)->post(route('dashboard.owners.store'), [
            'name' => 'مالك بلا أرقام',
            'contacts' => [],
        ])->assertSessionHasErrors('contacts');

        $this->actingAs($user)->post(route('dashboard.owners.store'), [
            'name' => 'مالك برقمين',
            'notes' => 'ملاحظة المالك',
            'contacts' => [
                ['phone_code' => '+965', 'phone' => '99001122', 'role' => 'المالك', 'name' => 'سعد'],
                ['phone_code' => '+966', 'phone' => '0501234567', 'role' => 'الوكيل', 'name' => 'فهد'],
            ],
        ])->assertSessionHasNoErrors();

        $owner = PropertyOwner::where('name', 'مالك برقمين')->firstOrFail();
        $this->assertSame('99001122', $owner->phone);
        $this->assertSame('+965', $owner->phone_code);
        $this->assertCount(2, $owner->contacts);
        $this->assertSame('501234567', $owner->contacts[1]->phone); // الصفر البادئ يُزال

        // التعديل: حذف الأول وإبقاء الثاني بمعرّفه → الرقم الرئيسي يتغير
        $second = $owner->contacts[1];
        $this->actingAs($user)->put(route('dashboard.owners.update', $owner), [
            'name' => 'مالك برقمين',
            'contacts' => [
                ['id' => $second->id, 'phone_code' => '+966', 'phone' => '501234567', 'role' => 'الوكيل', 'name' => 'فهد'],
            ],
        ])->assertSessionHasNoErrors();

        $owner->refresh();
        $this->assertCount(1, $owner->contacts);
        $this->assertSame('+966', $owner->phone_code);
        $this->assertSame('501234567', $owner->phone);
    }

    public function test_owner_filters_search_contacts_notes_city_agent_and_properties(): void
    {
        $user = $this->userWith(['property_owners.view']);
        $city = City::create(['name' => ['ar' => 'محافظة حولي', 'en' => 'Hawalli']]);
        $otherCity = City::create(['name' => ['ar' => 'محافظة الأحمدي', 'en' => 'Ahmadi']]);
        $area = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $city->id]);
        $otherArea = Area::create(['name' => ['ar' => 'الفنطاس', 'en' => 'Fintas'], 'city_id' => $otherCity->id]);
        $agent = User::factory()->create(['is_agent' => true]);

        $withProperty = PropertyOwner::create(['name' => 'مالك حولي', 'phone_code' => '+965', 'phone' => '11111111', 'area_id' => $area->id, 'notes' => 'يفضل التواصل صباحًا']);
        $withProperty->contacts()->create(['phone_code' => '+965', 'phone' => '11111111']);
        $withProperty->contacts()->create(['phone_code' => '+965', 'phone' => '77778888', 'role' => 'الوكيل']);
        Property::create(['reference_code' => '1', 'title' => ['ar' => 'عقار', 'en' => 'P'], 'owner_id' => $withProperty->id, 'agent_id' => $agent->id]);

        $without = PropertyOwner::create(['name' => 'مالك الأحمدي', 'phone_code' => '+965', 'phone' => '22222222', 'area_id' => $otherArea->id, 'notes' => 'لا يرد على الهاتف']);
        $without->contacts()->create(['phone_code' => '+965', 'phone' => '22222222']);

        $index = fn (array $filters) => $this->actingAs($user)->get(route('dashboard.owners.index', $filters))->assertOk();

        $index(['search' => '77778888'])->assertSee('مالك حولي')->assertDontSee('مالك الأحمدي');
        $index(['city_id' => $otherCity->id])->assertSee('مالك الأحمدي')->assertDontSee('مالك حولي');
        $index(['agent_id' => $agent->id])->assertSee('مالك حولي')->assertDontSee('مالك الأحمدي');
        $index(['has_properties' => '0'])->assertSee('مالك الأحمدي')->assertDontSee('مالك حولي');
        $index(['has_properties' => '1'])->assertSee('مالك حولي')->assertDontSee('مالك الأحمدي');
        $index(['notes_q' => 'صباح'])->assertSee('مالك حولي')->assertDontSee('مالك الأحمدي');
        $index(['notes_q' => 'ص'])->assertSee('مالك حولي')->assertSee('مالك الأحمدي'); // أقل من الحد الأدنى → يُتجاهل
    }

    public function test_owner_files_can_be_removed_and_wrong_types_are_rejected(): void
    {
        $user = $this->userWith(['property_owners.view', 'property_owners.create', 'property_owners.edit']);

        $this->actingAs($user)->post(route('dashboard.owners.store'), [
            'name' => 'مالك الملفات',
            'contacts' => [['phone_code' => '+965', 'phone' => '55556666']],
            'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ])->assertSessionHasErrors('files.0');

        $this->actingAs($user)->post(route('dashboard.owners.store'), [
            'name' => 'مالك الملفات',
            'contacts' => [['phone_code' => '+965', 'phone' => '55556666']],
            'files' => [
                UploadedFile::fake()->image('photo.png'),
                UploadedFile::fake()->create('contract.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ],
        ])->assertSessionHasNoErrors();

        $owner = PropertyOwner::where('name', 'مالك الملفات')->firstOrFail();
        $this->assertCount(2, $owner->getMedia('files'));
        $removeId = $owner->getMedia('files')->first()->id;

        $this->actingAs($user)->put(route('dashboard.owners.update', $owner), [
            'name' => 'مالك الملفات',
            'contacts' => [['id' => $owner->contacts->first()->id, 'phone_code' => '+965', 'phone' => '55556666']],
            'files_removed' => [$removeId],
        ])->assertSessionHasNoErrors();

        $this->assertCount(1, $owner->fresh()->getMedia('files'));
        $this->assertDatabaseMissing('media', ['id' => $removeId]);
    }

    public function test_owner_index_uses_sales_agent_label_and_no_contract_status(): void
    {
        $user = $this->userWith(['property_owners.view']);
        PropertyOwner::create(['name' => 'مالك القائمة', 'phone_code' => '+965', 'phone' => '99887766']);

        $this->actingAs($user)->get(route('dashboard.owners.index'))
            ->assertOk()
            ->assertSee('مندوب المبيعات')
            ->assertSee('بحث في الملاحظات')
            ->assertDontSee('مسؤول العقار')
            ->assertDontSee('حالة العقد')
            ->assertDontSee('الجنسية');
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
