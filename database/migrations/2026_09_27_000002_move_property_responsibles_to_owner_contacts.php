<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المسؤولون عن العقار صاروا يُختارون من مسؤولي المالك (property_owner_contacts) بدل إدخالهم لكل عقار،
 * وحارس العقار (اسم + رقم) صار حقلين خاصين بالعقار.
 * الصفوف القديمة: صفة «الحارس» ← حقلا الحارس، والباقي ← مسؤولو المالك (بلا تكرار الرقم) ويُربطون بالعقار.
 */
return new class extends Migration
{
    private const GUARD_ROLES = ['الحارس', 'حارس', 'حارس العقار'];

    public function up(): void
    {
        Schema::create('property_responsibles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('property_owner_contacts')->cascadeOnDelete();
            $table->unique(['property_id', 'contact_id'], 'prop_resp_unique');
            $table->index('contact_id', 'prop_resp_contact_idx');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('guard_name', 150)->nullable();
            $table->string('guard_phone_code', 8)->nullable();
            $table->string('guard_phone', 40)->nullable();
        });

        if (Schema::hasTable('property_contacts')) {
            $this->moveContacts();
            Schema::drop('property_contacts');
        }
    }

    public function down(): void
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
        $rows = DB::table('property_responsibles')
            ->join('property_owner_contacts', 'property_owner_contacts.id', '=', 'property_responsibles.contact_id')
            ->orderBy('property_responsibles.property_id')
            ->orderBy('property_owner_contacts.sort_order')
            ->get(['property_responsibles.property_id', 'property_owner_contacts.phone_code', 'property_owner_contacts.phone', 'property_owner_contacts.role', 'property_owner_contacts.name'])
            ->map(fn ($row) => array_change_key_case((array) $row))
            ->groupBy(fn (array $row) => (int) $row['property_id']);

        foreach ($rows as $propertyId => $contacts) {
            foreach ($contacts->values() as $index => $contact) {
                DB::table('property_contacts')->insert([
                    'property_id' => $propertyId, 'phone_code' => $contact['phone_code'], 'phone' => $contact['phone'],
                    'role' => $contact['role'], 'name' => $contact['name'], 'sort_order' => $index + 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        DB::table('properties')->whereNotNull('guard_phone')->orderBy('id')
            ->get(['id', 'guard_name', 'guard_phone_code', 'guard_phone'])
            ->each(function ($row) use ($now) {
                $row = array_change_key_case((array) $row);

                DB::table('property_contacts')->insert([
                    'property_id' => (int) $row['id'], 'phone_code' => $row['guard_phone_code'], 'phone' => $row['guard_phone'],
                    'role' => self::GUARD_ROLES[0], 'name' => $row['guard_name'], 'sort_order' => 0,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['guard_name', 'guard_phone_code', 'guard_phone']);
        });

        Schema::dropIfExists('property_responsibles');
    }

    private function moveContacts(): void
    {
        $now = now();

        $byProperty = DB::table('property_contacts')
            ->orderBy('property_id')->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn ($row) => array_change_key_case((array) $row))
            ->groupBy(fn (array $row) => (int) $row['property_id']);

        foreach ($byProperty as $propertyId => $contacts) {
            $ownerId = (int) DB::table('properties')->where('id', $propertyId)->value('owner_id');

            // عقار بلا مالك: لا مكان لمسؤوليه إلا خانة الحارس
            $guard = $contacts->first(fn (array $c) => in_array(trim((string) $c['role']), self::GUARD_ROLES, true))
                ?? ($ownerId ? null : $contacts->first());

            if ($guard) {
                DB::table('properties')->where('id', $propertyId)->update([
                    'guard_name' => $guard['name'],
                    'guard_phone_code' => $guard['phone_code'],
                    'guard_phone' => $guard['phone'],
                ]);
            }

            if (! $ownerId) {
                continue;
            }

            foreach ($contacts as $contact) {
                if ($guard && (int) $contact['id'] === (int) $guard['id']) {
                    continue;
                }

                $contactId = (int) DB::table('property_owner_contacts')
                    ->where('owner_id', $ownerId)->where('phone', $contact['phone'])->value('id');

                if (! $contactId) {
                    $contactId = (int) DB::table('property_owner_contacts')->insertGetId([
                        'owner_id' => $ownerId,
                        'phone_code' => $contact['phone_code'],
                        'phone' => $contact['phone'],
                        'role' => $contact['role'],
                        'name' => $contact['name'],
                        'sort_order' => (int) DB::table('property_owner_contacts')->where('owner_id', $ownerId)->max('sort_order') + 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $linked = DB::table('property_responsibles')
                    ->where('property_id', $propertyId)->where('contact_id', $contactId)->exists();

                if (! $linked) {
                    DB::table('property_responsibles')->insert(['property_id' => $propertyId, 'contact_id' => $contactId]);
                }
            }
        }
    }
};
