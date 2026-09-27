<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ملاحظات «طلب مميز» — تُكتب بجوار التشيك بوكس في فورم العميل وتظهر في شاشة الطلبات المميزة */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('featured_notes', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('featured_notes');
        });
    }
};
