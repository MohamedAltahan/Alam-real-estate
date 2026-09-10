<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * شاشة «ميداني»: زيارات المندوبين لملاك عقارات جدد (المسؤولون، المتابعة، العقار وموقعه، الصور)
 * مع روابط التحويل إلى مالك حقيقي وعقار حقيقي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_owners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);                              // نسخة من أول مسؤول
            $table->string('phone_code', 8)->nullable();
            $table->string('phone', 40);
            $table->string('contact_method', 20)->default('visit');   // اتصال · زيارة · ترشيح
            $table->string('stage', 20)->default('new');              // مالك جديد · محتمل · اجتماع · ربح · خسارة
            $table->text('notes')->nullable();
            $table->string('property_number', 60)->nullable();        // رقم حر يكتبه المندوب
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_owner_id')->nullable()->constrained('property_owners')->nullOnDelete();
            $table->foreignId('converted_property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->timestamps();

            // أسماء صريحة: أوراكل تولّد أسماء عشوائية للفهارس
            $table->index('stage', 'field_owners_stage_idx');
            $table->index('contact_method', 'field_owners_method_idx');
            $table->index('city_id', 'field_owners_city_idx');
            $table->index('area_id', 'field_owners_area_idx');
            $table->index('created_by', 'field_owners_creator_idx');
            $table->index('created_at', 'field_owners_created_idx');
            $table->index('phone', 'field_owners_phone_idx');
        });

        Schema::create('field_owner_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_owner_id')->constrained('field_owners')->cascadeOnDelete();
            $table->string('name', 150)->nullable();   // الاسم
            $table->string('role', 60)->nullable();    // صفته (المالك، الوكيل، المدير…)
            $table->string('phone_code', 8)->nullable();
            $table->string('phone', 40);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('field_owner_id', 'field_contacts_owner_idx');
            $table->index('phone', 'field_contacts_phone_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_owner_contacts');
        Schema::dropIfExists('field_owners');
    }
};
