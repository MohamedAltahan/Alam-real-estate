<?php

namespace App\Services;

use App\Models\Area;
use App\Models\FieldOwner;
use App\Models\FieldOwnerContact;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\User;
use App\Support\PhoneCountries;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * منطق شاشة «ميداني»: الفلاتر، المسؤولون المتعددون، الصور،
 * والتحويل إلى مالك حقيقي (شاشة الملاك) وعقار حقيقي (شاشة العقارات).
 */
class FieldOwnerService
{
    /** مفاتيح فلاتر القائمة */
    public const FILTER_KEYS = [
        'search', 'stage', 'contact_method', 'city_id', 'area_id', 'created_by', 'from', 'to',
    ];

    public function __construct(private PropertyService $properties) {}

    private function filtered(array $filters = []): Builder
    {
        return FieldOwner::query()
            ->when($filters['search'] ?? null, fn (Builder $q, $search) => $this->applySearch($q, (string) $search))
            ->when($filters['stage'] ?? null, fn (Builder $q, $v) => $q->where('stage', $v))
            ->when($filters['contact_method'] ?? null, fn (Builder $q, $v) => $q->where('contact_method', $v))
            ->when($filters['city_id'] ?? null, fn (Builder $q, $v) => $q->where('city_id', $v))
            ->when($filters['area_id'] ?? null, fn (Builder $q, $v) => $q->where('area_id', $v))
            ->when($filters['created_by'] ?? null, fn (Builder $q, $v) => $q->where('created_by', $v))
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()));
    }

    /** بحث عام: اسم المسؤول، رقم العقار، وأي رقم هاتف */
    private function applySearch(Builder $query, string $search): void
    {
        $term = '%'.mb_strtolower(trim($search)).'%';
        $digits = preg_replace('/\D+/', '', $search) ?? '';

        $query->where(function (Builder $q) use ($term, $digits, $search) {
            $q->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(property_number) LIKE ?', [$term])
                ->orWhereHas('contacts', fn (Builder $c) => $c->whereRaw('LOWER(name) LIKE ?', [$term]));

            if ($digits === '') {
                return;
            }

            $numbers = [$digits];

            $withCode = str_starts_with(trim($search), '+')
                || str_starts_with($digits, '00')
                || (strlen($digits) === 11 && str_starts_with($digits, '965'));

            if ($withCode) {
                $national = PhoneNumber::split($search)['national'];
                if ($national !== '' && $national !== $digits) {
                    $numbers[] = $national;
                }
            }

            foreach ($numbers as $number) {
                $q->orWhere('phone', 'like', "%{$number}%")
                    ->orWhereHas('contacts', fn (Builder $c) => $c->where('phone', 'like', "%{$number}%"));
            }
        });
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(['city', 'area', 'creator', 'contacts', 'media', 'convertedOwner', 'convertedProperty'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /** عدد الزيارات لكل مرحلة (بنفس الفلاتر عدا المرحلة) */
    public function stageCounts(array $filters = []): array
    {
        $base = $this->filtered(array_diff_key($filters, ['stage' => null]));

        return [
            'total' => (clone $base)->count(),
            'stages' => (clone $base)
                ->selectRaw('stage, COUNT(*) as aggregate')
                ->groupBy('stage')
                ->pluck('aggregate', 'stage')
                ->all(),
        ];
    }

    /** المندوبون الذين سجّلوا زيارات (لفلتر القائمة) */
    public function reps(): Collection
    {
        return User::query()
            ->whereIn('id', FieldOwner::query()->whereNotNull('created_by')->select('created_by'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(array $data, Request $request, int $userId): FieldOwner
    {
        return DB::transaction(function () use ($data, $request, $userId) {
            [$contacts, $data] = $this->extractContacts($data);

            $record = FieldOwner::create($data + $this->primary($contacts) + ['created_by' => $userId]);

            $this->syncContacts($record, $contacts);
            $this->syncPhotos($request, $record);

            return $record;
        });
    }

    public function update(FieldOwner $record, array $data, Request $request): FieldOwner
    {
        return DB::transaction(function () use ($record, $data, $request) {
            [$contacts, $data] = $this->extractContacts($data);

            $record->update($data + $this->primary($contacts));

            $this->syncContacts($record, $contacts);
            $this->syncPhotos($request, $record);

            return $record;
        });
    }

    /** @return array{0: array, 1: array} */
    private function extractContacts(array $data): array
    {
        $contacts = array_values((array) ($data['contacts'] ?? []));
        unset($data['contacts'], $data['photos'], $data['photos_removed']);

        // المحافظة تُستنتج من المنطقة عند تركها فارغة
        if (empty($data['city_id']) && ! empty($data['area_id'])) {
            $data['city_id'] = Area::whereKey($data['area_id'])->value('city_id');
        }

        return [$contacts, $data];
    }

    /** أول مسؤول يُنسخ على السجل نفسه (للقائمة والبحث والتحويل) */
    private function primary(array $contacts): array
    {
        $first = $contacts[0] ?? [];

        return [
            'name' => trim((string) ($first['name'] ?? '')),
            'phone_code' => $first['phone_code'] ?? PhoneCountries::DEFAULT,
            'phone' => (string) ($first['phone'] ?? ''),
        ];
    }

    /** مزامنة المسؤولين: تعديل الموجود بالمعرّف، إضافة الجديد، حذف المحذوف */
    private function syncContacts(FieldOwner $record, array $rows): void
    {
        $existing = $record->contacts()->get()->keyBy('id');
        $kept = [];

        foreach ($rows as $index => $row) {
            $attributes = [
                'name' => filled($row['name'] ?? null) ? trim($row['name']) : null,
                'role' => filled($row['role'] ?? null) ? trim($row['role']) : null,
                'phone_code' => $row['phone_code'] ?: PhoneCountries::DEFAULT,
                'phone' => (string) $row['phone'],
                'sort_order' => $index,
            ];

            $contact = ! empty($row['id']) ? $existing->get((int) $row['id']) : null;

            if ($contact) {
                $contact->fill($attributes)->save();
            } else {
                $contact = $record->contacts()->create($attributes);
            }

            $kept[] = $contact->id;
        }

        foreach ($existing->except($kept) as $contact) {
            /** @var FieldOwnerContact $contact */
            $contact->delete();
        }

        $record->unsetRelation('contacts');
    }

    /** صور العقار: حذف المحدد للحذف ثم إضافة الجديد */
    private function syncPhotos(Request $request, FieldOwner $record): void
    {
        foreach ((array) $request->input('photos_removed', []) as $id) {
            $record->media()->where('id', (int) $id)->first()?->delete();
        }

        foreach ((array) $request->file('photos', []) as $file) {
            if ($file) {
                $record->addMedia($file)->toMediaCollection(FieldOwner::PHOTOS);
            }
        }
    }

    // ===== التحويل =====

    /** مالك مسجَّل بنفس رقم أول مسؤول — للتحذير قبل التحويل بدل إنشاء تكرار */
    public function findDuplicateOwner(FieldOwner $record): ?PropertyOwner
    {
        $phone = (string) $record->phone;

        if ($phone === '') {
            return null;
        }

        return PropertyOwner::query()
            ->where(function (Builder $q) use ($phone) {
                $q->where('phone', $phone)
                    ->orWhereHas('contacts', fn (Builder $c) => $c->where('phone', $phone));
            })
            ->first();
    }

    /**
     * حفظ الزيارة كمالك حقيقي في شاشة الملاك (أو ربطها بمالك موجود).
     * لو الزيارة محوَّلة بالفعل يُعاد المالك نفسه بلا تغيير.
     */
    public function convertToOwner(FieldOwner $record, ?int $existingOwnerId = null): PropertyOwner
    {
        return DB::transaction(function () use ($record, $existingOwnerId) {
            if ($record->converted_owner_id) {
                return $record->convertedOwner()->firstOrFail();
            }

            $owner = $existingOwnerId
                ? PropertyOwner::findOrFail($existingOwnerId)
                : $this->createOwnerFrom($record);

            $record->forceFill(['converted_owner_id' => $owner->id])->save();
            $record->setRelation('convertedOwner', $owner);

            return $owner;
        });
    }

    private function createOwnerFrom(FieldOwner $record): PropertyOwner
    {
        $owner = PropertyOwner::create([
            'name' => $record->name,
            'phone_code' => $record->phone_code ?: PhoneCountries::DEFAULT,
            'phone' => $record->phone,
            'area_id' => $record->area_id,
            'registered_address' => $record->address,
            'notes' => collect(['محوَّل من زيارة ميدانية #'.$record->id, $record->notes])->filter()->implode("\n"),
        ]);

        foreach ($record->contacts()->get() as $index => $contact) {
            $owner->contacts()->create([
                'phone_code' => $contact->phone_code ?: PhoneCountries::DEFAULT,
                'phone' => $contact->phone,
                'role' => $contact->role,
                'name' => $contact->name,
                'sort_order' => $index,
            ]);
        }

        return $owner;
    }

    /** القيم الجاهزة لفورم إضافة عقار من الزيارة (تُهيَّأ على Property غير محفوظ) */
    public function propertyPrefill(FieldOwner $record): array
    {
        $record->loadMissing('creator');

        $prefill = [
            'owner_id' => $record->converted_owner_id,
            'city_id' => $record->city_id,
            'area_id' => $record->area_id,
            'street' => $record->address,
            'latitude' => $record->latitude,
            'longitude' => $record->longitude,
            'map_url' => $record->mapsUrl(),
            'agent_id' => $record->creator?->is_agent ? $record->created_by : null,
        ];

        if ($record->property_number) {
            $prefill['title'] = ['ar' => 'عقار '.$record->property_number];
        }

        return $prefill;
    }

    /**
     * إنشاء عقار حقيقي من الزيارة: المالك (تلقائيًا إن لم يُحوَّل بعد، أو المختار يدويًا)
     * ثم العقار، ثم نسخ صور الزيارة إلى معرض العقار، ثم ربط الزيارة بالعقار.
     */
    public function createProperty(FieldOwner $record, array $data, array $amenityIds = []): Property
    {
        if ($record->isPropertyConverted()) {
            throw ValidationException::withMessages(['field_owner_id' => 'هذه الزيارة محوَّلة بالفعل إلى عقار.']);
        }

        return DB::transaction(function () use ($record, $data, $amenityIds) {
            $ownerId = (int) ($data['owner_id'] ?? 0);

            if ($ownerId > 0) {
                if (! $record->converted_owner_id) {
                    $record->forceFill(['converted_owner_id' => $ownerId])->save();
                }
            } else {
                $ownerId = (int) $this->convertToOwner($record)->id;
            }

            $property = $this->properties->create(['owner_id' => $ownerId] + $data, $amenityIds);

            foreach ($record->getMedia(FieldOwner::PHOTOS) as $media) {
                $media->copy($property, 'gallery');
            }

            $record->forceFill(['converted_property_id' => $property->id])->save();

            return $property;
        });
    }
}
