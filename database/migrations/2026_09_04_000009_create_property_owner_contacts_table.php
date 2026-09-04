<?php

use App\Models\PropertyOwner;
use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * أرقام تواصل متعددة للمالك (رقم + صفته + اسمه) وملفات متعددة بدل عقد واحد،
 * مع مفتاح الدولة وحقل الملاحظات وفهارس الفلاتر على جدول الملّاك.
 */
return new class extends Migration
{
    private const INDEXES = [
        'area_id' => 'owners_area_idx',
        'created_at' => 'owners_created_idx',
        'phone' => 'owners_phone_idx',
    ];

    public function up(): void
    {
        Schema::create('property_owner_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('property_owners')->cascadeOnDelete();
            $table->string('phone_code', 8)->nullable();
            $table->string('phone', 40);
            $table->string('role', 60)->nullable();   // صفته (المالك، الوكيل، المدير…)
            $table->string('name', 150)->nullable();  // إسمه
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('owner_id', 'owner_contacts_owner_idx');
            $table->index('phone', 'owner_contacts_phone_idx');
        });

        Schema::table('property_owners', function (Blueprint $table) {
            $table->string('phone_code', 8)->nullable();
            $table->text('notes')->nullable();

            foreach (self::INDEXES as $column => $name) {
                $table->index($column, $name);
            }
        });

        if ($this->supportsFullText()) {
            Schema::table('property_owners', fn (Blueprint $table) => $table->fullText('notes', 'owners_notes_ft'));
        }

        $this->migrateExistingOwners();
    }

    public function down(): void
    {
        if ($this->supportsFullText()) {
            Schema::table('property_owners', fn (Blueprint $table) => $table->dropFullText('owners_notes_ft'));
        }

        Schema::table('property_owners', function (Blueprint $table) {
            foreach (self::INDEXES as $name) {
                $table->dropIndex($name);
            }

            $table->dropColumn(['phone_code', 'notes']);
        });

        Schema::dropIfExists('property_owner_contacts');
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    /** كل مالك: تقسيم رقمه إلى مفتاح + رقم محلي، وسطر تواصل واحد، ونقل ملف العقد إلى مجموعة «files» */
    private function migrateExistingOwners(): void
    {
        $now = now();

        DB::table('property_owners')
            ->orderBy('id')
            ->select(['id', 'phone'])
            ->chunkById(200, function ($rows) use ($now) {
                foreach ($rows as $row) {
                    // أوراكل تعيد أسماء الأعمدة بحروف كبيرة
                    $row = array_change_key_case((array) $row, CASE_LOWER);
                    ['code' => $code, 'national' => $national] = PhoneNumber::split($row['phone']);
                    $phone = $national !== '' ? $national : (string) $row['phone'];

                    DB::table('property_owners')->where('id', $row['id'])->update([
                        'phone_code' => $code,
                        'phone' => $phone,
                    ]);

                    if ($phone !== '') {
                        DB::table('property_owner_contacts')->insert([
                            'owner_id' => $row['id'],
                            'phone_code' => $code,
                            'phone' => $phone,
                            'sort_order' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });

        DB::table('media')
            ->where('model_type', PropertyOwner::class)
            ->where('collection_name', 'contract')
            ->update(['collection_name' => 'files']);
    }
};
