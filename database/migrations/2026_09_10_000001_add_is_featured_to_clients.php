<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // «طلب مميز» — يُعلَّم من فورم العميل ويظهر في تبويب الطلبات المميزة بشاشة طلبات التواصل
            $table->boolean('is_featured')->default(false);
            $table->index('is_featured', 'clients_featured_idx');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_featured_idx');
            $table->dropColumn('is_featured');
        });
    }
};
