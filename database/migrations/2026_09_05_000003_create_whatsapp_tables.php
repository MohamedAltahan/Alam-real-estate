<?php

use App\Support\WhatsAppTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * واتساب: الرقم المربوط بالبوابة، قوالب الرسائل، سجل الرسائل المرسلة،
 * وعلامتا المعاينة (أُبلغ المالك · أُرسلت المتابعة للعميل).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_instances', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 40);      // معرّف الجلسة في البوابة (عدد صحيح هناك)
            $table->string('name', 120);
            $table->string('phone', 40)->nullable();
            $table->string('status', 20)->default('created'); // created · qr · connected · disconnected
            $table->dateTime('connected_at')->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->json('last_event')->nullable();
            $table->timestamps();

            $table->index('external_id', 'wa_inst_external_idx');
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40);
            $table->string('name', 120);
            $table->text('body');
            $table->timestamps();

            $table->unique('key', 'wa_templates_key_uq');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->foreignId('viewing_id')->nullable()->constrained('client_viewings')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('to_phone', 40);
            $table->string('to_label', 150)->nullable();
            $table->text('body');
            $table->string('status', 20);            // queued · failed
            $table->string('provider_id', 40)->nullable();
            $table->string('error', 500)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['viewing_id', 'kind'], 'wa_msg_viewing_idx');
            $table->index('created_at', 'wa_msg_created_idx');
        });

        Schema::table('client_viewings', function (Blueprint $table) {
            $table->timestamp('owner_notified_at')->nullable();
            $table->timestamp('client_followed_up_at')->nullable();
        });

        // القوالب الافتراضية (WhatsAppTemplateSeeder يتولاها أيضاً على قاعدة جديدة)
        if (DB::table('whatsapp_templates')->count() === 0) {
            $now = now();

            foreach (WhatsAppTemplates::DEFAULTS as $key => $template) {
                DB::table('whatsapp_templates')->insert([
                    'key' => $key,
                    'name' => $template['name'],
                    'body' => $template['body'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('client_viewings', function (Blueprint $table) {
            $table->dropColumn(['owner_notified_at', 'client_followed_up_at']);
        });

        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_instances');
    }
};
