<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientStage;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_owner_files_can_be_uploaded_and_owner_profile_opened(): void
    {
        $user = User::factory()->create();
        $this->grant($user, ['property_owners.view', 'property_owners.create']);

        $this->actingAs($user)->post(route('dashboard.owners.store'), [
            'name' => 'مالك الاختبار',
            'contacts' => [
                ['phone_code' => '+965', 'phone' => '33333333', 'role' => 'المالك', 'name' => 'أبو محمد'],
            ],
            'files' => [
                UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('sheet.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ],
        ])->assertSessionHasNoErrors();

        $owner = PropertyOwner::where('phone', '33333333')->firstOrFail();
        $this->assertSame('+965', $owner->phone_code);
        $this->assertCount(2, $owner->getMedia('files'));
        $this->assertSame('المالك', $owner->contacts->first()->role);

        $this->actingAs($user)->get(route('dashboard.owners.show', $owner))
            ->assertOk()
            ->assertSee('ملفات المالك')
            ->assertSee('contract.pdf')
            ->assertSee('أبو محمد')
            ->assertDontSee('حالة العقد');
    }

    public function test_client_files_are_uploaded_listed_and_deleted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->grant($user, ['clients.view', 'clients.create', 'clients.edit']);

        $this->actingAs($user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل الملفات',
            'phone_code' => '+965',
            'phone' => '55667788',
            'files' => [
                UploadedFile::fake()->create('id-card.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('unit.jpg'),
            ],
        ])->assertSessionHasNoErrors();

        $client = Client::where('phone', '55667788')->firstOrFail();
        $this->assertCount(2, $client->getMedia(Client::FILES));
        $this->assertDatabaseHas('client_audit_logs', ['client_id' => $client->id, 'action' => 'file_added', 'user_id' => $user->id]);

        // الملفات تظهر في صفحة العميل وفي فورم التعديل
        $this->actingAs($user)->get(route('dashboard.clients.show', $client))
            ->assertOk()
            ->assertSee('ملفات العميل')
            ->assertSee('id-card.pdf');

        // التعديل: حذف ملف وإضافة آخر — بقية البيانات تبقى كما هي
        $pdf = $client->getMedia(Client::FILES)->firstWhere('file_name', 'id-card.pdf');

        $this->actingAs($user)->put(route('dashboard.clients.update', $client), [
            'name' => 'عميل الملفات',
            'phone_code' => '+965',
            'phone' => '55667788',
            'files_removed' => [$pdf->id],
            'files' => [UploadedFile::fake()->create('contract.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
        ])->assertSessionHasNoErrors();

        $names = $client->refresh()->getMedia(Client::FILES)->pluck('file_name')->all();
        $this->assertEqualsCanonicalizing(['unit.jpg', 'contract.docx'], $names);
        $this->assertDatabaseHas('client_audit_logs', ['client_id' => $client->id, 'action' => 'file_removed']);

        // نوع غير مسموح يُرفض
        $this->actingAs($user)->put(route('dashboard.clients.update', $client), [
            'name' => 'عميل الملفات',
            'phone_code' => '+965',
            'phone' => '55667788',
            'files' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertSessionHasErrors('files.0');

        $this->assertCount(2, $client->refresh()->getMedia(Client::FILES));
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
            'reference_code' => '903',
            'title' => ['ar' => 'عقار واتساب', 'en' => 'WhatsApp Property'],
            'status_id' => $available->id,
            'agent_id' => $manager->id,
        ]);

        $this->get(route('site.property', $property))
            ->assertOk()
            ->assertSee(urlencode('مرحباً، أود الاستفسار عن العقار رقم 903'), false);
    }

    /** @return array{PropertyStatus, PropertyStatus} */
    private function propertyStatuses(): array
    {
        $available = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
        $sold = PropertyStatus::create(['name' => ['ar' => 'مباع', 'en' => 'Sold'], 'key' => 'sold']);

        return [$available, $sold];
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::create(['name' => $name, 'guard_name' => 'web']));
        }
    }
}
