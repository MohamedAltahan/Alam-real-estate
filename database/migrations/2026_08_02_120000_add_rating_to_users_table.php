<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * متوسط تقييم الوكيل وعدد تقييماته — مخزَّنان كما في جدول العقارات
 * حتى لا نحسب المتوسط في كل عرض، ويُحدَّثان بعد كل إضافة/حذف تقييم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->decimal('rating', 3, 2)->nullable()->after('response_time');
            $t->unsignedInteger('reviews_count')->default(0)->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['rating', 'reviews_count']);
        });
    }
};
