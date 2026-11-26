<?php

namespace Tests\Feature\Site;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** أيقونات «تابعنا» في الفوتر: تيك توك بدل X، ويوتيوب جديد */
class FooterSocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_shows_tiktok_and_youtube_and_no_longer_x(): void
    {
        Setting::set('social', 'tiktok', 'https://www.tiktok.com/@alam');
        Setting::set('social', 'youtube', 'https://www.youtube.com/@alam');
        Setting::set('social', 'twitter', 'https://x.com/alam');

        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('href="https://www.tiktok.com/@alam"', false)
            ->assertSee('aria-label="tiktok"', false)
            ->assertSee('href="https://www.youtube.com/@alam"', false)
            ->assertSee('aria-label="youtube"', false)
            ->assertDontSee('https://x.com/alam', false);
    }

    public function test_dashboard_saves_tiktok_and_youtube_links(): void
    {
        $editor = User::factory()->create();
        foreach (['website.view', 'website.edit'] as $name) {
            $editor->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        }

        $this->actingAs($editor)->get(route('dashboard.website.index'))
            ->assertOk()
            ->assertSee('name="social_tiktok"', false)
            ->assertSee('name="social_youtube"', false)
            ->assertDontSee('name="social_twitter"', false);

        $this->actingAs($editor)->put(route('dashboard.website.settings'), [
            'contact_map_lat' => '29.3375',
            'contact_map_lng' => '48.0758',
            'social_tiktok' => 'https://www.tiktok.com/@alam',
            'social_youtube' => 'https://www.youtube.com/@alam',
        ])->assertSessionHasNoErrors();

        $this->assertSame('https://www.tiktok.com/@alam', Setting::get('social', 'tiktok'));
        $this->assertSame('https://www.youtube.com/@alam', Setting::get('social', 'youtube'));
    }
}
