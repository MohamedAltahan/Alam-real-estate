<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل التعديلات على العميل وكل ما يخصّه (احتياجات، معاينات، عقارات، تواصل)
        Schema::create('client_audit_logs', function (Blueprint $table) {
            $table->id();
            // nullable حتى يبقى السجل بعد حذف العميل
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('changes')->nullable(); // {field: {old, new}} أو لقطة من السجل
            $table->timestamp('created_at')->nullable();

            $table->index(['client_id', 'created_at'], 'cal_client_created_idx');
            $table->index(['subject_type', 'subject_id'], 'cal_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_audit_logs');
    }
};
