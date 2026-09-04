<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // المدن (المحافظات) — المناطق تتبعها
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->json('name'); // {ar, en} قابل للترجمة
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('areas', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->index('city_id', 'areas_city_idx'); // أوراكل لا تفهرس المفاتيح الأجنبية تلقائياً
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropIndex('areas_city_idx');
            $table->dropConstrainedForeignId('city_id');
        });

        Schema::dropIfExists('cities');
    }
};
