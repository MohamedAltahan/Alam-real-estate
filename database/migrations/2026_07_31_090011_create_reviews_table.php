<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تقييمات — للعقار وللوكيل (polymorphic)، تُضاف من الداشبورد
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->morphs('reviewable');                       // reviewable_type + reviewable_id
            $t->string('reviewer_name');                    // اسم العميل
            $t->unsignedTinyInteger('rating')->default(5);  // 1-5
            $t->text('comment')->nullable();
            $t->boolean('is_published')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
