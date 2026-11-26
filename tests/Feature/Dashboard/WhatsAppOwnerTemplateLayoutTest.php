<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\Client;
use App\Models\Property;
use App\Models\User;
use App\Support\WhatsAppTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** قالب «تفاصيل المعاينة لمسؤول العقار» بنفس شكل وترتيب ومسافات الرسالة المعتمدة من العميل */
class WhatsAppOwnerTemplateLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_message_matches_the_approved_layout_line_by_line(): void
    {
        $agent = User::factory()->create(['name' => 'Eslam Mahfouz', 'phone' => '+96563635018', 'is_agent' => true]);
        $area = Area::create(['name' => ['ar' => 'المهبولة', 'en' => 'Mahboula']]);
        $property = Property::create([
            'reference_code' => '114', 'title' => ['ar' => 'شقة مفروشة للإيجار في المهبولة', 'en' => 'Flat'],
            'area_id' => $area->id, 'building_name' => 'خالد تاور',
        ]);
        $client = Client::create([
            'name' => 'Gines navarro', 'phone_code' => '+965', 'phone' => '66305812', 'agent_id' => $agent->id,
            'nationality' => 'هندي', 'household_size' => 5, 'workplace' => 'KFH',
        ]);
        $viewing = $client->viewings()->create(['property_id' => $property->id, 'scheduled_at' => '2026-11-25 17:00:00']);

        $body = WhatsAppTemplates::render(WhatsAppTemplates::body(WhatsAppTemplates::KIND_OWNER), $viewing->load('client.agent', 'property.area'), 'مأمون');

        $this->assertSame([
            'عميلنا العزيز مأمون،',
            'نود إحاطتكم علماً بوجود زيارة مرتقبة لعقاركم من أحد عملائنا.',
            '',
            '',
            '•اسم العميل: Gines navarro',
            "•رقم الهاتف: \u{200E}+965663058xx",
            '•الجنسية: هندي',
            '•عدد الأفراد: 5',
            '•مكان العمل: KFH',
            '•عقار: المهبولة — خالد تاور',
            route('site.property', $property),
            '•تاريخ الزيارة: 2026-11-25',
            '•وقت الزيارة: 05:00 PM',
            '•اسم موظف علم: Eslam Mahfouz',
            "•رقمه: \u{200E}+96563635018",
            '',
            'علم العقارية ثقتكم مسؤوليتنا.',
        ], explode("\n", $body));
    }

    public function test_new_placeholders_are_listed_for_the_template_editor(): void
    {
        foreach (['{الجنسية}', '{عدد_الأفراد}', '{مكان_العمل}', '{تاريخ_المعاينة}', '{وقت_المعاينة}'] as $placeholder) {
            $this->assertArrayHasKey($placeholder, WhatsAppTemplates::PLACEHOLDERS);
        }
    }
}
