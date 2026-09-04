<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تصنيفا العقار: سكني وتجاري فقط (بمفتاح ثابت). تصنيف «مفروش» القديم يتحول
 * إلى سكني + علامة «مفروشة» على العقار.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        'residential' => ['ar' => 'سكني', 'en' => 'Residential'],
        'commercial' => ['ar' => 'تجاري', 'en' => 'Commercial'],
    ];

    public function up(): void
    {
        Schema::table('property_categories', function (Blueprint $table) {
            $table->string('key', 20)->nullable();
            $table->unique('key', 'prop_categories_key_uq');
        });

        // قاعدة بيانات جديدة بلا تصنيفات: LookupSeeder هو من يُنشئها
        if (DB::table('property_categories')->count() === 0) {
            return;
        }

        $ids = [];
        $others = [];

        foreach (DB::table('property_categories')->select(['id', 'name'])->orderBy('id')->get() as $row) {
            $row = array_change_key_case((array) $row, CASE_LOWER);
            $names = json_decode((string) $row['name'], true) ?: [];
            $ar = trim((string) ($names['ar'] ?? ''));
            $en = mb_strtolower(trim((string) ($names['en'] ?? '')));

            $key = match (true) {
                $ar === 'سكني' || $en === 'residential' => 'residential',
                $ar === 'تجاري' || $en === 'commercial' => 'commercial',
                default => null,
            };

            if ($key && ! isset($ids[$key])) {
                $ids[$key] = $row['id'];
                DB::table('property_categories')->where('id', $row['id'])->update(['key' => $key]);
            } else {
                $others[] = $row['id'];
            }
        }

        $now = now();
        foreach (self::CATEGORIES as $key => $name) {
            if (! isset($ids[$key])) {
                $ids[$key] = DB::table('property_categories')->insertGetId([
                    'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                    'key' => $key,
                    'sort_order' => $key === 'residential' ? 0 : 1,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // «مفروش» وأي تصنيف آخر → سكني + مفروشة
        if ($others) {
            DB::table('properties')->whereIn('category_id', $others)->update([
                'category_id' => $ids['residential'],
                'is_furnished' => 1,
            ]);
            DB::table('property_categories')->whereIn('id', $others)->delete();
        }

        DB::table('property_categories')->where('id', $ids['residential'])->update(['sort_order' => 0]);
        DB::table('property_categories')->where('id', $ids['commercial'])->update(['sort_order' => 1]);
    }

    public function down(): void
    {
        Schema::table('property_categories', function (Blueprint $table) {
            $table->dropUnique('prop_categories_key_uq');
            $table->dropColumn('key');
        });
    }
};
