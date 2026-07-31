<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // المناطق (Kuwait areas)
        Schema::create('areas', function (Blueprint $t) {
            $t->id();
            $t->json('name');                              // translatable AR/EN
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // تصنيف العقار: سكني / تجاري / مفروش
        Schema::create('property_categories', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // نوع الوحدة: منزل / شقة / فيلا / أرض
        Schema::create('unit_types', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // حالة العقار: متاح / محجوز / مباع
        Schema::create('property_statuses', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->string('key')->unique();                   // available, reserved, sold
            $t->string('color', 30)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // مراحل العميل (Pipeline)
        Schema::create('client_stages', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->string('key')->nullable();
            $t->string('color', 30)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_final')->default(false);       // reports count clients here
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // نوع العميل: مشترٍ / بائع / مستأجر
        Schema::create('client_types', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // المرافق والخدمات (icon + name)
        Schema::create('amenities', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->string('icon')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // نوع الطلب: تواصل عام / استفسار عقار / أعرض عقارك
        Schema::create('request_types', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->string('key')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // نوع مصدر التسويق: سوشيال ميديا / مباشر / إحالة
        Schema::create('marketing_source_types', function (Blueprint $t) {
            $t->id();
            $t->json('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_source_types');
        Schema::dropIfExists('request_types');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('client_types');
        Schema::dropIfExists('client_stages');
        Schema::dropIfExists('property_statuses');
        Schema::dropIfExists('unit_types');
        Schema::dropIfExists('property_categories');
        Schema::dropIfExists('areas');
    }
};
