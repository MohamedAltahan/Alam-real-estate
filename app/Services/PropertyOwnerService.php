<?php

namespace App\Services;

use App\Models\PropertyOwner;
use App\Models\PropertyOwnerContact;
use App\Support\FullTextQuery;
use App\Support\PhoneCountries;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * منطق ملّاك العقارات: الفلاتر، أرقام التواصل المتعددة، وملفات المالك.
 */
class PropertyOwnerService
{
    /** مفاتيح فلاتر القائمة */
    public const FILTER_KEYS = [
        'search', 'area_id', 'city_id', 'agent_id', 'has_properties', 'from', 'to', 'notes_q',
    ];

    private function filtered(array $filters = []): Builder
    {
        return PropertyOwner::query()
            ->when($filters['search'] ?? null, fn (Builder $q, $search) => $this->applySearch($q, (string) $search))
            ->when($filters['area_id'] ?? null, fn (Builder $q, $v) => $q->where('area_id', $v))
            ->when($filters['city_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('area', fn (Builder $a) => $a->where('city_id', $v)))
            ->when($filters['agent_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('properties', fn (Builder $p) => $p->where('agent_id', $v)))
            ->when(
                array_key_exists('has_properties', $filters) && in_array((string) $filters['has_properties'], ['0', '1'], true),
                fn (Builder $q) => (string) $filters['has_properties'] === '1' ? $q->has('properties') : $q->doesntHave('properties')
            )
            ->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->where('created_at', '<=', Carbon::parse($v)->endOfDay()))
            ->when($this->notesTerm($filters), fn (Builder $q, $term) => $this->applyNotesSearch($q, $term));
    }

    /** بحث عام: الاسم والبريد (بدون حساسية لحالة الأحرف) وأي رقم من أرقام التواصل */
    private function applySearch(Builder $query, string $search): void
    {
        $term = '%'.mb_strtolower(trim($search)).'%';
        $digits = preg_replace('/\D+/', '', $search) ?? '';

        $query->where(function (Builder $q) use ($term, $digits, $search) {
            $q->whereRaw('LOWER(name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(email) LIKE ?', [$term])
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

    private function notesTerm(array $filters): ?string
    {
        $term = trim((string) ($filters['notes_q'] ?? ''));

        return mb_strlen($term) >= (int) config('clients.notes_min_length', 3) ? $term : null;
    }

    /** البحث في الملاحظات: فهرس FULLTEXT على MariaDB/MySQL، وإلا LIKE */
    private function applyNotesSearch(Builder $query, string $term): void
    {
        $driver = $query->getConnection()->getDriverName();
        $boolean = in_array($driver, ['mysql', 'mariadb'], true) && config('clients.notes_fulltext', true)
            ? FullTextQuery::boolean($term)
            : '';

        if ($boolean !== '') {
            $query->whereFullText(['notes'], $boolean, ['mode' => 'boolean']);
        } else {
            $query->whereRaw('LOWER(notes) LIKE ?', ['%'.mb_strtolower($term).'%']);
        }
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with([
                'area.city', 'contacts', 'latestProperty.agent', 'media',
                'properties.area.city', 'properties.status', 'properties.unitType',
                'properties.agent', 'properties.media',
            ])
            ->withCount('properties')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data, Request $request): PropertyOwner
    {
        return DB::transaction(function () use ($data, $request) {
            [$contacts, $data] = $this->extractContacts($data);

            $owner = PropertyOwner::create($data + $this->primaryPhone($contacts));

            $this->syncContacts($owner, $contacts);
            $this->syncFiles($request, $owner);

            return $owner;
        });
    }

    public function update(PropertyOwner $owner, array $data, Request $request): PropertyOwner
    {
        return DB::transaction(function () use ($owner, $data, $request) {
            [$contacts, $data] = $this->extractContacts($data);

            $owner->update($data + $this->primaryPhone($contacts));

            $this->syncContacts($owner, $contacts);
            $this->syncFiles($request, $owner);

            return $owner;
        });
    }

    /** @return array{0: array, 1: array} */
    private function extractContacts(array $data): array
    {
        $contacts = array_values((array) ($data['contacts'] ?? []));
        unset($data['contacts'], $data['files'], $data['files_removed']);

        return [$contacts, $data];
    }

    /** أول رقم تواصل يُنسخ على المالك نفسه (للقائمة والبحث وواتساب) */
    private function primaryPhone(array $contacts): array
    {
        $first = $contacts[0] ?? null;

        return [
            'phone_code' => $first['phone_code'] ?? PhoneCountries::DEFAULT,
            'phone' => $first['phone'] ?? '',
        ];
    }

    /** مزامنة أرقام التواصل: تعديل الموجود بالمعرّف، إضافة الجديد، حذف المحذوف */
    private function syncContacts(PropertyOwner $owner, array $rows): void
    {
        $existing = $owner->contacts()->get()->keyBy('id');
        $kept = [];

        foreach ($rows as $index => $row) {
            $attributes = [
                'phone_code' => $row['phone_code'] ?: PhoneCountries::DEFAULT,
                'phone' => (string) $row['phone'],
                'role' => filled($row['role'] ?? null) ? trim($row['role']) : null,
                'name' => filled($row['name'] ?? null) ? trim($row['name']) : null,
                'sort_order' => $index,
            ];

            $contact = ! empty($row['id']) ? $existing->get((int) $row['id']) : null;

            if ($contact) {
                $contact->fill($attributes)->save();
            } else {
                $contact = $owner->contacts()->create($attributes);
            }

            $kept[] = $contact->id;
        }

        foreach ($existing->except($kept) as $contact) {
            /** @var PropertyOwnerContact $contact */
            $contact->delete();
        }

        $owner->unsetRelation('contacts');
    }

    /** ملفات المالك: حذف المحدد للحذف ثم إضافة الملفات الجديدة */
    private function syncFiles(Request $request, PropertyOwner $owner): void
    {
        foreach ((array) $request->input('files_removed', []) as $id) {
            $owner->media()->where('id', (int) $id)->first()?->delete();
        }

        foreach ((array) $request->file('files', []) as $file) {
            if ($file) {
                $owner->addMedia($file)->toMediaCollection(PropertyOwner::FILES);
            }
        }
    }
}
