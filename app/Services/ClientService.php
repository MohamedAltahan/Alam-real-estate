<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientInteraction;
use App\Models\ClientPropertyNeed;
use App\Models\ClientStage;
use App\Models\ClientType;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\PropertyReservation;
use App\Models\PropertyStatus;
use App\Support\FullTextQuery;
use App\Support\PhoneCountries;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * منطق العملاء المشترك — يخدم الداشبورد (Blade) والـ API معاً (API-first).
 */
class ClientService
{
    /** مفاتيح فلاتر القائمة */
    public const FILTER_KEYS = [
        'search', 'stage_id', 'agent_id', 'city_id', 'area_id', 'unit_type_id',
        'nationality', 'social_status', 'preferred_contact', 'from', 'to', 'notes_q',
    ];

    public function __construct(private ClientAuditLogger $audit) {}

    /** استعلام العملاء بعد تطبيق الفلاتر — يخدم القائمة وعدّادات المراحل معاً */
    private function filtered(array $filters = []): Builder
    {
        return Client::query()
            ->when($filters['search'] ?? null, fn (Builder $q, $search) => $this->applySearch($q, (string) $search))
            ->when($filters['stage_id'] ?? null, fn (Builder $q, $v) => $q->where('stage_id', $v))
            ->when($filters['agent_id'] ?? null, fn (Builder $q, $v) => $q->where('agent_id', $v))
            ->when($filters['city_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('needs', fn (Builder $n) => $n->where('city_id', $v)))
            ->when($filters['area_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('needs', fn (Builder $n) => $n->where('area_id', $v)))
            ->when($filters['unit_type_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('needs', fn (Builder $n) => $n->where('unit_type_id', $v)))
            ->when($filters['nationality'] ?? null, fn (Builder $q, $v) => $q->whereRaw('LOWER(nationality) LIKE ?', ['%'.mb_strtolower(trim((string) $v)).'%']))
            ->when($filters['social_status'] ?? null, fn (Builder $q, $v) => $q->where('social_status', $v))
            ->when($filters['preferred_contact'] ?? null, fn (Builder $q, $v) => $q->where('preferred_contact', $v))
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($this->notesTerm($filters), fn (Builder $q, $term) => $this->applyNotesSearch($q, $term));
    }

    /** بحث عام: الاسم والبريد (بدون حساسية لحالة الأحرف) والهاتف بالأرقام فقط */
    private function applySearch(Builder $query, string $search): void
    {
        $term = '%'.mb_strtolower(trim($search)).'%';
        $digits = preg_replace('/\D+/', '', $search) ?? '';

        $query->where(function (Builder $q) use ($term, $digits, $search) {
            $q->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(email) LIKE ?', [$term]);

            if ($digits === '') {
                return;
            }

            $q->orWhere('phone', 'like', "%{$digits}%");

            // لو كُتب الرقم بمفتاح الدولة (+965…) نبحث أيضاً بالرقم المحلي فقط
            $withCode = str_starts_with(trim($search), '+')
                || str_starts_with($digits, '00')
                || (strlen($digits) === 11 && str_starts_with($digits, '965'));

            if ($withCode) {
                $national = PhoneNumber::split($search)['national'];

                if ($national !== '' && $national !== $digits) {
                    $q->orWhere('phone', 'like', "%{$national}%");
                }
            }
        });
    }

    /** نص البحث في الملاحظات بعد التحقق من الحد الأدنى للطول */
    private function notesTerm(array $filters): ?string
    {
        $term = trim((string) ($filters['notes_q'] ?? ''));

        return mb_strlen($term) >= (int) config('clients.notes_min_length', 3) ? $term : null;
    }

    /**
     * البحث في الملاحظات: فهرس FULLTEXT على MariaDB/MySQL، وإلا LIKE.
     * يشمل أيضاً ملاحظات سجل التواصل (نصوص قصيرة، LIKE يكفيها).
     */
    private function applyNotesSearch(Builder $query, string $term): void
    {
        $like = '%'.mb_strtolower($term).'%';
        $driver = $query->getConnection()->getDriverName();
        $boolean = in_array($driver, ['mysql', 'mariadb'], true) && config('clients.notes_fulltext', true)
            ? FullTextQuery::boolean($term)
            : '';

        $query->where(function (Builder $q) use ($like, $boolean) {
            if ($boolean !== '') {
                $q->whereFullText(['notes'], $boolean, ['mode' => 'boolean']);
            } else {
                $q->whereRaw('LOWER(notes) LIKE ?', [$like]);
            }

            $q->orWhereExists(function ($sub) use ($like) {
                $sub->select(DB::raw(1))
                    ->from('client_interactions')
                    ->whereColumn('client_interactions.client_id', 'clients.id')
                    ->whereRaw('LOWER(client_interactions.notes) LIKE ?', [$like]);
            });
        });
    }

    /** قائمة العملاء مع بحث وفلاتر و pagination */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with([
                'stage', 'agent', 'recordedBy',
                'needs.city', 'needs.area', 'needs.unitType',
                'viewings.property.media', 'viewings.property.area',
                'interactions.user', 'interactions.stage',
                'properties',
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
            'stage', 'type', 'agent', 'source', 'recordedBy',
            'needs.city', 'needs.area', 'needs.unitType',
            'viewings.property.status', 'viewings.property.media', 'viewings.createdBy',
            'interactions.user', 'interactions.stage',
            'properties.status', 'properties.area', 'properties.unitType', 'properties.media',
            'properties.activeReservation.client', 'properties.activeReservation.reservedBy',
            'auditLogs' => fn ($q) => $q->with('user')->limit(50),
        ]);
    }

    public function create(array $data): Client
    {
        return DB::transaction(function () use ($data) {
            [$needs, $viewings, $data] = $this->extractRows($data);

            $data['stage_id'] = $data['stage_id'] ?? ClientStage::where('key', 'new')->value('id');
            $data['type_id'] = $data['type_id'] ?? $this->tenantTypeId();
            $data['phone_code'] = $data['phone_code'] ?? PhoneCountries::DEFAULT;
            $data['recorded_by'] = auth()->id();

            $client = Client::create($data);

            $this->syncNeeds($client, $needs);
            $this->syncViewings($client, $viewings);

            return $client;
        });
    }

    public function update(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data) {
            [$needs, $viewings, $data] = $this->extractRows($data);

            $client->update($data);

            $this->syncNeeds($client, $needs);
            $this->syncViewings($client, $viewings);

            return $client;
        });
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

            $this->audit->record($client, 'deleted', null, $this->audit->snapshot($client, ['name', 'phone_code', 'phone', 'email'], removed: true));

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

            $this->audit->record($client, 'interaction_logged', $interaction, $this->audit->snapshot($interaction, ['type', 'notes', 'stage_id']));

            // تغيير حالة العميل لو اتحدّدت مرحلة جديدة (يسجّله ClientObserver)
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

            $relation = in_array($relation, ['interested', 'viewed'], true) ? $relation : 'interested';

            $client->properties()->attach($propertyId, [
                'relation' => $relation,
                'notes' => $notes,
            ]);

            $this->audit->record($client, 'property_attached', $property, [
                'property_id' => ['old' => null, 'new' => (string) $property->id],
                'relation' => ['old' => null, 'new' => $relation],
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

            $this->audit->record($client, 'reservation_released', $property, [
                'property_id' => ['old' => (string) $property->id, 'new' => null],
            ]);
        });
    }

    public function detachProperty(Client $client, int $propertyId): void
    {
        DB::transaction(function () use ($client, $propertyId) {
            $property = Property::query()->lockForUpdate()->findOrFail($propertyId);
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

            $this->audit->record($client, 'property_detached', $property, [
                'property_id' => ['old' => (string) $property->id, 'new' => null],
            ]);
        });
    }

    /** معرّف نوع «مستأجر» — كل العملاء الجدد يُسجَّلون به */
    public function tenantTypeId(): ?int
    {
        $id = ClientType::where('key', 'tenant')->value('id');

        return $id ? (int) $id : null;
    }

    /** مراحل الـ Pipeline للاستخدام في الفلاتر والفورمات */
    public function stages()
    {
        return ClientStage::where('is_active', true)->orderBy('sort_order')->get();
    }

    // ===== الأسطر المتعددة: الاحتياجات والمعاينات =====

    /**
     * فصل أسطر الاحتياجات والمعاينات عن بيانات العميل.
     * غياب المفتاح = لا تلمس الأسطر الحالية (مفيد للـ API)، ووجوده = مزامنة كاملة.
     *
     * @return array{0: ?array, 1: ?array, 2: array}
     */
    private function extractRows(array $data): array
    {
        $needs = array_key_exists('needs', $data) ? (array) $data['needs'] : null;
        $viewings = array_key_exists('viewings', $data) ? (array) $data['viewings'] : null;

        unset($data['needs'], $data['viewings']);

        return [$needs, $viewings, $data];
    }

    private function syncNeeds(Client $client, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        $existing = $client->needs()->get()->keyBy('id');
        $kept = [];
        $fields = ['unit_type_id', 'city_id', 'area_id'];

        foreach (array_values($rows) as $index => $row) {
            $attributes = [
                'unit_type_id' => $row['unit_type_id'] ?: null,
                'city_id' => $row['city_id'] ?: null,
                'area_id' => $row['area_id'] ?: null,
                'sort_order' => $index,
            ];

            $need = ! empty($row['id']) ? $existing->get((int) $row['id']) : null;

            if ($need) {
                $need->fill($attributes);
                $changes = $this->audit->changes($need, $need->getDirty(), ['sort_order', 'created_at', 'updated_at']);

                if ($need->isDirty()) {
                    $need->save();
                }

                if ($changes) {
                    $this->audit->record($client, 'need_updated', $need, $changes);
                }
            } else {
                $need = $client->needs()->create($attributes);
                $this->audit->record($client, 'need_added', $need, $this->audit->snapshot($need, $fields));
            }

            $kept[] = $need->id;
        }

        foreach ($existing->except($kept) as $need) {
            /** @var ClientPropertyNeed $need */
            $this->audit->record($client, 'need_removed', $need, $this->audit->snapshot($need, $fields, removed: true));
            $need->delete();
        }

        $client->unsetRelation('needs');
    }

    private function syncViewings(Client $client, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        $existing = $client->viewings()->get()->keyBy('id');
        $kept = [];
        $fields = ['property_id', 'scheduled_at', 'in_person', 'outcome', 'notes'];

        foreach (array_values($rows) as $index => $row) {
            $propertyId = (int) $row['property_id'];
            $outcome = in_array($row['outcome'] ?? null, ClientViewing::OUTCOMES, true) ? $row['outcome'] : ClientViewing::OUTCOME_PENDING;

            $attributes = [
                'property_id' => $propertyId,
                'scheduled_at' => $row['scheduled_at'],
                'in_person' => array_key_exists('in_person', $row) && $row['in_person'] !== null ? (bool) $row['in_person'] : true,
                'outcome' => $outcome,
                'notes' => $row['notes'] ?? null,
            ];

            $viewing = ! empty($row['id']) ? $existing->get((int) $row['id']) : null;
            $property = Property::query()->lockForUpdate()->with('status')->findOrFail($propertyId);

            // التحقق من العقار عند إضافة معاينة أو تغيير عقار معاينة موجودة
            if (! $viewing || (int) $viewing->property_id !== $propertyId) {
                $this->ensurePropertyCanBeViewed($property, $client, $index);
            }

            if ($viewing) {
                $viewing->fill($attributes);

                if ($viewing->isDirty('outcome')) {
                    $viewing->outcome_at = $outcome === ClientViewing::OUTCOME_PENDING ? null : now();
                }

                // تغيّر الموعد إلى المستقبل يعيد تفعيل التذكير
                if ($viewing->isDirty('scheduled_at') && $viewing->scheduled_at?->isFuture()) {
                    $viewing->reminded_at = null;
                }

                $changes = $this->audit->changes($viewing, $viewing->getDirty(), ['outcome_at', 'reminded_at', 'created_at', 'updated_at']);

                if ($viewing->isDirty()) {
                    $viewing->save();
                }

                if ($changes) {
                    $this->audit->record($client, 'viewing_updated', $viewing, $changes);
                }
            } else {
                $viewing = $client->viewings()->create($attributes + [
                    'created_by' => auth()->id(),
                    'outcome_at' => $outcome === ClientViewing::OUTCOME_PENDING ? null : now(),
                ]);

                $this->audit->record($client, 'viewing_added', $viewing, $this->audit->snapshot($viewing, $fields));
            }

            $this->markViewed($client, $property);
            $kept[] = $viewing->id;
        }

        foreach ($existing->except($kept) as $viewing) {
            /** @var ClientViewing $viewing */
            $this->audit->record($client, 'viewing_removed', $viewing, $this->audit->snapshot($viewing, $fields, removed: true));
            $viewing->delete();
        }

        $client->unsetRelation('viewings');
    }

    /** المعاينة تربط العقار بسجل العميل تلقائياً بعلاقة «تمت المعاينة» */
    private function markViewed(Client $client, Property $property): void
    {
        $linked = $client->properties()->whereKey($property->id)->first();

        if (! $linked) {
            $client->properties()->attach($property->id, ['relation' => 'viewed']);
        } elseif ($linked->pivot->relation === 'interested') {
            $client->properties()->updateExistingPivot($property->id, ['relation' => 'viewed']);
        }
    }

    /** لا معاينة لعقار مباع أو محجوز لعميل آخر؛ المحجوز لنفس العميل مسموح. */
    private function ensurePropertyCanBeViewed(Property $property, Client $client, int $index): void
    {
        $key = "viewings.{$index}.property_id";

        if ($property->status?->key === 'sold') {
            throw ValidationException::withMessages([$key => 'لا يمكن جدولة معاينة لعقار مباع.']);
        }

        $activeReservation = PropertyReservation::query()
            ->where('active_property_id', $property->id)
            ->where('status', PropertyReservation::STATUS_ACTIVE)
            ->with('client')
            ->first();

        if ($activeReservation && (int) $activeReservation->client_id !== (int) $client->id) {
            throw ValidationException::withMessages([$key => $this->reservationMessage($activeReservation)]);
        }

        if (! $activeReservation && $property->status?->key === 'reserved') {
            throw ValidationException::withMessages([
                $key => 'حالة العقار محجوزة لكن لا يوجد حجز نشط مرتبط بعميل. راجع حالة العقار أولًا.',
            ]);
        }
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

        $this->audit->record($client, 'property_reserved', $property, [
            'property_id' => ['old' => null, 'new' => (string) $property->id],
        ]);

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
}
