<?php

namespace Database\Seeders;

use App\Models\PublishingChannel;
use Illuminate\Database\Seeder;

/** قنوات نشر تجريبية: موقع OLX + فيسبوك وإنستجرام */
class PublishingChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['kind' => PublishingChannel::KIND_WEBSITE, 'name' => 'OLX', 'url' => 'https://www.olx.com.kw'],
            ['kind' => PublishingChannel::KIND_SOCIAL, 'name' => 'Facebook', 'url' => 'https://www.facebook.com'],
            ['kind' => PublishingChannel::KIND_SOCIAL, 'name' => 'Instagram', 'url' => 'https://www.instagram.com'],
        ];

        foreach ($channels as $i => $channel) {
            PublishingChannel::updateOrCreate(
                ['kind' => $channel['kind'], 'name' => $channel['name']],
                ['url' => $channel['url'], 'sort_order' => $i, 'is_active' => true],
            );
        }
    }
}
