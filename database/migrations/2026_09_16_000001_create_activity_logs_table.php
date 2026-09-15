<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سجل النشاط العام: كل إضافة/تعديل/حذف على الوحدات الأساسية (عقارات · ملاك · عملاء · معاينات · مهام · مستخدمون · أدوار)
        // مستقل عن client_audit_logs وtask_audit_logs اللذين يبقيان داخل شاشتيهما
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 20); // created | updated | deleted
            $table->string('module', 40); // مفتاح الوحدة للفلترة (ActivitySubjects::MODULES)
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id')->nullable();
            // اسم السجل وقت الحدث — يبقى مقروءاً بعد حذفه (كل الحذف في النظام نهائي)
            $table->string('subject_label', 200)->nullable();
            $table->json('changes')->nullable(); // {field: {old, new}}
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at', 'act_created_idx');
            $table->index(['user_id', 'created_at'], 'act_user_created_idx');
            $table->index(['module', 'created_at'], 'act_module_created_idx');
            $table->index(['subject_type', 'subject_id'], 'act_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
