<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone');
            $t->string('email')->nullable();
            $t->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $t->foreignId('type_id')->nullable()->constrained('client_types')->nullOnDelete();
            $t->foreignId('stage_id')->nullable()->constrained('client_stages')->nullOnDelete();
            $t->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('source_id')->nullable()->constrained('marketing_sources')->nullOnDelete();
            $t->unsignedTinyInteger('rating')->nullable();  // 0-100 % (ملوّن)
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
