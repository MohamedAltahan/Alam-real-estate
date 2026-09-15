<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\City;
use App\Models\FieldOwner;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** شاشة «ميداني»: التسجيل، الفلاتر، والتحويل إلى مالك وعقار */
class FieldOwnerManagementTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $this->city = City::create(['name' => ['ar' => 'محافظة حولي', 'en' => 'Hawalli']]);
        $this->area = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $this->city->id]);
    }

    public function test_store_requires_a_contact_and_copies_the_first_row(): void
    {
        $user = $this->userWith(['field_owners.view', 'field_owners.create']);

        $this->actingAs($user)->post(route('dashboard.field-owners.store'), $this->payload(['contacts' => []]))
            ->assertSessionHasErrors('contacts');

        $this->actingAs($user)->post(route('dashboard.field-owners.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $record = FieldOwner::firstOrFail();
        $this->assertSame('سعد المطيري', $record->name);
        $this->assertSame('+965', $record->phone_code);
        $this->assertSame('99001122', $record->phone);
        $this->assertSame((int) $user->id, (int) $record->created_by);
        $this->assertSame((int) $this->city->id, (int) $record->city_id);
        $this->assertSame('visit', $record->contact_method);
        $this->assertSame('new', $record->stage);
        $this->assertEqualsWithDelta(29.3375, (float) $record->latitude, 0.0000001);
        $this->assertEqualsWithDelta(48.0758, (float) $record->longitude, 0.0000001);
        $this->assertCount(2, $record->contacts);
        $this->assertSame('501234567', $record->contacts[1]->phone); // الصفر البادئ يُزال
        $this->assertSame('الوكيل', $record->contacts[1]->role);
    }

    public function test_city_is_inferred_from_area_and_unknown_stage_or_method_is_rejected(): void
    {
        $user = $this->userWith(['field_owners.view', 'field_owners.create']);

        $this->actingAs($user)->post(route('dashboard.field-owners.store'), $this->payload(['stage' => 'foo', 'contact_method' => 'sms']))
            ->assertSessionHasErrors(['stage', 'contact_method']);

        $this->actingAs($user)->post(route('dashboard.field-owners.store'), $this->payload(['city_id' => '', 'latitude' => '', 'longitude' => '']))
            ->assertSessionHasNoErrors();

        $record = FieldOwner::firstOrFail();
        $this->assertSame((int) $this->city->id, (int) $record->city_id);
        $this->assertNull($record->latitude);
    }

    public function test_update_syncs_contacts_by_id_and_recopies_the_first_row(): void
    {
        $user = $this->userWith(['field_owners.view', 'field_owners.create', 'field_owners.edit']);
        $this->actingAs($user)->post(route('dashboard.field-owners.store'), $this->payload())->assertSessionHasNoErrors();
        $record = FieldOwner::firstOrFail();
        $second = $record->contacts[1];

        $this->actingAs($user)->put(route('dashboard.field-owners.update', $record), $this->payload([
            'stage' => 'meeting',
            'contacts' => [
                ['id' => $second->id, 'name' => 'فهد', 'role' => 'الوكيل', 'phone_code' => '+966', 'phone' => '501234567'],
            ],
        ]))->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertCount(1, $record->contacts);
        $this->assertSame('فهد', $record->name);
        $this->assertSame('+966', $record->phone_code);
        $this->assertSame('501234567', $record->phone);
        $this->assertSame('meeting', $record->stage);
    }

    public function test_index_filters_by_search_stage_method_city_rep_and_dates(): void
    {
        $user = $this->userWith(['field_owners.view']);
        $rep = User::factory()->create();
        $otherCity = City::create(['name' => ['ar' => 'محافظة الأحمدي', 'en' => 'Ahmadi']]);

        $this->record(['name' => 'سعد', 'phone' => '11111111', 'stage' => 'new', 'contact_method' => 'visit', 'property_number' => 'PACI-777', 'city_id' => $this->city->id, 'created_by' => $rep->id]);
        $this->record(['name' => 'ناصر', 'phone' => '22222222', 'stage' => 'won', 'contact_method' => 'call', 'city_id' => $otherCity->id, 'area_id' => null, 'created_by' => $user->id, 'created_at' => now()->subDays(10)]);

        $index = route('dashboard.field-owners.index');

        $this->actingAs($user)->get($index)->assertOk()->assertSee('سعد')->assertSee('ناصر');
        $this->actingAs($user)->get($index.'?search=777')->assertSee('سعد')->assertDontSee('ناصر');
        $this->actingAs($user)->get($index.'?search=2222')->assertSee('ناصر')->assertDontSee('سعد');
        $this->actingAs($user)->get($index.'?stage=won')->assertSee('ناصر')->assertDontSee('سعد');
        $this->actingAs($user)->get($index.'?contact_method=visit')->assertSee('سعد')->assertDontSee('ناصر');
        $this->actingAs($user)->get($index.'?city_id='.$this->city->id)->assertSee('سعد')->assertDontSee('ناصر');
        $this->actingAs($user)->get($index.'?created_by='.$rep->id)->assertSee('سعد')->assertDontSee('ناصر');
        $this->actingAs($user)->get($index.'?from='.now()->subDay()->toDateString())->assertSee('سعد')->assertDontSee('ناصر');
    }

    public function test_convert_to_owner_creates_owner_with_contacts_and_is_idempotent(): void
    {
        $user = $this->userWith(['field_owners.view', 'field_owners.create', 'property_owners.view', 'property_owners.create']);
        $this->actingAs($user)->post(route('dashboard.field-owners.store'), $this->payload())->assertSessionHasNoErrors();
        $record = FieldOwner::firstOrFail();

        $this->actingAs($user)->post(route('dashboard.field-owners.convert-owner', $record))
            ->assertRedirect(route('dashboard.owners.show', PropertyOwner::firstOrFail()));

        $owner = PropertyOwner::firstOrFail();
        $this->assertSame('سعد المطيري', $owner->name);
        $this->assertSame('+965', $owner->phone_code);
        $this->assertSame('99001122', $owner->phone);
        $this->assertSame((int) $this->area->id, (int) $owner->area_id);
        $this->assertSame('قطعة 4 شارع 12', $owner->registered_address);
        $this->assertStringContainsString('محوَّل من زيارة ميدانية #'.$record->id, $owner->notes);
        $this->assertCount(2, $owner->contacts);
        $this->assertSame('الوكيل', $owner->contacts[1]->role);
        $this->assertSame('فهد', $owner->contacts[1]->name);
        $this->assertSame((int) $owner->id, (int) $record->fresh()->converted_owner_id);

        // التحويل مرة ثانية لا ينشئ مالكًا جديدًا
        $this->actingAs($user)->post(route('dashboard.field-owners.convert-owner', $record))->assertRedirect();
        $this->assertSame(1, PropertyOwner::count());
    }

    public function test_convert_to_owner_warns_about_duplicates_and_can_link_the_existing_owner(): void
    {
        $user = $this->userWith(['field_owners.view', 'field_owners.create', 'property_owners.view', 'property_owners.create']);
        $existing = PropertyOwner::create(['name' => 'مالك قديم', 'phone_code' => '+965', 'phone' => '99001122']);
        $this->actingAs($user)->post(route('dashboard.field-owners.store'), $this->payload())->assertSessionHasNoErrors();
        $record = FieldOwner::firstOrFail();

        $this->actingAs($user)->get(route('dashboard.field-owners.show', $record))
            ->assertOk()
            ->assertSee('يوجد مالك مسجَّل بنفس رقم الهاتف')
            ->assertSee('مالك قديم');

        $this->actingAs($user)->post(route('dashboard.field-owners.convert-owner', $record), ['existing_owner_id' => $existing->id])
            ->assertRedirect(route('dashboard.owners.show', $existing));

        $this->assertSame(1, PropertyOwner::count());
        $this->assertSame((int) $existing->id, (int) $record->fresh()->converted_owner_id);
    }

    public function test_property_create_form_is_prefilled_from_the_field_record(): void
    {
        $user = $this->userWith(['field_owners.view', 'properties.view', 'properties.create']);
        $this->propertyLookups();
        $record = $this->record(['property_number' => 'PACI-777', 'address' => 'قطعة 4 شارع 12', 'latitude' => 29.3375, 'longitude' => 48.0758]);

        $this->actingAs($user)->get(route('dashboard.properties.create', ['field_owner' => $record->id]))
            ->assertOk()
            ->assertSee('name="field_owner_id" value="'.$record->id.'"', false)
            ->assertSee('من الزيارة الميدانية #'.$record->id)
            ->assertSee('https://www.google.com/maps?q=29.3375,48.0758')
            ->assertSee('عقار PACI-777')
            ->assertSee('قطعة 4 شارع 12');
    }

    public function test_property_store_from_field_record_copies_photos_and_converts_the_owner(): void
    {
        Storage::fake('public');
        $user = $this->userWith(['field_owners.view', 'properties.view', 'properties.create']);
        $lookups = $this->propertyLookups();
        $record = $this->record(['latitude' => 29.3375, 'longitude' => 48.0758]);
        $record->contacts()->create(['name' => 'سعد المطيري', 'role' => 'المالك', 'phone_code' => '+965', 'phone' => '99001122']);
        $record->addMedia(UploadedFile::fake()->image('front.jpg', 800, 600))->toMediaCollection('photos');

        $this->actingAs($user)->post(route('dashboard.properties.store'), $this->propertyPayload($lookups, [
            'field_owner_id' => $record->id,
            'latitude' => '29.3375',
            'longitude' => '48.0758',
        ]))->assertSessionHasNoErrors();

        $property = Property::firstOrFail();
        $record->refresh();

        $this->assertNotNull($property->owner_id);
        $this->assertSame('سعد المطيري', $property->owner->name);
        $this->assertCount(1, $property->owner->contacts);
        $this->assertSame((int) $property->owner_id, (int) $record->converted_owner_id);
        $this->assertSame((int) $property->id, (int) $record->converted_property_id);
        $this->assertCount(1, $property->getMedia('gallery'));
        $this->assertCount(1, $record->getMedia('photos'));
        $this->assertEqualsWithDelta(29.3375, (float) $property->latitude, 0.0000001);

        // التكرار مرفوض
        $this->actingAs($user)->post(route('dashboard.properties.store'), $this->propertyPayload($lookups, ['field_owner_id' => $record->id]))
            ->assertSessionHasErrors('field_owner_id');
        $this->assertSame(1, Property::count());
    }

    public function test_property_store_with_a_selected_owner_links_instead_of_creating(): void
    {
        $user = $this->userWith(['field_owners.view', 'properties.view', 'properties.create']);
        $lookups = $this->propertyLookups();
        $existing = PropertyOwner::create(['name' => 'مالك قديم', 'phone_code' => '+965', 'phone' => '55555555']);
        $record = $this->record();

        $this->actingAs($user)->post(route('dashboard.properties.store'), $this->propertyPayload($lookups, [
            'field_owner_id' => $record->id,
            'owner_id' => $existing->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, PropertyOwner::count());
        $record->refresh();
        $this->assertSame((int) $existing->id, (int) $record->converted_owner_id);
        $this->assertSame((int) $existing->id, (int) Property::firstOrFail()->owner_id);
    }

    public function test_permissions_gate_the_module_and_the_sidebar(): void
    {
        $none = User::factory()->create();
        $this->actingAs($none)->get(route('dashboard.field-owners.index'))->assertForbidden();
        $this->actingAs($none)->get(route('dashboard.profile.edit'))->assertOk()->assertDontSee('ميداني');

        $viewer = $this->userWith(['field_owners.view']);
        $record = $this->record();

        $this->actingAs($viewer)->get(route('dashboard.profile.edit'))->assertOk()->assertSee('ميداني');
        $this->actingAs($viewer)->get(route('dashboard.field-owners.index'))->assertOk()->assertDontSee('إضافة زيارة');
        $this->actingAs($viewer)->get(route('dashboard.field-owners.show', $record))->assertOk()->assertDontSee('حفظ كمالك')->assertDontSee('حفظ كعقار');
        $this->actingAs($viewer)->get(route('dashboard.field-owners.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('dashboard.field-owners.store'), $this->payload())->assertForbidden();
        $this->actingAs($viewer)->post(route('dashboard.field-owners.convert-owner', $record))->assertForbidden();
        $this->actingAs($viewer)->delete(route('dashboard.field-owners.destroy', $record))->assertForbidden();
    }

    // ===== Helpers =====

    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'contacts' => [
                ['name' => 'سعد المطيري', 'role' => 'المالك', 'phone_code' => '+965', 'phone' => '99001122'],
                ['name' => 'فهد', 'role' => 'الوكيل', 'phone_code' => '+966', 'phone' => '0501234567'],
            ],
            'contact_method' => 'visit',
            'stage' => 'new',
            'notes' => 'يفضل التواصل مساءً',
            'property_number' => 'PACI-777',
            'city_id' => $this->city->id,
            'area_id' => $this->area->id,
            'address' => 'قطعة 4 شارع 12',
            'latitude' => '29.3375',
            'longitude' => '48.0758',
        ], $overrides);
    }

    private function record(array $overrides = []): FieldOwner
    {
        return FieldOwner::forceCreate(array_merge([
            'name' => 'سعد المطيري',
            'phone_code' => '+965',
            'phone' => '99001122',
            'contact_method' => 'visit',
            'stage' => 'new',
            'city_id' => $this->city->id,
            'area_id' => $this->area->id,
        ], $overrides));
    }

    /** @return array{category: PropertyCategory, unitType: UnitType, status: PropertyStatus} */
    private function propertyLookups(): array
    {
        return [
            'category' => PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential']),
            'unitType' => UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment'], 'category' => 'residential']),
            'status' => PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']),
        ];
    }

    private function propertyPayload(array $lookups, array $overrides = []): array
    {
        return array_merge([
            'title' => ['ar' => 'شقة السالمية', 'en' => 'Salmiya flat'],
            'city_id' => $this->city->id,
            'area_id' => $this->area->id,
            'category_id' => $lookups['category']->id,
            'unit_type_id' => $lookups['unitType']->id,
            'purpose' => 'rent',
            'price' => 450,
            'price_period' => 'monthly',
            'status_id' => $lookups['status']->id,
            'contacts' => [['phone_code' => '+965', 'phone' => '99887766', 'role' => 'الحارس', 'name' => 'أبو خالد']],
            'amenities' => [],
        ], $overrides);
    }
}
