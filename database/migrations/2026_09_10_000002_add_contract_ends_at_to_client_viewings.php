<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_viewings', function (Blueprint $table) {
            // تاريخ انتهاء عقد الإيجار عند «تم اختيار العقار» — الفحص اليومي يحوّل النتيجة بعده إلى «إخلاء العقار»
            $table->date('contract_ends_at')->nullable()->after('outcome_at');
            // فهرس الفحص اليومي — الاسم صريح لأن أوراكل تولّد أسماء عشوائية للفهارس المركبة
            $table->index(['outcome', 'contract_ends_at'], 'cv_contract_idx');
        });
    }

    public function down(): void
    {
        Schema::table('client_viewings', function (Blueprint $table) {
            $table->dropIndex('cv_contract_idx');
            $table->dropColumn('contract_ends_at');
        });
    }
};
