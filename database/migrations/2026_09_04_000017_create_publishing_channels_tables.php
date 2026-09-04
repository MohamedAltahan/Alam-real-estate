<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قنوات النشر: المواقع الإلكترونية (kind=website) وقنوات السوشال ميديا (kind=social)،
 * وربط كل عقار بالقنوات التي نُشر عليها مع رابط الإعلان.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publishing_channels', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);            // website | social
            $table->string('name', 120);
            $table->string('url', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('kind', 'pub_channels_kind_idx');
        });

        Schema::create('property_channel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('publishing_channels')->cascadeOnDelete();
            $table->string('url', 500)->nullable(); // رابط الإعلان على القناة
            $table->timestamps();

            $table->unique(['property_id', 'channel_id'], 'prop_channel_uq');
            $table->index('channel_id', 'prop_channel_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_channel');
        Schema::dropIfExists('publishing_channels');
    }
};
