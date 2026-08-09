<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('desired_unit_type_id')->nullable()->constrained('unit_types')->nullOnDelete();
            $table->string('social_status', 30)->nullable();
            $table->string('nationality', 120)->nullable();
            $table->unsignedSmallInteger('household_size')->nullable();
            $table->string('workplace', 255)->nullable();
            $table->boolean('in_person')->nullable();
            $table->string('visit_times', 255)->nullable();
            $table->string('preferred_contact', 20)->nullable();
            $table->text('property_address')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        $this->normalizeClientStages();
        $this->removeDuplicateClientProperties();

        Schema::table('client_property', function (Blueprint $table) {
            $table->unique(['client_id', 'property_id'], 'client_property_pair_uq');
        });
    }

    public function down(): void
    {
        Schema::table('client_property', function (Blueprint $table) {
            $table->dropUnique('client_property_pair_uq');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desired_unit_type_id');
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn([
                'social_status',
                'nationality',
                'household_size',
                'workplace',
                'in_person',
                'visit_times',
                'preferred_contact',
                'property_address',
            ]);
        });

        $stages = [
            'new' => ['ar' => 'جديد', 'en' => 'New', 'color' => '#3B5BA5', 'final' => false, 'order' => 0],
            'contacted' => ['ar' => 'تم التواصل', 'en' => 'Contacted', 'color' => '#7481E0', 'final' => false, 'order' => 1],
            'interested' => ['ar' => 'مهتم', 'en' => 'Interested', 'color' => '#B5842A', 'final' => false, 'order' => 2],
            'negotiating' => ['ar' => 'تفاوض', 'en' => 'Negotiating', 'color' => '#E0B450', 'final' => false, 'order' => 3],
            'closed_won' => ['ar' => 'صفقة ناجحة', 'en' => 'Closed Won', 'color' => '#2E7D5B', 'final' => true, 'order' => 4],
            'closed_lost' => ['ar' => 'صفقة خاسرة', 'en' => 'Closed Lost', 'color' => '#C0392B', 'final' => true, 'order' => 5],
        ];

        foreach ($stages as $key => $stage) {
            DB::table('client_stages')->where('key', $key)->update([
                'name' => json_encode(['ar' => $stage['ar'], 'en' => $stage['en']], JSON_UNESCAPED_UNICODE),
                'color' => $stage['color'],
                'is_final' => $stage['final'],
                'is_active' => true,
                'sort_order' => $stage['order'],
            ]);
        }
    }

    private function normalizeClientStages(): void
    {
        $now = now();
        $stages = [
            'new' => ['ar' => 'طلب جديد', 'en' => 'New Request', 'color' => '#3B5BA5', 'final' => false, 'order' => 0],
            'viewing' => ['ar' => 'معاينة العقار', 'en' => 'Property Viewing', 'color' => '#B5842A', 'final' => false, 'order' => 1],
            'closed_won' => ['ar' => 'ربح', 'en' => 'Won', 'color' => '#2E7D5B', 'final' => true, 'order' => 2],
            'closed_lost' => ['ar' => 'خسارة', 'en' => 'Lost', 'color' => '#C0392B', 'final' => true, 'order' => 3],
        ];

        foreach ($stages as $key => $stage) {
            $payload = [
                'name' => json_encode(['ar' => $stage['ar'], 'en' => $stage['en']], JSON_UNESCAPED_UNICODE),
                'color' => $stage['color'],
                'is_final' => $stage['final'],
                'is_active' => true,
                'sort_order' => $stage['order'],
                'updated_at' => $now,
            ];

            if (DB::table('client_stages')->where('key', $key)->exists()) {
                DB::table('client_stages')->where('key', $key)->update($payload);
            } else {
                DB::table('client_stages')->insert($payload + ['key' => $key, 'created_at' => $now]);
            }
        }

        $viewingId = DB::table('client_stages')->where('key', 'viewing')->value('id');
        $legacyIds = DB::table('client_stages')
            ->whereIn('key', ['contacted', 'interested', 'negotiating'])
            ->pluck('id');

        if ($viewingId && $legacyIds->isNotEmpty()) {
            DB::table('clients')->whereIn('stage_id', $legacyIds)->update(['stage_id' => $viewingId]);
            DB::table('client_interactions')->whereIn('stage_id', $legacyIds)->update(['stage_id' => $viewingId]);
        }

        DB::table('client_stages')
            ->whereIn('key', ['contacted', 'interested', 'negotiating'])
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    private function removeDuplicateClientProperties(): void
    {
        $duplicates = DB::table('client_property')
            ->select(['client_id', 'property_id'])
            ->selectRaw('MIN(id) as keep_id')
            ->groupBy('client_id', 'property_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $row = collect((array) $duplicate)->mapWithKeys(
                fn ($value, $key) => [strtolower((string) $key) => $value]
            );

            DB::table('client_property')
                ->where('client_id', $row['client_id'])
                ->where('property_id', $row['property_id'])
                ->where('id', '!=', $row['keep_id'])
                ->delete();
        }
    }
};
