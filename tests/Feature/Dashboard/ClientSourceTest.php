<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\MarketingSource;
use App\Models\MarketingSourceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** «مصدر العميل» في بيانات المتابعة: يُختار من مصادر التسويق عند الإضافة والتعديل ويظهر في عرض العميل */
class ClientSourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MarketingSource $instagram;

    private MarketingSource $oldSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        foreach (['clients.view', 'clients.view_all', 'clients.create', 'clients.edit', 'clients.assign'] as $name) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $social = MarketingSourceType::create(['name' => ['ar' => 'سوشال ميديا', 'en' => 'Social'], 'is_active' => true]);
        $this->instagram = MarketingSource::create(['name' => 'إعلان إنستجرام', 'type_id' => $social->id, 'cost' => 0, 'status' => 'active']);
        $this->oldSource = MarketingSource::create(['name' => 'حملة قديمة', 'type_id' => $social->id, 'cost' => 0, 'status' => 'inactive']);
    }

    public function test_add_form_offers_active_sources_grouped_by_type(): void
    {
        $html = $this->actingAs($this->user)->get(route('dashboard.clients.index'))
            ->assertOk()
            ->assertSee('مصدر العميل')
            ->getContent();

        // قائمة فورم الإضافة (غير قائمة الفلتر التي تعرض كل المصادر)
        $this->assertSame(1, preg_match('~<select name="source_id" class=.*?</select>~su', $html, $m));
        $this->assertMatchesRegularExpression('~<optgroup label="سوشال ميديا">\s*<option value="\d+"\s*>إعلان إنستجرام~u', $m[0]);
        $this->assertStringNotContainsString('حملة قديمة', $m[0]);
    }

    public function test_source_is_saved_on_create_and_update_and_shown_on_the_client_page(): void
    {
        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل من إنستجرام', 'phone_code' => '+965', 'phone' => '55001122',
            'source_id' => $this->instagram->id,
        ])->assertSessionHasNoErrors();

        $client = Client::where('name', 'عميل من إنستجرام')->firstOrFail();
        $this->assertSame($this->instagram->id, (int) $client->source_id);

        $this->actingAs($this->user)->get(route('dashboard.clients.show', $client))
            ->assertOk()
            ->assertSeeInOrder(['المصدر', 'إعلان إنستجرام']);

        $this->actingAs($this->user)->put(route('dashboard.clients.update', $client), [
            'name' => 'عميل من إنستجرام', 'phone_code' => '+965', 'phone' => '55001122', 'source_id' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($client->fresh()->source_id);
    }

    public function test_an_inactive_source_already_on_the_client_stays_selectable_in_edit(): void
    {
        $client = Client::create(['name' => 'عميل قديم', 'phone_code' => '+965', 'phone' => '55003344', 'source_id' => $this->oldSource->id]);

        $this->actingAs($this->user)->get(route('dashboard.clients.show', $client))
            ->assertOk()
            ->assertSee('value="'.$this->oldSource->id.'" selected', false);
    }

    public function test_unknown_source_is_rejected(): void
    {
        $this->actingAs($this->user)->post(route('dashboard.clients.store'), [
            'name' => 'عميل', 'phone_code' => '+965', 'phone' => '55005566', 'source_id' => 99999,
        ])->assertSessionHasErrors('source_id');
    }
}
