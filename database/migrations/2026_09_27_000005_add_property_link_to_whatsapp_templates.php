<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * متغيّر {رابط_العقار} (صفحة العقار على الموقع) في قوالب واتساب المحفوظة:
 * سطر «رابط العقار: …» يُضاف تحت أول سطر فيه اسم العقار (أو رقمه) — حتى القوالب المعدّلة يدوياً.
 * القالب الذي يحوي المتغيّر بالفعل لا يُمسّ.
 */
return new class extends Migration
{
    private const LINE = 'رابط العقار: {رابط_العقار}';

    private const TOKEN = '{رابط_العقار}';

    /** السطر الذي يأتي الرابط تحته: أول سطر فيه أحد هذه المتغيّرات بالترتيب */
    private const ANCHORS = ['{اسم_العقار}', '{رقم_العقار}', '{عنوان_العقار}'];

    public function up(): void
    {
        $this->each(function (string $body) {
            if (str_contains($body, self::TOKEN)) {
                return null;
            }

            $lines = explode("\n", $body);

            foreach (self::ANCHORS as $anchor) {
                foreach ($lines as $index => $line) {
                    if (str_contains($line, $anchor)) {
                        array_splice($lines, $index + 1, 0, [self::LINE]);

                        return implode("\n", $lines);
                    }
                }
            }

            return null;
        });
    }

    public function down(): void
    {
        $this->each(function (string $body) {
            $lines = explode("\n", $body);
            $kept = array_values(array_filter($lines, fn (string $line) => trim($line) !== self::LINE));

            return count($kept) === count($lines) ? null : implode("\n", $kept);
        });
    }

    /** @param  callable(string): ?string  $transform  نص جديد أو null لإبقاء القالب كما هو */
    private function each(callable $transform): void
    {
        foreach (DB::table('whatsapp_templates')->get(['id', 'body']) as $row) {
            $row = array_change_key_case((array) $row);
            $body = str_replace("\r\n", "\n", (string) ($row['body'] ?? ''));
            $new = $transform($body);

            if ($new !== null && $new !== $body) {
                DB::table('whatsapp_templates')->where('id', $row['id'])->update(['body' => $new, 'updated_at' => now()]);
            }
        }
    }
};
