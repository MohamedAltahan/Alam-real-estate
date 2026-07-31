<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // عقارات العميل (سجل) — تظهر في شاشة العميل الواحدة
        Schema::create('client_property', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $t->string('relation')->nullable();             // interested, viewed, reserved
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_property');
    }
};
