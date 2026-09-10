<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حالة تسليم رسائل واتساب كما في التطبيق (أُرسلت · وصلت · قُرئت) مع أزمنة كل مرحلة،
 * ومعرّف الرسالة في واتساب نفسه، ووقت آخر استعلام من البوابة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('wa_message_id', 100)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('status_checked_at')->nullable();

            $table->index('status', 'wa_msg_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('wa_msg_status_idx');
            $table->dropColumn(['wa_message_id', 'sent_at', 'delivered_at', 'read_at', 'status_checked_at']);
        });
    }
};
