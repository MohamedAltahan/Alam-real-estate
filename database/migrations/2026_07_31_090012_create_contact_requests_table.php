<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // طلبات التواصل + أعرض عقارك (موحّد بعمود request_type_id)
        Schema::create('contact_requests', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->foreignId('request_type_id')->nullable()->constrained('request_types')->nullOnDelete();
            $t->string('subject')->nullable();
            $t->text('message')->nullable();
            $t->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $t->string('status')->default('pending');       // pending, contacted
            $t->boolean('is_read')->default(false);
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
