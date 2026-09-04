<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // احتياجات العقار: العميل قد يبحث عن أكثر من نوع/مدينة/منطقة
        Schema::create('client_property_needs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('unit_type_id')->nullable()->constrained('unit_types')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('client_id', 'cpn_client_idx');
            $table->index('city_id', 'cpn_city_idx');
            $table->index('area_id', 'cpn_area_idx');
            $table->index('unit_type_id', 'cpn_unit_idx');
        });

        $this->migrateLegacyNeeds();
    }

    public function down(): void
    {
        Schema::dropIfExists('client_property_needs');
    }

    /** نقل المنطقة ونوع الوحدة القديمين من جدول العملاء إلى أول سطر احتياج */
    private function migrateLegacyNeeds(): void
    {
        if (! Schema::hasColumn('clients', 'area_id') || ! Schema::hasColumn('clients', 'desired_unit_type_id')) {
            return;
        }

        $now = now();

        DB::table('clients')
            ->where(fn ($q) => $q->whereNotNull('area_id')->orWhereNotNull('desired_unit_type_id'))
            ->orderBy('id')
            ->select(['id', 'area_id', 'desired_unit_type_id'])
            ->chunk(200, function ($rows) use ($now) {
                $inserts = [];

                foreach ($rows as $row) {
                    // أوراكل تعيد أسماء الأعمدة بحروف كبيرة
                    $row = array_change_key_case((array) $row, CASE_LOWER);

                    $inserts[] = [
                        'client_id' => $row['id'],
                        'city_id' => null, // يُستكمل من areas.city_id بواسطة KuwaitAreasSeeder
                        'area_id' => $row['area_id'],
                        'unit_type_id' => $row['desired_unit_type_id'],
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($inserts) {
                    DB::table('client_property_needs')->insert($inserts);
                }
            });
    }
};
