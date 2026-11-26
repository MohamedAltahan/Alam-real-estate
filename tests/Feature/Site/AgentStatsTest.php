<?php

namespace Tests\Feature\Site;

use App\Models\Client;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** بروفايل مندوب المبيعات وكارته في صفحة العقار: عدد العقارات فقط — بلا «عميل» ولا «سنوات» */
class AgentStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_profile_and_property_card_show_only_the_listings_count(): void
    {
        $agent = User::factory()->create(['is_agent' => true, 'status' => 'active', 'phone' => '+96550000001', 'created_at' => now()->subYears(3)]);
        foreach (['901', '902', '903'] as $ref) {
            Property::create(['reference_code' => $ref, 'title' => ['ar' => 'عقار '.$ref, 'en' => 'P '.$ref], 'agent_id' => $agent->id]);
        }
        Client::create(['name' => 'عميل', 'phone_code' => '+965', 'phone' => '55000077', 'agent_id' => $agent->id]);

        $profile = $this->get(route('site.agent', $agent))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~text-3xl font-bold text-accent-400 tabular-nums">3</p>\s*<p class="text-xs text-white/50">عقار</p>~u', $profile);
        $this->assertStringNotContainsString('>سنوات</p>', $profile);
        $this->assertStringNotContainsString('>عميل</p>', $profile);

        $page = $this->get(route('site.property', Property::first()))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~text-lg font-bold text-ink tabular-nums">3</p>\s*<p class="text-\[11px\] text-gray-400 mt-0.5">عقار</p>~u', $page);
        $this->assertStringNotContainsString('عميل سعيد', $page);
        $this->assertStringNotContainsString('سنوات خبرة', $page);
    }
}
