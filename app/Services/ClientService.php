<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientInteraction;
use App\Models\ClientStage;
use App\Models\Property;
use App\Models\PropertyStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * منطق العملاء المشترك — يخدم الداشبورد (Blade) والـ API معاً (API-first).
 */
class ClientService
{
    /** استعلام العملاء بعد تطبيق الفلاتر — يخدم القائمة وعدّادات المراحل معاً */
    private function filtered(array $filters = []): Builder
    {
        return Client::query()
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['stage_id'] ?? null, fn ($q, $v) => $q->where('stage_id', $v))
            ->when($filters['agent_id'] ?? null, fn ($q, $v) => $q->where('agent_id', $v))
            ->when($filters['type_id'] ?? null, fn ($q, $v) => $q->where('type_id', $v));
    }

    /** قائمة العملاء مع بحث وفلاتر و pagination */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with([
                'stage', 'type', 'agent', 'area', 'desiredUnitType', 'recordedBy',
                'properties', 'interactions.user', 'interactions.stage',
            ])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * عدد العملاء في كل مرحلة (لأزرار الفلتر) — بنفس الفلاتر الأخرى المفعّلة
     * لكن بدون فلتر المرحلة نفسه، حتى تظل كل الأزرار تعرض أعدادها.
     *
     * @return array{total:int, stages:array<int,int>}
     */
    public function stageCounts(array $filters = []): array
    {
        $base = $this->filtered(array_diff_key($filters, ['stage_id' => null]));

        return [
            'total' => (clone $base)->count(),
            'stages' => (clone $base)
                ->selectRaw('stage_id, COUNT(*) as aggregate')
                ->groupBy('stage_id')
                ->pluck('aggregate', 'stage_id')
                ->all(),
        ];
    }

    /** تفاصيل عميل مع كل ما يخصّه (لشاشة العميل الواحدة) */
    public function load(Client $client): Client
    {
        return $client->load([
            'stage', 'type', 'area', 'agent', 'source', 'desiredUnitType', 'recordedBy',
            'interactions.user', 'interactions.stage',
            'properties.status', 'properties.area', 'properties.unitType', 'properties.media',
        ]);
    }

    public function create(array $data): Client
    {
        $data['stage_id'] ??= ClientStage::where('key', 'new')->value('id');
        $data['recorded_by'] = auth()->id();

        return Client::create($data);
    }

    public function update(Client $client, array $data): Client
    {
        $client->update($data);

        return $client;
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }

    /**
     * تسجيل تفاعل (مكالمة/مقابلة) — ولو معاه مرحلة جديدة، يحدّث حالة العميل كمان.
     */
    public function logInteraction(Client $client, array $data): ClientInteraction
    {
        return DB::transaction(function () use ($client, $data) {
            $interaction = $client->interactions()->create([
                'user_id' => $data['user_id'] ?? auth()->id(),
                'type' => $data['type'],
                'notes' => $data['notes'] ?? null,
                'stage_id' => $data['stage_id'] ?? null,
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);

            // تغيير حالة العميل لو اتحدّدت مرحلة جديدة
            if (! empty($data['stage_id'])) {
                $client->update(['stage_id' => $data['stage_id']]);
            }

            return $interaction;
        });
    }

    /** ربط عقار بالعميل (سجل عقاراته) */
    public function attachProperty(Client $client, int $propertyId, ?string $relation = null, ?string $notes = null): void
    {
        DB::transaction(function () use ($client, $propertyId, $relation, $notes) {
            $property = Property::query()->lockForUpdate()->with(['status', 'clients'])->findOrFail($propertyId);

            if ($client->properties()->whereKey($propertyId)->exists()) {
                throw ValidationException::withMessages([
                    'property_id' => 'هذا العقار مضاف بالفعل لهذا العميل.',
                ]);
            }

            if ($property->status?->key === 'sold') {
                throw ValidationException::withMessages([
                    'property_id' => 'لا يمكن إضافة هذا العقار لأنه مباع.',
                ]);
            }

            if ($property->status?->key === 'reserved' || $property->clients->contains(fn ($linked) => $linked->pivot?->relation === 'reserved')) {
                throw ValidationException::withMessages([
                    'property_id' => 'لا يمكن إضافة هذا العقار لأنه محجوز لعميل آخر.',
                ]);
            }

            $client->properties()->attach($propertyId, ['relation' => $relation, 'notes' => $notes]);

            if ($relation === 'reserved') {
                $reservedId = PropertyStatus::where('key', 'reserved')->value('id');
                if ($reservedId) {
                    $property->update(['status_id' => $reservedId]);
                }
            }
        });
    }

    public function detachProperty(Client $client, int $propertyId): void
    {
        DB::transaction(function () use ($client, $propertyId) {
            $property = Property::query()->lockForUpdate()->with('status')->findOrFail($propertyId);
            $pivot = DB::table('client_property')
                ->where('client_id', $client->id)
                ->where('property_id', $propertyId)
                ->first();

            $client->properties()->detach($propertyId);

            if ($pivot?->relation === 'reserved' && $property->status?->key === 'reserved') {
                $stillReserved = DB::table('client_property')
                    ->where('property_id', $propertyId)
                    ->where('relation', 'reserved')
                    ->exists();
                $availableId = PropertyStatus::where('key', 'available')->value('id');

                if (! $stillReserved && $availableId) {
                    $property->update(['status_id' => $availableId]);
                }
            }
        });
    }

    /** مراحل الـ Pipeline للاستخدام في الفلاتر والفورمات */
    public function stages()
    {
        return ClientStage::where('is_active', true)->orderBy('sort_order')->get();
    }
}
