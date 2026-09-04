<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // «أوقات الزيارة» القديمة نص حر لا يمكن تحويله لمواعيد — يُحفظ داخل الملاحظات
        if (Schema::hasColumn('clients', 'visit_times')) {
            DB::table('clients')
                ->whereNotNull('visit_times')
                ->orderBy('id')
                ->select(['id', 'notes', 'visit_times'])
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $row = array_change_key_case((array) $row, CASE_LOWER);
                        $visit = trim((string) $row['visit_times']);

                        if ($visit === '') {
                            continue;
                        }

                        $notes = trim((string) $row['notes']);
                        $notes = ($notes !== '' ? $notes."\n" : '').'أوقات الزيارة: '.$visit;

                        DB::table('clients')->where('id', $row['id'])->update(['notes' => $notes]);
                    }
                });
        }

        Schema::table('clients', function (Blueprint $table) {
            // المنطقة ونوع الوحدة انتقلا إلى client_property_needs، وحضوري إلى client_viewings
            if (Schema::hasColumn('clients', 'area_id')) {
                $table->dropConstrainedForeignId('area_id');
            }
            if (Schema::hasColumn('clients', 'desired_unit_type_id')) {
                $table->dropConstrainedForeignId('desired_unit_type_id');
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['property_address', 'visit_times', 'in_person'],
                fn (string $column) => Schema::hasColumn('clients', $column),
            ));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('desired_unit_type_id')->nullable()->constrained('unit_types')->nullOnDelete();
            $table->boolean('in_person')->nullable();
            $table->string('visit_times', 255)->nullable();
            $table->text('property_address')->nullable();
        });
    }
};
