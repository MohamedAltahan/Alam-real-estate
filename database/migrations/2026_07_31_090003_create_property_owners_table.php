<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_owners', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone');
            $t->string('email')->nullable();
            $t->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $t->string('nationality')->nullable();          // الجنسية
            $t->text('registered_address')->nullable();     // العنوان المسجّل
            $t->string('status')->default('active');        // active, inactive
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_owners');
    }
};
