<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $t) {
            $t->id();
            $t->string('reference_code')->unique();         // ALM-###
            $t->json('title');                              // translatable
            $t->json('short_description')->nullable();      // translatable
            $t->json('description')->nullable();            // translatable (rich)
            $t->json('specifications')->nullable();         // translatable (rich)
            $t->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $t->foreignId('category_id')->nullable()->constrained('property_categories')->nullOnDelete();
            $t->foreignId('unit_type_id')->nullable()->constrained('unit_types')->nullOnDelete();
            $t->string('purpose')->default('sale');         // sale, rent
            $t->decimal('price', 14, 3)->default(0);
            $t->string('price_period')->nullable();         // monthly, yearly (للإيجار)
            $t->foreignId('status_id')->nullable()->constrained('property_statuses')->nullOnDelete();
            $t->foreignId('owner_id')->nullable()->constrained('property_owners')->nullOnDelete();
            $t->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedSmallInteger('bedrooms')->nullable();
            $t->unsignedSmallInteger('bathrooms')->nullable();
            $t->decimal('area_size', 12, 2)->nullable();    // م²
            $t->string('block')->nullable();                // قطعة
            $t->string('street')->nullable();               // شارع
            $t->string('building')->nullable();             // عمارة
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('video_url')->nullable();            // رابط يوتيوب
            $t->string('cover_image')->nullable();
            $t->boolean('is_featured')->default(false);     // مميز
            $t->decimal('rating', 3, 2)->nullable();        // متوسط محسوب (cache)
            $t->unsignedInteger('reviews_count')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
