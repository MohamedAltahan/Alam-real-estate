<?php

namespace Tests\Feature\Site;

use App\Models\ContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneCountryCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_contact_forms_show_ten_country_codes_and_no_phone_placeholder(): void
    {
        foreach ([route('site.contact'), route('site.list-property')] as $url) {
            $response = $this->get($url)->assertOk();
            $html = $response->getContent();

            preg_match('/<select[^>]+name="phone_country_code".*?<\/select>/s', $html, $select);

            $this->assertNotEmpty($select);
            $this->assertSame(10, substr_count($select[0], '<option value="+'));
            $this->assertStringContainsString('<option value="+965" selected>', $select[0]);
            $this->assertStringContainsString('<option value="+966"', $select[0]);
            $this->assertStringNotContainsString('placeholder="05XXXXXXXX"', $html);
        }
    }

    public function test_contact_request_saves_the_selected_country_code_with_the_phone(): void
    {
        $this->post(route('site.contact.store'), [
            'name' => 'محمد أحمد',
            'phone_country_code' => '+966',
            'phone' => '050 123 4567',
            'message' => 'أرغب في مزيد من المعلومات.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+966 501234567', ContactRequest::sole()->phone);
    }

    public function test_list_property_request_saves_the_selected_country_code_with_the_phone(): void
    {
        $this->post(route('site.list-property.store'), [
            'name' => 'مالك العقار',
            'phone_country_code' => '+20',
            'phone' => '010 1234 5678',
            'details' => 'شقة مكونة من ثلاث غرف.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+20 1012345678', ContactRequest::sole()->phone);
    }
}
