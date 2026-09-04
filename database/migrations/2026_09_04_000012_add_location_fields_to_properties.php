<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * حقول جديدة للعقار: المحافظة، رابط خرائط جوجل، اسم المبنى، نسبة عمولة المالك،
 * حارس العقار ورقمه، وعلامة «مفروشة».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->index('city_id', 'properties_city_idx');
            $table->string('map_url', 500)->nullable();
            $table->string('building_name', 150)->nullable();
            $table->decimal('owner_commission_rate', 5, 2)->nullable();
            $table->string('guard_name', 150)->nullable();
            $table->string('guard_phone', 40)->nullable();
            $table->boolean('is_furnished')->default(false);
        });

        // المحافظة من منطقة العقار
        foreach (DB::table('areas')->whereNotNull('city_id')->select(['id', 'city_id'])->get() as $row) {
            $row = array_change_key_case((array) $row, CASE_LOWER);

            DB::table('properties')
                ->where('area_id', $row['id'])
                ->whereNull('city_id')
                ->update(['city_id' => $row['city_id']]);
        }
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_city_idx');
            $table->dropConstrainedForeignId('city_id');
            $table->dropColumn(['map_url', 'building_name', 'owner_commission_rate', 'guard_name', 'guard_phone', 'is_furnished']);
        });
    }
};
