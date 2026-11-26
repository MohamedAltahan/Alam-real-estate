<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\MarketingSource;
use App\Models\MarketingSourceType;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** فلترا «مصدر العميل» و«حضوري» في شاشة العملاء */
class ClientSourceAndInPersonFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_can_be_filtered_by_source_and_in_person_viewings(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'clients.view', 'guard_name' => 'web']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'clients.view_all', 'guard_name' => 'web']));

        $type = MarketingSourceType::create(['name' => ['ar' => 'سوشال', 'en' => 'Social'], 'is_active' => true]);
        $insta = MarketingSource::create(['name' => 'إنستجرام', 'type_id' => $type->id, 'cost' => 0, 'status' => 'active']);
        $google = MarketingSource::create(['name' => 'جوجل', 'type_id' => $type->id, 'cost' => 0, 'status' => 'inactive']);

        $property = Property::create(['reference_code' => '41', 'title' => ['ar' => 'عقار', 'en' => 'P']]);
        $visited = Client::create(['name' => 'عميل حضر', 'phone_code' => '+965', 'phone' => '55000101', 'source_id' => $insta->id]);
        $visited->viewings()->create(['property_id' => $property->id, 'scheduled_at' => now()->addDay(), 'in_person' => true]);
        $remote = Client::create(['name' => 'عميل عن بعد', 'phone_code' => '+965', 'phone' => '55000102', 'source_id' => $google->id]);
        $remote->viewings()->create(['property_id' => $property->id, 'scheduled_at' => now()->addDay(), 'in_person' => false]);
        Client::create(['name' => 'عميل بلا معاينة', 'phone_code' => '+965', 'phone' => '55000103']);

        $list = fn (array $q) => $this->actingAs($user)->get(route('dashboard.clients.index', $q))->assertOk();

        // الفلتران ظاهران، والمصدر المتوقف ضمن الخيارات
        $list([])->assertSee('name="source_id" form="clients-filters"', false)->assertSee('name="in_person" form="clients-filters"', false)
            ->assertSee('جوجل');

        $list(['source_id' => $insta->id])->assertSee('عميل حضر')->assertDontSee('عميل عن بعد')->assertDontSee('عميل بلا معاينة');
        $list(['source_id' => $google->id])->assertSee('عميل عن بعد')->assertDontSee('عميل حضر');

        $list(['in_person' => '1'])->assertSee('عميل حضر')->assertDontSee('عميل عن بعد')->assertDontSee('عميل بلا معاينة');
        $list(['in_person' => '0'])->assertSee('عميل عن بعد')->assertSee('عميل بلا معاينة')->assertDontSee('عميل حضر');
        $list(['in_person' => ''])->assertSee('عميل حضر')->assertSee('عميل عن بعد');
    }
}
