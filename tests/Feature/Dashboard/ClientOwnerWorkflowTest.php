<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientStage;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use App\Services\ClientService;
use App\Services\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClientOwnerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_client_fields_and_recording_employee_are_saved(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['clients.view', 'clients.create']);
        $stage = ClientStage::where('key', 'new')->firstOrFail();
        $unitType = UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment']]);

        $this->actingAs($user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل الاختبار',
            'phone_code' => '+965',
            'phone' => '99999999',
            'needs' => [
                ['unit_type_id' => $unitType->id, 'city_id' => '', 'area_id' => ''],
            ],
            'social_status' => 'family',
            'nationality' => 'كويتي',
            'household_size' => 5,
            'workplace' => 'شركة الاختبار',
            'preferred_contact' => 'whatsapp',
        ])->assertSessionHasNoErrors();

        $client = Client::where('phone', '99999999')->firstOrFail();
        $this->assertSame($stage->id, $client->stage_id);
        $this->assertSame($user->id, $client->recorded_by);
        $this->assertSame('+965', $client->phone_code);
        $this->assertSame($unitType->id, $client->needs->first()->unit_type_id);
        $this->assertSame('family', $client->social_status);
        $this->assertSame(5, $client->household_size);
        $this->assertSame('whatsapp', $client->preferred_contact);
        $this->assertNotNull($client->type_id); // مستأجر افتراضياً
    }

    public function test_property_cannot_be_added_twice_to_the_same_client(): void
    {
        [$available] = $this->propertyStatuses();
        $client = Client::create(['name' => 'عميل أول', 'phone' => '111']);
        $property = Property::create([
            'reference_code' => 'ALM-901', 'title' => ['ar' => 'عقار اختبار', 'en' => 'Test'],
            'status_id' => $available->id,
        ]);
        $service = app(ClientService::class);

        $service->attachProperty($client, $property->id, 'interested');

        try {
            $service->attachProperty($client, $property->id, 'viewed');
            $this->fail('Expected duplicate property validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame('هذا العقار مضاف بالفعل لهذا العميل.', $exception->errors()['property_id'][0]);
        }

        $this->assertDatabaseCount('client_property', 1);
    }

    public function test_reserving_property_updates_status_and_blocks_another_client(): void
    {
        [$available, $reserved] = $this->propertyStatuses();
        $first = Client::create(['name' => 'عميل أول', 'phone' => '111']);
        $second = Client::create(['name' => 'عميل ثان', 'phone' => '222']);
        $property = Property::create([
            'reference_code' => 'ALM-902', 'title' => ['ar' => 'عقار حجز', 'en' => 'Reserved Test'],
            'status_id' => $available->id,
        ]);
        $service = app(ClientService::class);

        $service->attachProperty($first, $property->id, 'reserved');
        $this->assertSame($reserved->id, $property->refresh()->status_id);
        $this->assertDatabaseHas('property_reservations', [
            'property_id' => $property->id,
            'client_id' => $first->id,
            'active_property_id' => $property->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('client_property', [
            'property_id' => $property->id,
            'client_id' => $first->id,
            'relation' => 'interested',
        ]);

        try {
            $service->attachProperty($second, $property->id, 'interested');
            $this->fail('Expected reserved property validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('محجوز', $exception->errors()['property_id'][0]);
            $this->assertStringContainsString('عميل أول', $exception->errors()['property_id'][0]);
        }

        try {
            $service->detachProperty($first, $property->id);
            $this->fail('Expected active reservation detach validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('ألغِ الحجز', $exception->errors()['property_id'][0]);
        }

        $service->releaseReservation($first, $property->id);
        $this->assertSame($available->id, $property->refresh()->status_id);
        $this->assertDatabaseHas('property_reservations', [
            'property_id' => $property->id,
            'client_id' => $first->id,
            'active_property_id' => null,
            'status' => 'cancelled',
        ]);

        $service->attachProperty($second, $property->id, 'interested');
        $this->assertDatabaseHas('client_property', [
            'property_id' => $property->id,
            'client_id' => $second->id,
            'relation' => 'interested',
        ]);
    }

    public function test_reserved_status_cannot_be_selected_without_an_active_reservation(): void
    {
        [$available, $reserved] = $this->propertyStatuses();
        $property = Property::create([
            'reference_code' => 'ALM-904',
            'title' => ['ar' => 'عقار متاح', 'en' => 'Available Property'],
            'status_id' => $available->id,
        ]);

        try {
            app(PropertyService::class)->update($property, ['status_id' => $reserved->id]);
            $this->fail('Expected manual reserved status validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('شاشة العميل', $exception->errors()['status_id'][0]);
        }

        $this->assertSame($available->id, $property->refresh()->status_id);
    }

    public function test_deleting_client_releases_any_active_property_reservations(): void
    {
        [$available] = $this->propertyStatuses();
        $client = Client::create(['name' => 'عميل سيُحذف', 'phone' => '555']);
        $property = Property::create([
            'reference_code' => 'ALM-905',
            'title' => ['ar' => 'عقار حجز العميل', 'en' => 'Client Reservation'],
            'status_id' => $available->id,
        ]);
        $service = app(ClientService::class);

        $service->reserveProperty($client, $property->id);
        $service->delete($client);

        $this->assertSame('available', $property->refresh()->status?->key);
        $this->assertDatabaseMissing('property_reservations', ['client_id' => $client->id]);
    }

    public function test_owner_contract_can_be_uploaded_and_owner_profile_opened(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['property_owners.view', 'property_owners.create']);

        $this->actingAs($user)->post(route('dashboard.owners.store'), [
            'name' => 'مالك الاختبار',
            'phone' => '33333333',
            'status' => 'active',
            'contract' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $owner = PropertyOwner::where('phone', '33333333')->firstOrFail();
        $this->assertCount(1, $owner->getMedia('contract'));
        $this->actingAs($user)->get(route('dashboard.owners.show', $owner))
            ->assertOk()
            ->assertSee('حالة العقد')
            ->assertSee('contract.pdf');
    }

    public function test_client_pages_render_notes_and_requested_unit_type(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['clients.view']);
        $unitType = UnitType::create(['name' => ['ar' => 'فيلا', 'en' => 'Villa']]);
        $client = Client::create([
            'name' => 'عميل العرض', 'phone_code' => '+965', 'phone' => '44444444', 'notes' => 'ملاحظة مهمة',
            'recorded_by' => $user->id,
        ]);
        $client->needs()->create(['unit_type_id' => $unitType->id]);

        $this->actingAs($user)->get(route('dashboard.clients.index'))
            ->assertOk()
            ->assertSee('الملاحظات')
            ->assertSee('ملاحظة مهمة')
            ->assertDontSee('كل الوكلاء');

        $this->actingAs($user)->get(route('dashboard.clients.show', $client))
            ->assertOk()
            ->assertSee('نوع الوحدة المطلوبة')
            ->assertSee('فيلا')
            ->assertSee('سجّل البيانات');
    }

    public function test_owner_rows_open_the_owner_profile_on_click(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['property_owners.view']);
        $owner = PropertyOwner::create(['name' => 'مالك النقر', 'phone' => '99000011']);

        $this->actingAs($user)->get(route('dashboard.owners.index'))
            ->assertOk()
            ->assertSee("window.location = '".route('dashboard.owners.show', $owner)."'", false);
    }

    public function test_property_whatsapp_message_contains_reference_code(): void
    {
        [$available] = $this->propertyStatuses();
        $manager = User::factory()->create(['phone' => '+965 9999 9999']);
        $property = Property::create([
            'reference_code' => 'ALM-903',
            'title' => ['ar' => 'عقار واتساب', 'en' => 'WhatsApp Property'],
            'status_id' => $available->id,
            'agent_id' => $manager->id,
        ]);

        $this->get(route('site.property', $property))
            ->assertOk()
            ->assertSee(urlencode('مرحباً، أود الاستفسار عن العقار رقم ALM-903'), false);
    }

    /** @return array{PropertyStatus, PropertyStatus} */
    private function propertyStatuses(): array
    {
        $available = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
        $reserved = PropertyStatus::create(['name' => ['ar' => 'محجوز', 'en' => 'Reserved'], 'key' => 'reserved']);
        PropertyStatus::create(['name' => ['ar' => 'مباع', 'en' => 'Sold'], 'key' => 'sold']);

        return [$available, $reserved];
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::create(['name' => $name, 'guard_name' => 'web']));
        }
    }
}
