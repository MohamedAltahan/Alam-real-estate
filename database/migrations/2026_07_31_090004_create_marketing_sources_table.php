<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_sources', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('type_id')->nullable()->constrained('marketing_source_types')->nullOnDelete();
            $t->decimal('cost', 14, 3)->default(0);         // التكلفة (KD, 3 decimals)
            $t->string('status')->default('active');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_sources');
    }
};
