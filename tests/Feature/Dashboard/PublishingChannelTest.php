<?php

namespace Tests\Feature\Dashboard;

use App\Models\Property;
use App\Models\PublishingChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PublishingChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_screens_require_the_publishing_channels_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.websites.index'))
            ->assertForbidden();

        $viewer = $this->userWith(['view']);
        $this->actingAs($viewer)
            ->get(route('dashboard.social-channels.index'))
            ->assertOk()
            ->assertSee('السوشال ميديا')
            ->assertDontSee('@click="startAdd()"', false);

        $this->actingAs($viewer)
            ->post(route('dashboard.websites.store'), ['name' => 'OLX'])
            ->assertForbidden();
    }

    public function test_websites_and_social_channels_are_separate_lists_with_uploaded_icons(): void
    {
        Storage::fake('public');
        $user = $this->userWith(['view', 'create', 'edit', 'delete']);

        $this->actingAs($user)->post(route('dashboard.websites.store'), [
            'name' => 'OLX',
            'url' => 'https://www.olx.com.kw',
            'is_active' => 1,
            'icon' => UploadedFile::fake()->image('olx.png', 64, 64),
        ])->assertSessionHasNoErrors();

        $svg = UploadedFile::fake()->createWithContent('facebook.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>');
        $this->actingAs($user)->post(route('dashboard.social-channels.store'), [
            'name' => 'Facebook',
            'url' => 'https://www.facebook.com/alam',
            'is_active' => 1,
            'icon' => $svg,
        ])->assertSessionHasNoErrors();

        $olx = PublishingChannel::where('name', 'OLX')->firstOrFail();
        $facebook = PublishingChannel::where('name', 'Facebook')->firstOrFail();
        $this->assertSame('website', $olx->kind);
        $this->assertSame('social', $facebook->kind);
        $this->assertNotNull($olx->icon_url);
        $this->assertNotNull($facebook->icon_url);

        $this->actingAs($user)->get(route('dashboard.websites.index'))->assertOk()->assertSee('OLX')->assertDontSee('Facebook');
        $this->actingAs($user)->get(route('dashboard.social-channels.index'))->assertOk()->assertSee('Facebook')->assertDontSee('OLX');

        // تعديل بحذف الأيقونة
        $this->actingAs($user)->put(route('dashboard.websites.update', $olx), [
            'name' => 'OLX Kuwait',
            'url' => 'https://www.olx.com.kw',
            'is_active' => 0,
            'icon_removed' => 1,
        ])->assertSessionHasNoErrors();

        $olx->refresh();
        $this->assertSame('OLX Kuwait', $olx->name);
        $this->assertFalse($olx->is_active);
        $this->assertNull($olx->icon_url);

        // لا يمكن تعديل قناة سوشال من مسار المواقع
        $this->actingAs($user)->put(route('dashboard.websites.update', $facebook), ['name' => 'X'])->assertNotFound();
    }

    public function test_channel_with_published_properties_cannot_be_deleted(): void
    {
        $user = $this->userWith(['view', 'delete']);
        $channel = PublishingChannel::create(['kind' => 'website', 'name' => 'OLX']);
        $property = Property::create(['reference_code' => '5', 'title' => ['ar' => 'عقار', 'en' => 'P']]);
        $property->channels()->attach($channel->id, ['url' => 'https://olx.com/1']);

        $this->actingAs($user)->delete(route('dashboard.websites.destroy', $channel))->assertSessionHas('error');
        $this->assertDatabaseHas('publishing_channels', ['id' => $channel->id]);

        $property->channels()->detach();
        $this->actingAs($user)->delete(route('dashboard.websites.destroy', $channel))->assertSessionHas('success');
        $this->assertDatabaseMissing('publishing_channels', ['id' => $channel->id]);
    }

    private function userWith(array $actions): User
    {
        $user = User::factory()->create();

        foreach ($actions as $action) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => "publishing_channels.{$action}", 'guard_name' => 'web']));
        }

        return $user;
    }
}
