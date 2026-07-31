<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل التواصل مع العميل — كل مكالمة/مقابلة + تغيير الحالة
        Schema::create('client_interactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // المنفّذ
            $t->string('type')->default('call');            // call, meeting, whatsapp, email
            $t->text('notes')->nullable();                  // اللي حصل في التواصل
            $t->foreignId('stage_id')->nullable()->constrained('client_stages')->nullOnDelete(); // الحالة بعده
            $t->timestamp('occurred_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_interactions');
    }
};
