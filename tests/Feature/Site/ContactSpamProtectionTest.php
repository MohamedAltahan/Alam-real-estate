<?php

namespace Tests\Feature\Site;

use App\Models\ContactRequest;
use App\Support\Honeypot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ContactSpamProtectionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'زائر حقيقي',
            'phone_country_code' => '+965',
            'phone' => '55112233',
            'email' => 'visitor@example.com',
            'message' => 'أرغب في الاستفسار عن شقة في السالمية.',
            Honeypot::FIELD => '',
            Honeypot::TIME_FIELD => Crypt::encryptString((string) now()->subSeconds(30)->getTimestamp()),
        ], $overrides);
    }

    public function test_a_real_visitor_can_send_the_contact_form(): void
    {
        $this->post(route('site.contact.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(1, ContactRequest::count());
        $this->assertSame('زائر حقيقي', ContactRequest::first()->name);
    }

    public function test_filling_the_hidden_field_is_rejected(): void
    {
        $this->post(route('site.contact.store'), $this->payload([Honeypot::FIELD => 'https://spam.example']))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, ContactRequest::count());
    }

    public function test_submitting_faster_than_a_human_is_rejected(): void
    {
        $this->post(route('site.contact.store'), $this->payload([
            Honeypot::TIME_FIELD => Crypt::encryptString((string) now()->getTimestamp()),
        ]))->assertSessionHasErrors('name');

        $this->assertSame(0, ContactRequest::count());
    }

    public function test_a_missing_or_forged_stamp_is_rejected(): void
    {
        $this->post(route('site.contact.store'), $this->payload([Honeypot::TIME_FIELD => '']))
            ->assertSessionHasErrors('name');

        $this->post(route('site.contact.store'), $this->payload([Honeypot::TIME_FIELD => 'not-encrypted']))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, ContactRequest::count());
    }

    public function test_a_stale_form_is_rejected(): void
    {
        $this->post(route('site.contact.store'), $this->payload([
            Honeypot::TIME_FIELD => Crypt::encryptString((string) now()->subDay()->getTimestamp()),
        ]))->assertSessionHasErrors('name');

        $this->assertSame(0, ContactRequest::count());
    }

    public function test_the_list_property_form_is_protected_too(): void
    {
        $this->post(route('site.list-property.store'), [
            'name' => 'مالك',
            'phone_country_code' => '+965',
            'phone' => '55112233',
            'details' => 'شقة للعرض',
            Honeypot::FIELD => 'spam',
            Honeypot::TIME_FIELD => Crypt::encryptString((string) now()->subMinute()->getTimestamp()),
        ])->assertSessionHasErrors();

        $this->assertSame(0, ContactRequest::count());
    }

    public function test_the_public_forms_render_the_trap(): void
    {
        foreach ([route('site.contact'), route('site.list-property')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('name="'.Honeypot::FIELD.'"', false)
                ->assertSee('name="'.Honeypot::TIME_FIELD.'"', false);
        }
    }

    public function test_a_burst_of_submissions_from_one_address_is_throttled(): void
    {
        // البوت أرسل خمسة طلبات في ثانيتين — الحد يوقف ما بعد الخامس
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('site.contact.store'), $this->payload(['email' => "burst{$i}@example.com"]));
        }

        $this->post(route('site.contact.store'), $this->payload(['email' => 'burst-last@example.com']))
            ->assertStatus(429);
    }
}
