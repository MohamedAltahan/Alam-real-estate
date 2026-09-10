<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قراءة طلب التواصل لكل مستخدم على حدة — بديل العمود العام is_read الذي كان «قراءة الكل» يعلّمه للجميع
        Schema::create('contact_request_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_request_id')->constrained('contact_requests', indexName: 'cr_reads_request_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'cr_reads_user_fk')->cascadeOnDelete();
            $table->timestamp('read_at');
            // الأسماء صريحة لأن أوراكل تولّد أسماء عشوائية للفهارس المركبة
            $table->unique(['contact_request_id', 'user_id'], 'cr_reads_pair_uq');
            $table->index('user_id', 'cr_reads_user_idx');
        });

        // انتقال: ما كان «مقروءاً» عالمياً ولم يُتواصل معه يُعتبر مقروءاً لكل المستخدمين الحاليين
        $users = DB::table('users')->pluck('id');
        $read = DB::table('contact_requests')->where('is_read', 1)->where('status', '!=', 'contacted')->pluck('id');
        $now = now();

        foreach ($read as $requestId) {
            foreach ($users as $userId) {
                DB::table('contact_request_reads')->insert([
                    'contact_request_id' => $requestId,
                    'user_id' => $userId,
                    'read_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_request_reads');
    }
};
