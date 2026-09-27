<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\City;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyOwner;
use App\Models\PropertyStatus;
use App\Models\UnitType;
use App\Models\User;
use App\Services\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PropertyFormDependenciesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private City $city;

    private Area $area;

    private Area $otherArea;

    private PropertyCategory $residential;

    private PropertyCategory $commercial;

    private UnitType $apartment;

    private UnitType $office;

    private PropertyStatus $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        foreach (['properties.view', 'properties.create', 'properties.edit'] as $name) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $this->city = City::create(['name' => ['ar' => 'محافظة حولي', 'en' => 'Hawalli']]);
        $otherCity = City::create(['name' => ['ar' => 'محافظة الأحمدي', 'en' => 'Ahmadi']]);
        $this->area = Area::create(['name' => ['ar' => 'السالمية', 'en' => 'Salmiya'], 'city_id' => $this->city->id]);
        $this->otherArea = Area::create(['name' => ['ar' => 'الفنطاس', 'en' => 'Fintas'], 'city_id' => $otherCity->id]);
        $this->residential = PropertyCategory::create(['name' => ['ar' => 'سكني', 'en' => 'Residential'], 'key' => 'residential']);
        $this->commercial = PropertyCategory::create(['name' => ['ar' => 'تجاري', 'en' => 'Commercial'], 'key' => 'commercial']);
        $this->apartment = UnitType::create(['name' => ['ar' => 'شقة', 'en' => 'Apartment'], 'category' => 'residential']);
        $this->office = UnitType::create(['name' => ['ar' => 'مكتب', 'en' => 'Office'], 'category' => 'commercial']);
        $this->status = PropertyStatus::create(['name' => ['ar' => 'متاح', 'en' => 'Available'], 'key' => 'available']);
    }

    public function test_reference_codes_are_numeric_and_increment(): void
    {
        Property::create(['reference_code' => '7', 'title' => ['ar' => 'قديم', 'en' => 'Old']]);

        $service = app(PropertyService::class);
        $this->assertSame('8', $service->generateReferenceCode());

        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $created = Property::latest('id')->firstOrFail();
        $this->assertSame('8', $created->reference_code);
        $this->assertSame($this->city->id, $created->city_id);
        $this->assertSame('https://maps.app.goo.gl/abc', $created->map_url);
        $this->assertSame('برج السالمية', $created->building_name);
        $this->assertSame('2.50', $created->owner_commission_rate);
        // حارس العقار: رقمه يُطبَّع (أرقام فقط بلا صفر بادئ)
        $this->assertSame('أبو خالد', $created->guard_name);
        $this->assertSame('+965', $created->guard_phone_code);
        $this->assertSame('99887766', $created->guard_phone);
        $this->assertSame('96599887766', $created->guard_whatsapp_number);
        $this->assertTrue($created->is_furnished);
        $this->assertSame(3, $created->bedrooms);
    }

    public function test_responsibles_are_picked_from_the_selected_owners_contacts(): void
    {
        $owner = PropertyOwner::create(['name' => 'أبو خالد', 'phone_code' => '+965', 'phone' => '55112233']);
        $self = $owner->contacts()->create(['phone_code' => '+965', 'phone' => '55112233', 'role' => 'المالك', 'name' => 'أبو خالد', 'sort_order' => 0]);
        $agent = $owner->contacts()->create(['phone_code' => '+966', 'phone' => '501234567', 'role' => 'الوكيل', 'name' => 'سالم', 'sort_order' => 1]);
        $other = PropertyOwner::create(['name' => 'مالك آخر', 'phone_code' => '+965', 'phone' => '66000000']);
        $foreign = $other->contacts()->create(['phone_code' => '+965', 'phone' => '66000000', 'role' => 'المالك', 'name' => 'فهد']);

        // المالك له مسؤولون ⇒ يُختار واحد على الأقل
        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload(['owner_id' => $owner->id]))
            ->assertSessionHasErrors('responsibles');

        // مسؤول من مالك آخر مرفوض
        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload([
            'owner_id' => $owner->id, 'responsibles' => [$foreign->id],
        ]))->assertSessionHasErrors('responsibles.0');

        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload([
            'owner_id' => $owner->id, 'responsibles' => [$agent->id, $self->id],
        ]))->assertSessionHasNoErrors();

        $property = Property::latest('id')->firstOrFail();
        // بترتيب مسؤولي المالك لا بترتيب الاختيار
        $this->assertSame([$self->id, $agent->id], $property->responsibles->pluck('id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame('الوكيل · سالم', $property->responsibles->last()->label());

        $this->actingAs($this->user)->get(route('dashboard.properties.show', $property))
            ->assertOk()
            ->assertSee('المسؤولون عن العقار')
            ->assertSee('الوكيل · سالم')
            ->assertSee('wa.me/966501234567')
            ->assertSee('حارس العقار')
            ->assertSee('wa.me/96599887766');

        // تعديل: إلغاء أحدهما
        $this->actingAs($this->user)->put(route('dashboard.properties.update', $property), $this->payload([
            'owner_id' => $owner->id, 'responsibles' => [$agent->id],
        ]))->assertSessionHasNoErrors();
        $this->assertSame([$agent->id], $property->fresh()->responsibles->pluck('id')->map(fn ($id) => (int) $id)->all());

        // تغيير المالك يستبدل المسؤولين بمسؤولي المالك الجديد
        $this->actingAs($this->user)->put(route('dashboard.properties.update', $property), $this->payload([
            'owner_id' => $other->id, 'responsibles' => [$foreign->id],
        ]))->assertSessionHasNoErrors();
        $this->assertSame([$foreign->id], $property->fresh()->responsibles->pluck('id')->map(fn ($id) => (int) $id)->all());

        // حذف المسؤول من المالك يفكّ ربطه بالعقار
        $foreign->delete();
        $this->assertCount(0, $property->fresh()->responsibles);

        // المالك بلا مسؤولين مسجّلين لا يمنع الحفظ، والحارس اختياري
        $this->actingAs($this->user)->put(route('dashboard.properties.update', $property), $this->payload([
            'owner_id' => $other->id, 'guard_name' => '', 'guard_phone' => '',
        ]))->assertSessionHasNoErrors();
        $property->refresh();
        $this->assertNull($property->guard_phone);
        $this->assertNull($property->guard_phone_code);
        $this->assertFalse($property->hasGuard());
        $this->actingAs($this->user)->get(route('dashboard.properties.show', $property))
            ->assertOk()->assertSee('رقم الحارس:');
    }

    public function test_the_owners_own_number_is_labelled_with_the_owner_name(): void
    {
        // رقم المالك الأساسي بلا اسم ولا صفة (كما يُنشأ للملاك القدامى)
        $owner = PropertyOwner::create(['name' => 'هيا السالم', 'phone_code' => '+965', 'phone' => '99667788']);
        $own = $owner->contacts()->create(['phone_code' => '+965', 'phone' => '99667788']);
        $other = $owner->contacts()->create(['phone_code' => '+965', 'phone' => '66000000']);
        $property = Property::create(['reference_code' => '41', 'title' => ['ar' => 'شقة', 'en' => 'Flat'], 'owner_id' => $owner->id]);
        $property->responsibles()->attach([$own->id, $other->id]);

        $this->assertSame('المالك · هيا السالم', $own->label($owner));
        $this->assertSame('مسؤول العقار', $other->label($owner));
        $this->assertSame('مسؤول العقار', $own->label());

        $this->actingAs($this->user)->get(route('dashboard.properties.show', $property))
            ->assertOk()->assertSee('المالك · هيا السالم');
    }

    public function test_commission_location_guard_and_responsibles_always_show_for_any_viewer(): void
    {
        // مستخدم بصلاحية عرض العقارات فقط (مثل مندوب المبيعات) — بلا صلاحية الملاك
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(Permission::firstOrCreate(['name' => 'properties.view', 'guard_name' => 'web']));

        $owner = PropertyOwner::create(['name' => 'أبو خالد', 'phone_code' => '+965', 'phone' => '55112233']);
        $agent = $owner->contacts()->create(['phone_code' => '+965', 'phone' => '66000000', 'role' => 'الوكيل', 'name' => 'سالم']);
        $full = Property::create([
            'reference_code' => '51', 'title' => ['ar' => 'شقة كاملة', 'en' => 'Full'], 'owner_id' => $owner->id,
            'area_id' => $this->area->id, 'city_id' => $this->city->id, 'block' => '4', 'street' => '12',
            'map_url' => 'https://maps.app.goo.gl/xyz', 'owner_commission_rate' => 2.5,
            'guard_name' => 'أبو فهد', 'guard_phone_code' => '+965', 'guard_phone' => '99001122',
        ]);
        $full->responsibles()->attach($agent->id);

        $this->actingAs($viewer)->get(route('dashboard.properties.show', $full))
            ->assertOk()
            ->assertSee('نسبة العمولة من المالك')->assertSee('2.5%')
            ->assertSee('الموقع')->assertSee('قطعة 4 · شارع 12')->assertSee('https://maps.app.goo.gl/xyz')
            ->assertSee('رقم الحارس:')->assertSee('+965 99001122')->assertSee('أبو فهد')
            ->assertSee('المسؤولون عن العقار')->assertSee('الوكيل · سالم')->assertSee('+965 66000000');

        // عقار بلا أي من هذه البيانات: الأقسام نفسها تظهر بقيم فارغة
        $empty = Property::create(['reference_code' => '52', 'title' => ['ar' => 'شقة فارغة', 'en' => 'Empty']]);

        $this->actingAs($viewer)->get(route('dashboard.properties.show', $empty))
            ->assertOk()
            ->assertSee('نسبة العمولة من المالك')
            ->assertSee('الموقع')->assertSee('لا يوجد رابط موقع على خرائط جوجل')
            ->assertSee('حارس العقار')->assertSee('رقم الحارس:')
            ->assertSee('المسؤولون عن العقار')->assertSee('لم يُختر مسؤول عن هذا العقار بعد');
    }

    public function test_guard_phone_must_be_digits(): void
    {
        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload(['guard_phone' => '12']))
            ->assertSessionHasErrors('guard_phone');
    }

    public function test_edit_form_shows_the_owner_responsibles_picker_and_the_guard(): void
    {
        $owner = PropertyOwner::create(['name' => 'أبو خالد', 'phone_code' => '+965', 'phone' => '55112233']);
        $contact = $owner->contacts()->create(['phone_code' => '+965', 'phone' => '55112233', 'role' => 'المالك', 'name' => 'أبو خالد']);
        $property = Property::create([
            'reference_code' => '31', 'title' => ['ar' => 'شقة', 'en' => 'Flat'], 'owner_id' => $owner->id,
            'guard_name' => 'أبو فهد', 'guard_phone_code' => '+965', 'guard_phone' => '99001122',
        ]);
        $property->responsibles()->attach($contact->id);

        $this->actingAs($this->user)->get(route('dashboard.properties.edit', $property))
            ->assertOk()
            ->assertSee('المالك والمسؤولون')
            ->assertSee('propertyPeople(', false)
            ->assertSee('name="guard_name"', false)
            ->assertSee('value="أبو فهد"', false)
            ->assertSee('99001122')
            ->assertSee('<option value="'.$owner->id.'" selected', false);
    }

    public function test_area_must_belong_to_the_selected_governorate(): void
    {
        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload([
            'city_id' => $this->city->id,
            'area_id' => $this->otherArea->id,
        ]))->assertSessionHasErrors('area_id');

        // بدون محافظة → تُستنتج من المنطقة
        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload([
            'city_id' => '',
            'area_id' => $this->otherArea->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($this->otherArea->city_id, Property::latest('id')->firstOrFail()->city_id);
    }

    public function test_unit_type_must_match_the_category_and_bedrooms_are_dropped_for_commercial(): void
    {
        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload([
            'category_id' => $this->commercial->id,
            'unit_type_id' => $this->apartment->id,
        ]))->assertSessionHasErrors('unit_type_id');

        $this->actingAs($this->user)->post(route('dashboard.properties.store'), $this->payload([
            'category_id' => $this->commercial->id,
            'unit_type_id' => $this->office->id,
            'bedrooms' => 4,
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Property::latest('id')->firstOrFail()->bedrooms);
    }

    public function test_property_form_lists_only_the_four_statuses_and_governorates(): void
    {
        PropertyStatus::create(['name' => ['ar' => 'قيد الإنتظار', 'en' => 'Pending'], 'key' => 'pending']);

        $this->actingAs($this->user)->get(route('dashboard.properties.create'))
            ->assertOk()
            ->assertSee('المحافظة')
            ->assertSee('مندوب المبيعات')
            ->assertSee('رابط موقع العقار على خرائط جوجل')
            ->assertSee('عمولة المالك')
            ->assertSee('قيد الإنتظار')
            ->assertDontSee('محجوز')
            ->assertDontSee('مسؤول العقار');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => ['ar' => 'شقة السالمية', 'en' => 'Salmiya flat'],
            'city_id' => $this->city->id,
            'area_id' => $this->area->id,
            'category_id' => $this->residential->id,
            'unit_type_id' => $this->apartment->id,
            'purpose' => 'rent',
            'price' => 450,
            'price_period' => 'monthly',
            'status_id' => $this->status->id,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'is_furnished' => 1,
            'building_name' => 'برج السالمية',
            'map_url' => 'https://maps.app.goo.gl/abc',
            'owner_commission_rate' => 2.5,
            'guard_name' => 'أبو خالد',
            'guard_phone_code' => '+965',
            'guard_phone' => '0 9988-7766',
            'amenities' => [],
        ], $overrides);
    }
}
