<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * احتياج العقار: نوع العقار (سكني/تجاري) + المساحة + عدد الغرف.
 * نوع العقار يُعبَّأ من تصنيف نوع الوحدة المختار في الصفوف القديمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_property_needs', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('unit_type_id');
            $table->decimal('area_size', 10, 2)->nullable()->after('category');
            $table->unsignedSmallInteger('rooms')->nullable()->after('area_size');
            $table->index('category', 'cpn_category_idx');
            $table->index('rooms', 'cpn_rooms_idx');
        });

        $categories = DB::table('unit_types')->pluck('category', 'id');

        DB::table('client_property_needs')
            ->whereNotNull('unit_type_id')
            ->select(['id', 'unit_type_id'])
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($categories) {
                foreach ($rows as $row) {
                    $row = array_change_key_case((array) $row);
                    $category = $categories[(int) $row['unit_type_id']] ?? null;

                    if ($category) {
                        DB::table('client_property_needs')->where('id', (int) $row['id'])->update(['category' => $category]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('client_property_needs', function (Blueprint $table) {
            $table->dropIndex('cpn_category_idx');
            $table->dropIndex('cpn_rooms_idx');
        });

        Schema::table('client_property_needs', function (Blueprint $table) {
            $table->dropColumn(['category', 'area_size', 'rooms']);
        });
    }
};
