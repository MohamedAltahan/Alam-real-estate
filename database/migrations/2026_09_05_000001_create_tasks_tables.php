<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لوحة المهام: المهام + التعليقات + سجل التعديلات.
 * رقم المهمة هو id نفسه (#12) — كل الفهارس مسمّاة صراحةً لحدّ Oracle (30 حرفاً).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('new');       // new · received · in_progress · done
            $table->string('priority', 10)->default('medium');  // low · medium · high · urgent
            $table->date('due_date')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'position'], 'tasks_status_pos_idx');
            $table->index('assignee_id', 'tasks_assignee_idx');
            $table->index('due_date', 'tasks_due_idx');
            $table->index('property_id', 'tasks_property_idx');
            $table->index('created_at', 'tasks_created_idx');
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('task_id', 'task_comments_task_idx');
        });

        Schema::create('task_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['task_id', 'created_at'], 'tal_task_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_audit_logs');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('tasks');
    }
};
