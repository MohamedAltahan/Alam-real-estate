<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * حالات العقار أربع فقط: قيد الإنتظار · قيد التدقيق · متاح · مباع.
 * حالة «محجوز» تُحذف وتتحول عقاراتها إلى «متاح».
 */
return new class extends Migration
{
    private const STATUSES = [
        ['key' => 'pending', 'color' => '#6B7280', 'ar' => 'قيد الإنتظار', 'en' => 'Pending'],
        ['key' => 'review', 'color' => '#B5842A', 'ar' => 'قيد التدقيق', 'en' => 'Under Review'],
        ['key' => 'available', 'color' => '#2E7D5B', 'ar' => 'متاح', 'en' => 'Available'],
        ['key' => 'sold', 'color' => '#C0392B', 'ar' => 'مباع', 'en' => 'Sold'],
    ];

    public function up(): void
    {
        // قاعدة بيانات جديدة بلا حالات: LookupSeeder هو من يُنشئها
        if (DB::table('property_statuses')->count() === 0) {
            return;
        }

        $now = now();
        $ids = [];

        foreach (self::STATUSES as $i => $status) {
            $values = [
                'name' => json_encode(['ar' => $status['ar'], 'en' => $status['en']], JSON_UNESCAPED_UNICODE),
                'color' => $status['color'],
                'sort_order' => $i,
                'is_active' => 1,
                'updated_at' => $now,
            ];

            $existing = DB::table('property_statuses')->where('key', $status['key'])->first();

            if ($existing) {
                $existing = array_change_key_case((array) $existing, CASE_LOWER);
                DB::table('property_statuses')->where('id', $existing['id'])->update($values);
                $ids[$status['key']] = $existing['id'];
            } else {
                $ids[$status['key']] = DB::table('property_statuses')->insertGetId(
                    $values + ['key' => $status['key'], 'created_at' => $now]
                );
            }
        }

        $reserved = DB::table('property_statuses')->where('key', 'reserved')->first();

        if ($reserved) {
            $reserved = array_change_key_case((array) $reserved, CASE_LOWER);
            DB::table('properties')->where('status_id', $reserved['id'])->update(['status_id' => $ids['available']]);
            DB::table('property_statuses')->where('id', $reserved['id'])->delete();
        }
    }

    public function down(): void
    {
        // الحالات الجديدة تبقى؛ لا حاجة لإعادة «محجوز»
    }
};
