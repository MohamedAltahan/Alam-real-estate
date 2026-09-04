<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientInteraction;
use App\Models\ClientStage;
use App\Models\Property;
use App\Models\PropertyReservation;
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
            'properties.activeReservation.client', 'properties.activeReservation.reservedBy',
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
        DB::transaction(function () use ($client) {
            $reservations = PropertyReservation::query()
                ->where('client_id', $client->id)
                ->where('status', PropertyReservation::STATUS_ACTIVE)
                ->whereNotNull('active_property_id')
                ->lockForUpdate()
                ->get();
            $availableId = PropertyStatus::where('key', 'available')->value('id');

            foreach ($reservations as $reservation) {
                $property = Property::query()->lockForUpdate()->with('status')->find($reservation->property_id);

                if ($property?->status?->key === 'reserved' && $availableId) {
                    $property->update(['status_id' => $availableId]);
                }

                $reservation->update([
                    'active_property_id' => null,
                    'status' => PropertyReservation::STATUS_CANCELLED,
                    'released_by' => auth()->id(),
                    'released_at' => now(),
                ]);
            }

            $client->delete();
        });
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
            $property = Property::query()->lockForUpdate()->with('status')->findOrFail($propertyId);

            if ($relation === 'reserved') {
                $this->reservePropertyLocked($client, $property, $notes);

                return;
            }

            if ($client->properties()->whereKey($propertyId)->exists()) {
                throw ValidationException::withMessages([
                    'property_id' => 'هذا العقار مضاف بالفعل لهذا العميل.',
                ]);
            }

            $this->ensurePropertyCanBeLinked($property);

            $client->properties()->attach($propertyId, [
                'relation' => in_array($relation, ['interested', 'viewed'], true) ? $relation : 'interested',
                'notes' => $notes,
            ]);
        });
    }

    /** إنشاء حجز نشط، مع قفل العقار لمنع طلبين متزامنين من حجز نفس العقار. */
    public function reserveProperty(Client $client, int $propertyId, ?string $notes = null): PropertyReservation
    {
        return DB::transaction(function () use ($client, $propertyId, $notes) {
            $property = Property::query()->lockForUpdate()->with('status')->findOrFail($propertyId);

            return $this->reservePropertyLocked($client, $property, $notes);
        });
    }

    /** إلغاء الحجز النشط صراحةً وإعادة العقار إلى متاح. */
    public function releaseReservation(Client $client, int $propertyId): void
    {
        DB::transaction(function () use ($client, $propertyId) {
            $property = Property::query()->lockForUpdate()->with('status')->findOrFail($propertyId);
            $reservation = PropertyReservation::query()
                ->where('active_property_id', $propertyId)
                ->where('status', PropertyReservation::STATUS_ACTIVE)
                ->lockForUpdate()
                ->with('client')
                ->first();

            if (! $reservation) {
                throw ValidationException::withMessages([
                    'property_id' => 'لا يوجد حجز نشط لهذا العقار.',
                ]);
            }

            if ((int) $reservation->client_id !== (int) $client->id) {
                throw ValidationException::withMessages([
                    'property_id' => $this->reservationMessage($reservation),
                ]);
            }

            $reservation->update([
                'active_property_id' => null,
                'status' => PropertyReservation::STATUS_CANCELLED,
                'released_by' => auth()->id(),
                'released_at' => now(),
            ]);

            if ($property->status?->key === 'reserved') {
                $availableId = PropertyStatus::where('key', 'available')->value('id');
                if ($availableId) {
                    $property->update(['status_id' => $availableId]);
                }
            }
        });
    }

    public function detachProperty(Client $client, int $propertyId): void
    {
        DB::transaction(function () use ($client, $propertyId) {
            Property::query()->lockForUpdate()->findOrFail($propertyId);
            $hasActiveReservation = PropertyReservation::query()
                ->where('active_property_id', $propertyId)
                ->where('client_id', $client->id)
                ->where('status', PropertyReservation::STATUS_ACTIVE)
                ->exists();

            if ($hasActiveReservation) {
                throw ValidationException::withMessages([
                    'property_id' => 'ألغِ الحجز النشط أولًا، ثم يمكنك إزالة العقار من سجل العميل.',
                ]);
            }

            $client->properties()->detach($propertyId);
        });
    }

    private function reservePropertyLocked(Client $client, Property $property, ?string $notes): PropertyReservation
    {
        if ($property->status?->key === 'sold') {
            throw ValidationException::withMessages([
                'property_id' => 'لا يمكن حجز هذا العقار لأنه مباع.',
            ]);
        }

        $activeReservation = PropertyReservation::query()
            ->where('active_property_id', $property->id)
            ->where('status', PropertyReservation::STATUS_ACTIVE)
            ->lockForUpdate()
            ->with('client')
            ->first();

        if ($activeReservation) {
            $message = (int) $activeReservation->client_id === (int) $client->id
                ? 'هذا العقار محجوز بالفعل لهذا العميل منذ '.$activeReservation->reserved_at?->format('Y-m-d').'.'
                : $this->reservationMessage($activeReservation);

            throw ValidationException::withMessages(['property_id' => $message]);
        }

        if ($property->status?->key === 'reserved') {
            throw ValidationException::withMessages([
                'property_id' => 'حالة العقار محجوزة لكن لا يوجد حجز نشط مرتبط بعميل. راجع حالة العقار أولًا.',
            ]);
        }

        $reservedId = PropertyStatus::where('key', 'reserved')->value('id');
        if (! $reservedId) {
            throw ValidationException::withMessages([
                'property_id' => 'حالة «محجوز» غير مهيأة في النظام.',
            ]);
        }

        if (! $client->properties()->whereKey($property->id)->exists()) {
            $client->properties()->attach($property->id, [
                'relation' => 'interested',
                'notes' => $notes,
            ]);
        }

        $reservation = PropertyReservation::create([
            'property_id' => $property->id,
            'client_id' => $client->id,
            'active_property_id' => $property->id,
            'status' => PropertyReservation::STATUS_ACTIVE,
            'reserved_by' => auth()->id(),
            'reserved_at' => now(),
            'notes' => $notes,
        ]);

        $property->update(['status_id' => $reservedId]);

        return $reservation;
    }

    private function ensurePropertyCanBeLinked(Property $property): void
    {
        if ($property->status?->key === 'sold') {
            throw ValidationException::withMessages([
                'property_id' => 'لا يمكن إضافة هذا العقار لأنه مباع.',
            ]);
        }

        $activeReservation = PropertyReservation::query()
            ->where('active_property_id', $property->id)
            ->where('status', PropertyReservation::STATUS_ACTIVE)
            ->with('client')
            ->first();

        if ($activeReservation) {
            throw ValidationException::withMessages([
                'property_id' => $this->reservationMessage($activeReservation),
            ]);
        }

        if ($property->status?->key === 'reserved') {
            throw ValidationException::withMessages([
                'property_id' => 'لا يمكن إضافة العقار لأن حالته «محجوز». راجع تفاصيل العقار.',
            ]);
        }
    }

    private function reservationMessage(PropertyReservation $reservation): string
    {
        $clientName = $reservation->client?->name ?: 'عميل آخر';
        $date = $reservation->reserved_at?->format('Y-m-d');

        return 'العقار محجوز حاليًا للعميل «'.$clientName.'»'.($date ? ' منذ '.$date : '').'. ألغِ الحجز الحالي أولًا.';
    }

    /** مراحل الـ Pipeline للاستخدام في الفلاتر والفورمات */
    public function stages()
    {
        return ClientStage::where('is_active', true)->orderBy('sort_order')->get();
    }
}
