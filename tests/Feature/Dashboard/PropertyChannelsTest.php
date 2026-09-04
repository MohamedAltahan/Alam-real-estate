<?php

namespace Tests\Feature\Dashboard;

use App\Models\Property;
use App\Models\PublishingChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PropertyChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_website_channels_does_not_touch_social_channels(): void
    {
        $user = $this->userWith(['properties.view', 'properties.edit']);
        $olx = PublishingChannel::create(['kind' => 'website', 'name' => 'OLX']);
        $dubizzle = PublishingChannel::create(['kind' => 'website', 'name' => 'Dubizzle']);
        $facebook = PublishingChannel::create(['kind' => 'social', 'name' => 'Facebook']);
        $property = Property::create(['reference_code' => '12', 'title' => ['ar' => 'عقار', 'en' => 'P']]);
        $property->channels()->attach($facebook->id, ['url' => 'https://fb.com/post']);
        $property->channels()->attach($dubizzle->id);

        $this->actingAs($user)->put(route('dashboard.properties.channels.update', $property), [
            'kind' => 'website',
            'channels' => [
                $olx->id => ['on' => 1, 'url' => 'https://www.olx.com.kw/ad/123'],
                $dubizzle->id => ['on' => 0, 'url' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('property_channel', ['property_id' => $property->id, 'channel_id' => $olx->id, 'url' => 'https://www.olx.com.kw/ad/123']);
        $this->assertDatabaseMissing('property_channel', ['property_id' => $property->id, 'channel_id' => $dubizzle->id]);
        $this->assertDatabaseHas('property_channel', ['property_id' => $property->id, 'channel_id' => $facebook->id, 'url' => 'https://fb.com/post']);

        // رابط غير صالح يُرفض
        $this->actingAs($user)->put(route('dashboard.properties.channels.update', $property), [
            'kind' => 'website',
            'channels' => [$olx->id => ['on' => 1, 'url' => 'not a url']],
        ])->assertSessionHasErrors("channels.{$olx->id}.url");
    }

    public function test_properties_index_filters_by_channel_and_shows_counts(): void
    {
        $user = $this->userWith(['properties.view']);
        $olx = PublishingChannel::create(['kind' => 'website', 'name' => 'OLX']);
        $facebook = PublishingChannel::create(['kind' => 'social', 'name' => 'Facebook']);

        $published = Property::create(['reference_code' => '21', 'title' => ['ar' => 'عقار منشور', 'en' => 'Published']]);
        $published->channels()->attach([$olx->id => [], $facebook->id => []]);
        Property::create(['reference_code' => '22', 'title' => ['ar' => 'عقار غير منشور', 'en' => 'Unpublished']]);

        $this->actingAs($user)->get(route('dashboard.properties.index', ['website_id' => $olx->id]))
            ->assertOk()
            ->assertSee('عقار منشور')
            ->assertDontSee('عقار غير منشور')
            ->assertSee('name="social_id"', false);

        $this->actingAs($user)->get(route('dashboard.properties.index', ['social_id' => $facebook->id]))
            ->assertOk()
            ->assertSee('عقار منشور')
            ->assertDontSee('عقار غير منشور');

        $this->actingAs($user)->get(route('dashboard.properties.index'))
            ->assertOk()
            ->assertSee('المواقع')
            ->assertSee('السوشال ميديا')
            ->assertSee('مندوب المبيعات');
    }

    public function test_updating_channels_requires_edit_permission(): void
    {
        $user = $this->userWith(['properties.view']);
        $property = Property::create(['reference_code' => '30', 'title' => ['ar' => 'عقار', 'en' => 'P']]);

        $this->actingAs($user)->put(route('dashboard.properties.channels.update', $property), ['kind' => 'website'])
            ->assertForbidden();
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
