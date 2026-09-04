<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'stage_id' => 'clients_stage_idx',
        'agent_id' => 'clients_agent_idx',
        'created_at' => 'clients_created_idx',
        'nationality' => 'clients_nat_idx',
        'social_status' => 'clients_social_idx',
        'preferred_contact' => 'clients_pref_idx',
        'phone' => 'clients_phone_idx',
    ];

    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // مفتاح الدولة منفصل عن الرقم المحلي؛ الافتراضي +965 يُضبط في التطبيق
            $table->string('phone_code', 8)->nullable();

            foreach (self::INDEXES as $column => $name) {
                $table->index($column, $name);
            }
        });

        // فهرس نصي للبحث في الملاحظات — متاح على MariaDB/MySQL فقط
        if ($this->supportsFullText()) {
            Schema::table('clients', fn (Blueprint $table) => $table->fullText('notes', 'clients_notes_ft'));
        }

        $this->splitExistingPhones();
    }

    public function down(): void
    {
        if ($this->supportsFullText()) {
            Schema::table('clients', fn (Blueprint $table) => $table->dropFullText('clients_notes_ft'));
        }

        Schema::table('clients', function (Blueprint $table) {
            foreach (self::INDEXES as $name) {
                $table->dropIndex($name);
            }

            $table->dropColumn('phone_code');
        });
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    /** تقسيم الأرقام المخزّنة (مثل +96555112233) إلى مفتاح + رقم محلي */
    private function splitExistingPhones(): void
    {
        DB::table('clients')
            ->orderBy('id')
            ->select(['id', 'phone'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $row = array_change_key_case((array) $row, CASE_LOWER);
                    ['code' => $code, 'national' => $national] = PhoneNumber::split($row['phone']);

                    DB::table('clients')->where('id', $row['id'])->update([
                        'phone_code' => $code,
                        'phone' => $national !== '' ? $national : $row['phone'],
                    ]);
                }
            });
    }
};
