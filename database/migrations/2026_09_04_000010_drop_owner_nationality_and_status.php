<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** حذف الجنسية وحالة العقد من الملّاك (لم يعودا مستخدمين) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_owners', function (Blueprint $table) {
            $table->dropColumn(['nationality', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('property_owners', function (Blueprint $table) {
            $table->string('nationality')->nullable();
            $table->string('status')->default('active');
        });
    }
};
