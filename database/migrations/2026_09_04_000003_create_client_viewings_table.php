<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // المعاينات: عقار + موعد + حضوري + النتيجة (مصدر تقرير معدل التحول)
        Schema::create('client_viewings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->boolean('in_person')->default(true);
            $table->string('outcome', 20)->default('pending'); // pending | chosen | rejected
            $table->timestamp('outcome_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reminded_at')->nullable(); // يمنع تكرار إشعار التذكير
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('client_id', 'cv_client_idx');
            $table->index('property_id', 'cv_property_idx');
            $table->index('scheduled_at', 'cv_sched_idx');
            // فهرس المعاينات المستحقة للتذكير — الاسم صريح لأن أوراكل تولّد أسماء عشوائية للفهارس المركبة
            $table->index(['outcome', 'reminded_at', 'scheduled_at'], 'cv_due_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_viewings');
    }
};
