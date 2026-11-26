<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** فلتر نطاق السعر في شاشتي العقارات والعملاء */
class PriceRangeFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Property $cheap;

    private Property $mid;

    private Property $dear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        foreach (['properties.view', 'properties.view_commission', 'clients.view', 'clients.view_all'] as $name) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $this->cheap = Property::create(['reference_code' => '801', 'title' => ['ar' => 'استوديو اقتصادي', 'en' => 'Budget studio'], 'price' => 300, 'purpose' => 'rent']);
        $this->mid = Property::create(['reference_code' => '802', 'title' => ['ar' => 'شقة متوسطة', 'en' => 'Mid flat'], 'price' => 650, 'purpose' => 'rent', 'building_name' => 'برج الحمرا']);
        $this->dear = Property::create(['reference_code' => '803', 'title' => ['ar' => 'بنتهاوس فاخر', 'en' => 'Penthouse'], 'price' => 2500, 'purpose' => 'rent', 'building_name' => 'برج الحمرا']);
    }

    public function test_properties_screen_filters_by_price_range(): void
    {
        $index = fn (array $q) => $this->actingAs($this->user)->get(route('dashboard.properties.index', $q))->assertOk();

        $index([])->assertSee('name="price_from"', false)->assertSee('name="price_to"', false);

        $index(['price_from' => 500, 'price_to' => 1000])
            ->assertSee('شقة متوسطة')
            ->assertDontSee('استوديو اقتصادي')
            ->assertDontSee('بنتهاوس فاخر');

        // حد واحد فقط يكفي
        $index(['price_from' => 1000])->assertSee('بنتهاوس فاخر')->assertDontSee('شقة متوسطة');
        $index(['price_to' => 300])->assertSee('استوديو اقتصادي')->assertDontSee('شقة متوسطة');
    }

    public function test_clients_screen_filters_by_the_price_of_the_linked_property(): void
    {
        Client::create(['name' => 'عميل الاستوديو', 'phone_code' => '+965', 'phone' => '55600001'])
            ->properties()->attach($this->cheap->id, ['relation' => 'interested']);
        Client::create(['name' => 'عميل الشقة', 'phone_code' => '+965', 'phone' => '55600002'])
            ->viewings()->create(['property_id' => $this->mid->id, 'scheduled_at' => now()->addDay()]);
        Client::create(['name' => 'عميل البنتهاوس', 'phone_code' => '+965', 'phone' => '55600003'])
            ->properties()->attach($this->dear->id, ['relation' => 'interested']);

        $index = fn (array $q) => $this->actingAs($this->user)->get(route('dashboard.clients.index', $q))->assertOk();

        $index(['price_from' => 500, 'price_to' => 1000])
            ->assertSee('عميل الشقة')
            ->assertDontSee('عميل الاستوديو')
            ->assertDontSee('عميل البنتهاوس');

        // مع البحث بالمبنى: الشرطان على العقار نفسه
        $index(['property_q' => 'الحمرا', 'price_from' => 1000])
            ->assertSee('عميل البنتهاوس')
            ->assertDontSee('عميل الشقة');
    }
}
