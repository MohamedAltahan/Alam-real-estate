<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نتائج المعاينة الجديدة: قيد الانتظار · قيد الدراسة · مهتم · غير مهتم · إلغاء الموعد.
 * «تم اختيار العقار» → «مهتم»، «لم يختر» → «غير مهتم»، و«إخلاء العقار» يُلغى (كان اختياراً ⇒ «مهتم»).
 * يُحذف تاريخ انتهاء العقد نهائياً — لا دورة عقود في النظام (التواريخ القديمة لا تُستعاد عند التراجع).
 */
return new class extends Migration
{
    private const FORWARD = ['chosen' => 'interested', 'rejected' => 'not_interested', 'vacated' => 'interested'];

    private const BACKWARD = ['interested' => 'chosen', 'not_interested' => 'rejected', 'studying' => 'pending', 'cancelled' => 'pending'];

    public function up(): void
    {
        foreach (self::FORWARD as $old => $new) {
            DB::table('client_viewings')->where('outcome', $old)->update(['outcome' => $new]);
        }

        Schema::table('client_viewings', function (Blueprint $table) {
            $table->dropIndex('cv_contract_idx');
        });

        Schema::table('client_viewings', function (Blueprint $table) {
            $table->dropColumn('contract_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('client_viewings', function (Blueprint $table) {
            $table->date('contract_ends_at')->nullable()->after('outcome_at');
        });

        Schema::table('client_viewings', function (Blueprint $table) {
            $table->index(['outcome', 'contract_ends_at'], 'cv_contract_idx');
        });

        foreach (self::BACKWARD as $new => $old) {
            DB::table('client_viewings')->where('outcome', $new)->update(['outcome' => $old]);
        }
    }
};
