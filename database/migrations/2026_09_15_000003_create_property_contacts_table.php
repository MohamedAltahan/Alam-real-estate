<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المسؤولون عن العقار: أرقام تواصل متعددة (رقم + صفته + اسمه) لكل عقار — تذهب إليهم رسائل المعاينة.
 * حارس العقار القديم (اسم + رقم) يتحوّل إلى صف بصفة «الحارس» ثم يُحذف عمودا الحارس.
 * حارس بلا رقم هاتف لا يُنقل (الرقم إلزامي).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('phone_code', 8)->nullable();
            $table->string('phone', 40);
            $table->string('role', 60)->nullable();
            $table->string('name', 150)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('property_id', 'prop_contacts_property_idx');
            $table->index('phone', 'prop_contacts_phone_idx');
        });

        $now = now();

        DB::table('properties')
            ->whereNotNull('guard_phone')
            ->select(['id', 'guard_name', 'guard_phone'])
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    $row = array_change_key_case((array) $row);
                    $phone = PhoneNumber::split($row['guard_phone'] ?? null);

                    if ($phone['national'] === '') {
                        continue;
                    }

                    DB::table('property_contacts')->insert([
                        'property_id' => (int) $row['id'],
                        'phone_code' => $phone['code'],
                        'phone' => $phone['national'],
                        'role' => 'الحارس',
                        'name' => filled($row['guard_name'] ?? null) ? trim((string) $row['guard_name']) : null,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['guard_name', 'guard_phone']);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('guard_name', 150)->nullable();
            $table->string('guard_phone', 40)->nullable();
        });

        DB::table('property_contacts')
            ->where('role', 'الحارس')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => (int) array_change_key_case((array) $row)['property_id'])
            ->each(function ($rows, int $propertyId) {
                $first = array_change_key_case((array) $rows->first());

                DB::table('properties')->where('id', $propertyId)->update([
                    'guard_name' => $first['name'] ?? null,
                    'guard_phone' => PhoneNumber::format($first['phone_code'] ?? null, $first['phone'] ?? null),
                ]);
            });

        Schema::dropIfExists('property_contacts');
    }
};
