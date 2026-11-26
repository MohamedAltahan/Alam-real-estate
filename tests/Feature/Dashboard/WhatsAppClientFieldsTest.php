<?php

namespace Tests\Feature\Dashboard;

use App\Models\Client;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Services\ViewingService;
use App\Support\WhatsAppTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** رسالة المعاينة من صفحة المعاينات تُعبّأ ببيانات العميل (الجنسية، عدد الأفراد، مكان العمل) */
class WhatsAppClientFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewings_page_loads_the_client_fields_used_by_the_message(): void
    {
        $client = Client::create(['name' => 'عميل', 'phone' => '99812226', 'nationality' => 'أردني', 'household_size' => 4, 'workplace' => 'شركة المعارض']);
        $property = Property::create(['reference_code' => '312', 'title' => ['ar' => 'سرداب', 'en' => 'Basement']]);
        ClientViewing::create(['client_id' => $client->id, 'property_id' => $property->id, 'scheduled_at' => now()]);

        $viewing = app(ViewingService::class)->paginate()->first();
        $message = WhatsAppTemplates::render('{الجنسية}|{عدد_الأفراد}|{مكان_العمل}', $viewing);

        $this->assertSame('أردني|4|شركة المعارض', $message);
    }
}
