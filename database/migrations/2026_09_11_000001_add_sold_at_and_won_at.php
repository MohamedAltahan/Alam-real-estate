<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // تاريخ البيع الفعلي — بديل updated_at في إيرادات الداشبورد: التعديل اللاحق للعقار لا يحرّكه
            $table->timestamp('sold_at')->nullable()->after('status_id');
            // الاسم صريح لأن أوراكل تولّد أسماء عشوائية للفهارس
            $table->index('sold_at', 'properties_sold_at_idx');
        });

        Schema::table('clients', function (Blueprint $table) {
            // تاريخ الربح الفعلي (الانتقال إلى مرحلة «ربح») — بديل updated_at في الصفقات المغلقة
            $table->timestamp('won_at')->nullable()->after('stage_id');
            $table->index('won_at', 'clients_won_at_idx');
        });

        // تعبئة أولية: ما هو «مباع»/«ربح» الآن يأخذ updated_at (أفضل تقدير متاح)
        $soldId = DB::table('property_statuses')->where('key', 'sold')->value('id');

        if ($soldId) {
            DB::table('properties')->where('status_id', $soldId)->whereNull('sold_at')->update(['sold_at' => DB::raw('updated_at')]);
        }

        $wonId = DB::table('client_stages')->where('key', 'closed_won')->value('id');

        if ($wonId) {
            DB::table('clients')->where('stage_id', $wonId)->whereNull('won_at')->update(['won_at' => DB::raw('updated_at')]);
        }
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_sold_at_idx');
            $table->dropColumn('sold_at');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_won_at_idx');
            $table->dropColumn('won_at');
        });
    }
};
