<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $t) {
            // العميل الذي أُنشئ (أو رُبِط) من هذا الطلب — يمنع التحويل مرّتين
            $t->foreignId('converted_client_id')->nullable()->after('handled_by')
                ->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_requests', function (Blueprint $t) {
            $t->dropConstrainedForeignId('converted_client_id');
        });
    }
};
