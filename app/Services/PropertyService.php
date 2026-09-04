<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PublishingChannel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * منطق العقارات المشترك (API-first) — يخدم الداشبورد والـ API.
 */
class PropertyService
{
    /** مفاتيح فلاتر القائمة */
    public const FILTER_KEYS = [
        'search', 'status_id', 'city_id', 'area_id', 'unit_type_id', 'purpose', 'website_id', 'social_id',
    ];

    public function paginate(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return Property::query()
            ->with(['area', 'city', 'category', 'unitType', 'status', 'agent', 'owner', 'channels'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $term = '%'.mb_strtolower(trim($search)).'%';

                $query->where(function ($query) use ($term) {
                    $query->whereRaw('LOWER(reference_code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$term]);
                });
            })
            ->when($filters['status_id'] ?? null, fn ($q, $v) => $q->where('status_id', $v))
            ->when($filters['city_id'] ?? null, fn ($q, $v) => $q->where('city_id', $v))
            ->when($filters['area_id'] ?? null, fn ($q, $v) => $q->where('area_id', $v))
            ->when($filters['unit_type_id'] ?? null, fn ($q, $v) => $q->where('unit_type_id', $v))
            ->when($filters['purpose'] ?? null, fn ($q, $v) => $q->where('purpose', $v))
            ->when($filters['website_id'] ?? null, fn ($q, $v) => $q->whereHas('channels', fn ($c) => $c->where('publishing_channels.id', $v)))
            ->when($filters['social_id'] ?? null, fn ($q, $v) => $q->whereHas('channels', fn ($c) => $c->where('publishing_channels.id', $v)))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data, array $amenityIds = []): Property
    {
        return DB::transaction(function () use ($data, $amenityIds) {
            $data['reference_code'] = $this->generateReferenceCode();
            $property = Property::create($data);
            $property->amenities()->sync($amenityIds);

            return $property;
        });
    }

    public function update(Property $property, array $data, array $amenityIds = []): Property
    {
        return DB::transaction(function () use ($property, $data, $amenityIds) {
            $property->update($data);
            $property->amenities()->sync($amenityIds);

            return $property;
        });
    }

    public function delete(Property $property): void
    {
        $property->delete();
    }

    /** توليد رقم مرجعي فريد — أرقام فقط، يكمل من آخر رقم مستخدم */
    public function generateReferenceCode(): string
    {
        $maxCode = Property::query()->pluck('reference_code')
            ->filter(fn ($code) => is_string($code) && ctype_digit($code))
            ->map(fn ($code) => (int) $code)
            ->max() ?? 0;

        $next = max((int) $maxCode, (int) (Property::max('id') ?? 0));

        do {
            $next++;
        } while (Property::where('reference_code', (string) $next)->exists());

        return (string) $next;
    }

    /**
     * ربط العقار بقنوات النشر من نوع معيّن (موقع/سوشال) مع رابط الإعلان.
     * القنوات من النوع الآخر لا تُمسّ.
     *
     * @param  array<int|string, array{on?: mixed, url?: string|null}>  $rows  مفهرسة بمعرّف القناة
     */
    public function syncChannels(Property $property, string $kind, array $rows): void
    {
        $channelIds = PublishingChannel::query()->kind($kind)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $sync = [];
        foreach ($channelIds as $id) {
            $row = $rows[$id] ?? null;
            if ($row && ! empty($row['on'])) {
                $url = trim((string) ($row['url'] ?? ''));
                $sync[$id] = ['url' => $url !== '' ? $url : null];
            }
        }

        DB::transaction(function () use ($property, $channelIds, $sync) {
            $property->channels()->detach(array_diff($channelIds, array_keys($sync)));

            foreach ($sync as $id => $pivot) {
                if ($property->channels()->where('publishing_channels.id', $id)->exists()) {
                    $property->channels()->updateExistingPivot($id, $pivot);
                } else {
                    $property->channels()->attach($id, $pivot);
                }
            }
        });

        $property->unsetRelation('channels');
    }

    /** إضافة تقييم للعقار (يُدار من الداشبورد) */
    public function addReview(Property $property, array $data): void
    {
        $property->reviews()->create([
            'reviewer_name' => $data['reviewer_name'],
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'created_by' => auth()->id(),
        ]);
        $this->refreshRating($property);
    }

    public function refreshRating(Property $property): void
    {
        $property->update([
            'reviews_count' => $property->reviews()->count(),
            'rating' => round((float) $property->reviews()->avg('rating'), 2),
        ]);
    }
}
