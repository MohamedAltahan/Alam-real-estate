<?php

namespace Tests\Feature\Site;

use App\Models\Property;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** الزرّان العائمان: الأرقام الرئيسية في كل الصفحات، ورقم مندوب العقار في صفحة العقار */
class FloatingContactButtonsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('contact', 'phone', '+965 1111 1111');
        Setting::set('contact', 'whatsapp', '+965 2222 2222');
    }

    public function test_home_page_uses_the_main_contact_numbers(): void
    {
        $this->get(route('site.home'))
            ->assertOk()
            ->assertSee('https://wa.me/96522222222', false)
            ->assertSee('tel:+96511111111" class="grid place-items-center w-12 h-12', false);
    }

    public function test_property_page_uses_the_sales_agent_numbers(): void
    {
        $agent = User::factory()->create(['phone' => '+965 5555 6666']);
        $property = Property::create([
            'reference_code' => '315',
            'title' => ['ar' => 'شقة السالمية', 'en' => 'Salmiya flat'],
            'agent_id' => $agent->id,
        ]);

        $html = $this->get(route('site.property', $property))->assertOk()->getContent();

        $this->assertStringContainsString('href="tel:+96555556666" class="grid place-items-center w-12 h-12', $html);
        $this->assertStringContainsString('href="https://wa.me/96555556666?text=', $html);
        // رقم الشركة يبقى في الفوتر فقط، لا في الزر العائم
        $this->assertStringNotContainsString('https://wa.me/96522222222?text=', $html);
        $this->assertStringNotContainsString('tel:+96511111111" class="grid place-items-center w-12 h-12', $html);
    }

    public function test_property_without_an_agent_phone_falls_back_to_the_main_numbers(): void
    {
        $property = Property::create([
            'reference_code' => '316',
            'title' => ['ar' => 'شقة بلا مندوب', 'en' => 'No agent flat'],
        ]);

        $this->get(route('site.property', $property))
            ->assertOk()
            ->assertSee('https://wa.me/96522222222?text=', false)
            ->assertSee('tel:+96511111111" class="grid place-items-center w-12 h-12', false);
    }
}
