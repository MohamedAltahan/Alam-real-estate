<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** تصنيف نوع الوحدة: سكني/تجاري — يحدّد أنواع الوحدات المتاحة حسب تصنيف العقار */
return new class extends Migration
{
    private const COMMERCIAL = ['مكتب', 'محل', 'office', 'shop'];

    public function up(): void
    {
        Schema::table('unit_types', function (Blueprint $table) {
            $table->string('category', 20)->default('residential');
            $table->index('category', 'unit_types_category_idx');
        });

        foreach (DB::table('unit_types')->select(['id', 'name'])->get() as $row) {
            $row = array_change_key_case((array) $row, CASE_LOWER);
            $names = json_decode((string) $row['name'], true) ?: [];
            $values = array_map(fn ($n) => mb_strtolower(trim((string) $n)), array_values($names));

            if (array_intersect($values, self::COMMERCIAL)) {
                DB::table('unit_types')->where('id', $row['id'])->update(['category' => 'commercial']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('unit_types', function (Blueprint $table) {
            $table->dropIndex('unit_types_category_idx');
            $table->dropColumn('category');
        });
    }
};
