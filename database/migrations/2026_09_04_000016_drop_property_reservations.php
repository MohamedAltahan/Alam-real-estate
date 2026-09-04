<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** إزالة نظام حجز العقارات بالكامل */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('client_property')
            ->whereIn('relation', ['reserved', 'استفسار'])
            ->update(['relation' => 'interested']);

        Schema::dropIfExists('property_reservations');
    }

    public function down(): void
    {
        Schema::create('property_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('active_property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('active_property_id', 'prop_res_active_uq');
            $table->index(['property_id', 'status'], 'prop_res_property_status_idx');
            $table->index(['client_id', 'status'], 'prop_res_client_status_idx');
        });
    }
};
