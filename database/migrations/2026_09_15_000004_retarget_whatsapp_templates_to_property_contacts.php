<?php

use App\Support\WhatsAppTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * رسائل المعاينة صارت تذهب لمسؤولي العقار بدل المالك والعميل.
 * القالب المخزّن يُستبدل فقط إن كان ما زال مطابقاً للافتراضي القديم حرفياً (القوالب المعدّلة يدوياً لا تُمسّ)،
 * أما الاسم فيُحدَّث دائماً.
 */
return new class extends Migration
{
    private const OLD = [
        'viewing_owner' => [
            'name' => 'تفاصيل المعاينة للمالك',
            'body' => "السلام عليكم {اسم_المستلم}،\nنود إبلاغكم بموعد معاينة للعقار {رقم_العقار} — {اسم_العقار}.\nالعميل: {اسم_العميل}\nالهاتف: {هاتف_العميل}\nالموعد: {موعد_المعاينة}\nمندوب المبيعات: {مندوب_المبيعات} — {هاتف_المندوب}\n\nعلم العقارية",
        ],
        'viewing_client' => [
            'name' => 'متابعة المعاينة للعميل',
            'body' => "السلام عليكم {اسم_العميل}،\nشكراً لمعاينتكم العقار {رقم_العقار} — {اسم_العقار} بتاريخ {موعد_المعاينة}.\nنتيجة المعاينة: {نتيجة_المعاينة}\nيسعدنا تواصلكم مع مندوب المبيعات {مندوب_المبيعات} — {هاتف_المندوب} لأي استفسار.\n\nعلم العقارية",
        ],
    ];

    public function up(): void
    {
        $this->swap(self::OLD, WhatsAppTemplates::DEFAULTS);
    }

    public function down(): void
    {
        $this->swap(WhatsAppTemplates::DEFAULTS, self::OLD);
    }

    /** @param  array<string, array{name:string, body:string}>  $from */
    private function swap(array $from, array $to): void
    {
        foreach ($to as $key => $template) {
            $row = DB::table('whatsapp_templates')->where('key', $key)->first();

            if (! $row) {
                continue;
            }

            $row = array_change_key_case((array) $row);
            $stored = trim(str_replace("\r\n", "\n", (string) ($row['body'] ?? '')));
            $update = ['name' => $template['name'], 'updated_at' => now()];

            if ($stored === trim($from[$key]['body'] ?? '')) {
                $update['body'] = $template['body'];
            }

            DB::table('whatsapp_templates')->where('key', $key)->update($update);
        }
    }
};
