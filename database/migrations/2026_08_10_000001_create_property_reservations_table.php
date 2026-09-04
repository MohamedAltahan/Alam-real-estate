<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            // يساوي property_id للحجز النشط فقط، ويصبح NULL بعد الإلغاء.
            // القيد الفريد يمنع حجز العقار لعميلين في الوقت نفسه على MariaDB وOracle.
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

        $this->normalizeLegacyReservations();
    }

    public function down(): void
    {
        Schema::dropIfExists('property_reservations');
    }

    /**
     * تحويل البيانات القديمة بدون افتراض أن كلمة reserved في جدول الربط حجز فعلي.
     * حالة العقار الحالية هي مرجع الترحيل مرة واحدة، وبعده يصبح جدول الحجوزات هو المرجع.
     */
    private function normalizeLegacyReservations(): void
    {
        $reservedStatusId = DB::table('property_statuses')->where('key', 'reserved')->value('id');
        $availableStatusId = DB::table('property_statuses')->where('key', 'available')->value('id');

        if (! $reservedStatusId) {
            DB::table('client_property')->where('relation', 'reserved')->update(['relation' => 'interested']);

            return;
        }

        $legacyRows = DB::table('client_property as cp')
            ->join('properties as p', 'p.id', '=', 'cp.property_id')
            ->select([
                'cp.id', 'cp.client_id', 'cp.property_id', 'cp.notes',
                'cp.created_at', 'p.status_id',
            ])
            ->where('cp.relation', 'reserved')
            ->orderBy('cp.property_id')
            ->orderBy('cp.created_at')
            ->get();

        $migratedProperties = [];

        foreach ($legacyRows as $row) {
            if ((int) $row->status_id === (int) $reservedStatusId && ! isset($migratedProperties[$row->property_id])) {
                $reservedAt = $row->created_at ?: now();

                DB::table('property_reservations')->insert([
                    'property_id' => $row->property_id,
                    'client_id' => $row->client_id,
                    'active_property_id' => $row->property_id,
                    'status' => 'active',
                    'reserved_at' => $reservedAt,
                    'notes' => $row->notes,
                    'created_at' => $reservedAt,
                    'updated_at' => now(),
                ]);

                $migratedProperties[$row->property_id] = true;
            }
        }

        // الربط يعبر عن الاهتمام/المعاينة فقط من الآن، وليس هو سجل الحجز.
        DB::table('client_property')->where('relation', 'reserved')->update(['relation' => 'interested']);

        // أي حالة "محجوز" قديمة بلا عميل صاحب حجز كانت حالة يتيمة ومضللة.
        if ($availableStatusId) {
            DB::table('properties')
                ->where('status_id', $reservedStatusId)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('property_reservations')
                        ->whereColumn('property_reservations.active_property_id', 'properties.id')
                        ->where('property_reservations.status', 'active');
                })
                ->update([
                    'status_id' => $availableStatusId,
                    'updated_at' => now(),
                ]);
        }
    }
};
