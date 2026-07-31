<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone')->nullable();
            $t->string('civil_id')->nullable();            // الرقم المدني
            $t->string('avatar')->nullable();
            $t->string('job_title')->nullable();           // المسمى الوظيفي
            $t->string('status')->default('active');       // active, suspended
            $t->boolean('is_agent')->default(false);       // يظهر بالموقع كوكيل
            $t->json('bio')->nullable();                   // translatable نبذة الوكيل
            $t->json('languages')->nullable();             // ["ar","en"]
            $t->string('response_time')->nullable();       // سرعة الرد
            $t->json('preferences')->nullable();           // لغة/توقيت/عملة/إشعارات
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn([
                'phone', 'civil_id', 'avatar', 'job_title', 'status',
                'is_agent', 'bio', 'languages', 'response_time', 'preferences',
            ]);
        });
    }
};
