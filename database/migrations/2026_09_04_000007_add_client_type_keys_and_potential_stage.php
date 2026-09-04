<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** مفاتيح أنواع العملاء حسب الاسم العربي (بعد إزالة التشكيل) */
    private const TYPE_KEYS = [
        'مستاجر' => 'tenant',
        'مشتر' => 'buyer',
        'بائع' => 'seller',
        'مؤجر' => 'landlord',
    ];

    /** المراحل بعد إضافة «عميل محتمل» — بنفس أسلوب normalizeClientStages() */
    private const STAGES = [
        'new' => ['ar' => 'طلب جديد', 'en' => 'New Request', 'color' => '#3B5BA5', 'final' => false, 'order' => 0],
        'potential' => ['ar' => 'عميل محتمل', 'en' => 'Potential Client', 'color' => '#7481E0', 'final' => false, 'order' => 1],
        'viewing' => ['ar' => 'معاينة العقار', 'en' => 'Property Viewing', 'color' => '#B5842A', 'final' => false, 'order' => 2],
        'closed_won' => ['ar' => 'ربح', 'en' => 'Won', 'color' => '#2E7D5B', 'final' => true, 'order' => 3],
        'closed_lost' => ['ar' => 'خسارة', 'en' => 'Lost', 'color' => '#C0392B', 'final' => true, 'order' => 4],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('client_types', 'key')) {
            Schema::table('client_types', function (Blueprint $table) {
                $table->string('key', 30)->nullable()->index('client_types_key_idx');
            });
        }

        $this->assignTypeKeys();
        $this->upsertStages();
    }

    public function down(): void
    {
        DB::table('client_stages')->where('key', 'potential')->update(['is_active' => false]);

        Schema::table('client_types', function (Blueprint $table) {
            $table->dropIndex('client_types_key_idx');
            $table->dropColumn('key');
        });
    }

    private function assignTypeKeys(): void
    {
        $now = now();
        $found = [];

        foreach (DB::table('client_types')->orderBy('id')->get(['id', 'name', 'key']) as $row) {
            $row = array_change_key_case((array) $row, CASE_LOWER);
            $names = json_decode((string) $row['name'], true) ?: [];
            $arabic = $this->normalize((string) ($names['ar'] ?? ''));

            foreach (self::TYPE_KEYS as $needle => $key) {
                if (isset($found[$key]) || ! str_contains($arabic, $needle)) {
                    continue;
                }

                DB::table('client_types')->where('id', $row['id'])->update(['key' => $key, 'updated_at' => $now]);
                $found[$key] = true;
                break;
            }
        }

        // نوع «مستأجر» أساسي — كل العملاء الجدد يُسجَّلون به
        if (! isset($found['tenant'])) {
            DB::table('client_types')->insert([
                'name' => json_encode(['ar' => 'مستأجر', 'en' => 'Tenant'], JSON_UNESCAPED_UNICODE),
                'key' => 'tenant',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function upsertStages(): void
    {
        $now = now();

        foreach (self::STAGES as $key => $stage) {
            $payload = [
                'name' => json_encode(['ar' => $stage['ar'], 'en' => $stage['en']], JSON_UNESCAPED_UNICODE),
                'color' => $stage['color'],
                'is_final' => $stage['final'],
                'is_active' => true,
                'sort_order' => $stage['order'],
                'updated_at' => $now,
            ];

            if (DB::table('client_stages')->where('key', $key)->exists()) {
                DB::table('client_stages')->where('key', $key)->update($payload);
            } else {
                DB::table('client_stages')->insert($payload + ['key' => $key, 'created_at' => $now]);
            }
        }
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $value) ?? $value;

        return trim(str_replace(['أ', 'إ', 'آ'], 'ا', $value));
    }
};
