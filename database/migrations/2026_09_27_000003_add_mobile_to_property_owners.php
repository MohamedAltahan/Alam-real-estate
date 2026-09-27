<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رقم موبايل المالك نفسه — للعرض في صفحة المالك فقط (لا رسائل واتساب ولا قوائم ولا بحث).
 * منفصل عن phone الذي يُنسخ تلقائياً من أول مسؤول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_owners', function (Blueprint $table) {
            $table->string('mobile_code', 8)->nullable();
            $table->string('mobile', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('property_owners', function (Blueprint $table) {
            $table->dropColumn(['mobile_code', 'mobile']);
        });
    }
};
