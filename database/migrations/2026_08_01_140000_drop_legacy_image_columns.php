<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * كل الصور انتقلت إلى spatie/laravel-medialibrary (جدول media)،
 * فلم تعد أعمدة المسارات النصية ولا جدول صور العقارات مستخدمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('property_images');

        Schema::table('properties', function (Blueprint $t) {
            $t->dropColumn('cover_image');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('avatar');
        });

        Schema::table('testimonials', function (Blueprint $t) {
            $t->dropColumn('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('properties', fn (Blueprint $t) => $t->string('cover_image')->nullable());
        Schema::table('users', fn (Blueprint $t) => $t->string('avatar')->nullable());
        Schema::table('testimonials', fn (Blueprint $t) => $t->string('avatar')->nullable());

        Schema::create('property_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $t->string('path');
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_cover')->default(false);
            $t->timestamps();
        });
    }
};
