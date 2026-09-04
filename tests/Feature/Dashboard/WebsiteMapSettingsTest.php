<?php

namespace Tests\Feature\Dashboard;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WebsiteMapSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_save_contact_map_location(): void
    {
        $user = User::factory()->create();
        foreach (['website.view', 'website.edit'] as $permission) {
            $user->givePermissionTo(Permission::create(['name' => $permission, 'guard_name' => 'web']));
        }

        $this->actingAs($user)->put(route('dashboard.website.settings'), [
            'contact_phone' => '+965 0000 0000',
            'contact_email' => 'map@example.com',
            'contact_address' => 'Kuwait',
            'contact_whatsapp' => '+96500000000',
            'contact_map_lat' => '29.3759000',
            'contact_map_lng' => '47.9774000',
        ])->assertSessionHasNoErrors();

        $this->assertEquals('29.3759000', Setting::get('contact', 'map_lat'));
        $this->assertEquals('47.9774000', Setting::get('contact', 'map_lng'));

        $this->get(route('site.contact'))
            ->assertOk()
            ->assertSee('marker=29.3759%2C47.9774', false);
    }

    public function test_invalid_map_coordinates_are_rejected(): void
    {
        $user = User::factory()->create();
        foreach (['website.view', 'website.edit'] as $permission) {
            $user->givePermissionTo(Permission::create(['name' => $permission, 'guard_name' => 'web']));
        }

        $this->actingAs($user)->put(route('dashboard.website.settings'), [
            'contact_map_lat' => '120',
            'contact_map_lng' => '220',
        ])->assertSessionHasErrors(['contact_map_lat', 'contact_map_lng']);
    }
}
