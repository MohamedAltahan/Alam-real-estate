<?php

namespace Database\Seeders;

use App\Models\WhatsappTemplate;
use App\Support\WhatsAppTemplates;
use Illuminate\Database\Seeder;

/** القوالب الافتراضية لرسائل واتساب — لا تُستبدل إن كانت موجودة */
class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (WhatsAppTemplates::DEFAULTS as $key => $template) {
            WhatsappTemplate::firstOrCreate(['key' => $key], $template);
        }
    }
}
