<?php

namespace Tests\Feature\Dashboard;

use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WebsiteTestimonialAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_upload_and_remove_a_testimonial_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        foreach (['website.view', 'website.edit'] as $permission) {
            $user->givePermissionTo(Permission::create(['name' => $permission, 'guard_name' => 'web']));
        }

        $this->actingAs($user)->post(route('dashboard.website.testimonials.store'), [
            'name' => 'محمد أحمد',
            'title_ar' => 'عميل',
            'title_en' => '',
            'content_ar' => 'تجربة ممتازة',
            'content_en' => '',
            'rating' => 5,
            'avatar' => UploadedFile::fake()->image('person.jpg', 400, 400),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $testimonial = Testimonial::firstOrFail();
        $this->assertCount(1, $testimonial->getMedia('avatar'));
        $this->assertNotNull($testimonial->avatar_url);

        $this->actingAs($user)->put(route('dashboard.website.testimonials.update', $testimonial), [
            'name' => $testimonial->name,
            'title_ar' => $testimonial->getTranslation('title', 'ar'),
            'title_en' => '',
            'content_ar' => $testimonial->getTranslation('content', 'ar'),
            'content_en' => '',
            'rating' => $testimonial->rating,
            'avatar_removed' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertCount(0, $testimonial->fresh()->getMedia('avatar'));
    }
}
