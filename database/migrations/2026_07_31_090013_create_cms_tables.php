<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // صفحات الموقع + SEO مترجم
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();                   // home, about, contact...
            $t->string('name');
            $t->json('seo_title')->nullable();              // translatable
            $t->json('seo_description')->nullable();        // translatable
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // سكاشن كل صفحة — محتوى JSON مرن ومترجم (يشمل صور الهيرو المتحركة)
        Schema::create('page_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $t->string('key');                              // hero, stats, areas, cta, footer...
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_visible')->default(true);
            $t->json('content')->nullable();                // translatable flexible content
            $t->timestamps();
        });

        // الأسئلة الشائعة
        Schema::create('faqs', function (Blueprint $t) {
            $t->id();
            $t->json('question');                           // translatable
            $t->json('answer');                             // translatable
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // آراء العملاء بالرئيسية (محتوى CMS منفصل عن تقييمات الوكلاء)
        Schema::create('testimonials', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('title')->nullable();                  // translatable (الصفة)
            $t->json('content');                            // translatable
            $t->unsignedTinyInteger('rating')->nullable();
            $t->string('avatar')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // إعدادات عامة: فوتر / تواصل / سوشيال / عام
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('group')->default('general');        // footer, contact, social...
            $t->string('key');
            $t->json('value')->nullable();                  // translatable where needed
            $t->timestamps();
            $t->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('pages');
    }
};
