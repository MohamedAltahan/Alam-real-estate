<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientInteraction;
use App\Models\ClientPropertyNeed;
use App\Models\ClientStage;
use App\Models\ClientType;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Support\PropertyLookup;
use App\Support\FullTextQuery;
use App\Support\PhoneCountries;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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

    /** فلاتر تبويب «الطلبات المميزة» في شاشة طلبات التواصل — الأساسية فقط */
    public const FEATURED_FILTER_KEYS = ['search', 'stage_id', 'agent_id'];

    public function __construct(private ClientAuditLogger $audit, private ViewingOutcomeSync $sync) {}

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
            ])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /** الطلبات المميزة: العملاء المعلَّمون «طلب مميز» — بنفس البحث والفلاتر الأساسية */
    public function paginateFeatured(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return $this->filtered(array_intersect_key($filters, array_flip(self::FEATURED_FILTER_KEYS)))
            ->featured()
            ->with(['stage', 'agent', 'needs.city', 'needs.area', 'needs.unitType'])
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
            'viewings.property.owner.contacts', 'viewings.property.agent', 'viewings.property.area',
            'interactions.user', 'interactions.stage',
            'properties.status', 'properties.area', 'properties.unitType', 'properties.media',
            'auditLogs' => fn ($q) => $q->with('user')->limit(50),
        ]);
    }

    public function create(array $data, ?Request $request = null): Client
    {
        return DB::transaction(function () use ($data, $request) {
            [$needs, $viewings, $data] = $this->extractRows($data);

            $data['stage_id'] = $data['stage_id'] ?? ClientStage::where('key', 'new')->value('id');
            $data['type_id'] = $data['type_id'] ?? $this->tenantTypeId();
            $data['phone_code'] = $data['phone_code'] ?? PhoneCountries::DEFAULT;
            $data['recorded_by'] = auth()->id();

            $client = Client::create($data);

            $this->syncNeeds($client, $needs);
            $this->syncViewings($client, $viewings);
            $this->syncFiles($client, $request);

            return $client;
        });
    }

    public function update(Client $client, array $data, ?Request $request = null): Client
    {
        return DB::transaction(function () use ($client, $data, $request) {
            [$needs, $viewings, $data] = $this->extractRows($data);

            $client->update($data);

            $this->syncNeeds($client, $needs);
            $this->syncViewings($client, $viewings);
            $this->syncFiles($client, $request);

            return $client;
        });
    }

    /** ملفات العميل: حذف المحدد للحذف ثم إضافة الملفات الجديدة — كل ملف يُسجَّل في سجل التعديلات */
    private function syncFiles(Client $client, ?Request $request): void
    {
        if (! $request) {
            return;
        }

        foreach ((array) $request->input('files_removed', []) as $id) {
            $media = $client->media()->where('id', (int) $id)->first();

            if ($media) {
                $this->audit->record($client, 'file_removed', null, ['file' => ['old' => $media->file_name, 'new' => null]]);
                $media->delete();
            }
        }

        foreach ((array) $request->file('files', []) as $file) {
            if ($file) {
                $media = $client->addMedia($file)->toMediaCollection(Client::FILES);
                $this->audit->record($client, 'file_added', null, ['file' => ['old' => null, 'new' => $media->file_name]]);
            }
        }
    }

    public function delete(Client $client): void
    {
        DB::transaction(function () use ($client) {
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

        unset($data['needs'], $data['viewings'], $data['files'], $data['files_removed']);

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
        $fields = ['property_id', 'scheduled_at', 'in_person', 'outcome', 'contract_ends_at', 'notes'];

        foreach (array_values($rows) as $index => $row) {
            $propertyId = (int) $row['property_id'];
            $outcome = in_array($row['outcome'] ?? null, ClientViewing::OUTCOMES, true) ? $row['outcome'] : ClientViewing::OUTCOME_PENDING;

            $attributes = [
                'property_id' => $propertyId,
                'scheduled_at' => $row['scheduled_at'],
                'in_person' => array_key_exists('in_person', $row) && $row['in_person'] !== null ? (bool) $row['in_person'] : true,
                'outcome' => $outcome,
                // تاريخ انتهاء العقد يخص «تم اختيار العقار» ويبقى مع «إخلاء العقار» كسجل
                'contract_ends_at' => in_array($outcome, [ClientViewing::OUTCOME_CHOSEN, ClientViewing::OUTCOME_VACATED], true)
                    ? (($row['contract_ends_at'] ?? null) ?: null)
                    : null,
                'notes' => $row['notes'] ?? null,
            ];

            $viewing = ! empty($row['id']) ? $existing->get((int) $row['id']) : null;
            $property = Property::query()->lockForUpdate()->with('status')->findOrFail($propertyId);

            // التحقق من العقار عند إضافة معاينة أو تغيير عقار معاينة موجودة فقط —
            // فإعادة حفظ معاينة مختارة على عقار بيع صار «مباعاً» بسببها لا تُحجب
            if (! $viewing || (int) $viewing->property_id !== $propertyId) {
                $this->ensurePropertyCanBeViewed($property, $index, $client);
            }

            if ($viewing) {
                $previous = $viewing->outcome;
                $previousPropertyId = (int) $viewing->property_id;
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

                // انتقلت المعاينة لعقار آخر: يُحرَّر القديم كأنها حُذفت منه، ثم تُطبَّق على الجديد كأنها جديدة
                if ($previousPropertyId !== $propertyId) {
                    $old = Property::query()->with('status')->find($previousPropertyId);

                    if ($old) {
                        $viewing->setRelation('client', $client)->setRelation('property', $old);
                        $this->sync->apply($viewing, $previous, deleted: true);
                    }

                    $previous = null;
                }
            } else {
                $previous = null;
                $viewing = $client->viewings()->create($attributes + [
                    'created_by' => auth()->id(),
                    'outcome_at' => $outcome === ClientViewing::OUTCOME_PENDING ? null : now(),
                ]);

                $this->audit->record($client, 'viewing_added', $viewing, $this->audit->snapshot($viewing, $fields));
            }

            // أثر النتيجة على العميل والعقار (ربح / مباع / متاح)
            $viewing->setRelation('client', $client)->setRelation('property', $property);
            $this->sync->apply($viewing, $previous);

            $kept[] = $viewing->id;
        }

        foreach ($existing->except($kept) as $viewing) {
            /** @var ClientViewing $viewing */
            $this->audit->record($client, 'viewing_removed', $viewing, $this->audit->snapshot($viewing, $fields, removed: true));
            $viewing->delete();

            // حذف معاينة مختارة يحرّر عقار البيع
            $viewing->setRelation('client', $client);
            $this->sync->apply($viewing, $viewing->outcome, deleted: true);
        }

        $client->unsetRelation('viewings');
    }

    /** لا معاينة لعقار مباع، ولا لعقار مشغول اختاره عميل آخر بالفعل */
    private function ensurePropertyCanBeViewed(Property $property, int $index, Client $client): void
    {
        if ($property->status?->key === 'sold') {
            throw ValidationException::withMessages([
                "viewings.{$index}.property_id" => 'لا يمكن جدولة معاينة لعقار مباع.',
            ]);
        }

        $busy = PropertyLookup::busyViewings($property->busyViewings(), $client->id)->first();

        if ($busy) {
            throw ValidationException::withMessages([
                "viewings.{$index}.property_id" => 'العقار مشغول: '.PropertyLookup::busyText($busy).'.',
            ]);
        }
    }
}
