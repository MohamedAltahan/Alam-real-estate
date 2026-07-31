<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_amenity', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $t->foreignId('amenity_id')->constrained('amenities')->cascadeOnDelete();
            $t->unique(['property_id', 'amenity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_amenity');
    }
};
