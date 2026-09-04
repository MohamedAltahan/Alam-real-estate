<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * منطق العقارات المشترك (API-first) — يخدم الداشبورد والـ API.
 */
class PropertyService
{
    public function paginate(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return Property::query()
            ->with(['area', 'category', 'unitType', 'status', 'agent', 'owner'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $term = '%'.mb_strtolower(trim($search)).'%';

                $query->where(function ($query) use ($term) {
                    $query->whereRaw('LOWER(reference_code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$term]);
                });
            })
            ->when($filters['status_id'] ?? null, fn ($q, $v) => $q->where('status_id', $v))
            ->when($filters['area_id'] ?? null, fn ($q, $v) => $q->where('area_id', $v))
            ->when($filters['unit_type_id'] ?? null, fn ($q, $v) => $q->where('unit_type_id', $v))
            ->when($filters['purpose'] ?? null, fn ($q, $v) => $q->where('purpose', $v))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data, array $amenityIds = []): Property
    {
        return DB::transaction(function () use ($data, $amenityIds) {
            $reservedId = PropertyStatus::where('key', 'reserved')->value('id');
            if ($reservedId && (int) ($data['status_id'] ?? 0) === (int) $reservedId) {
                throw ValidationException::withMessages([
                    'status_id' => 'يتم حجز العقار من شاشة العميل بعد إنشاء العقار.',
                ]);
            }

            $data['reference_code'] = $this->generateReferenceCode();
            $property = Property::create($data);
            $property->amenities()->sync($amenityIds);

            return $property;
        });
    }

    public function update(Property $property, array $data, array $amenityIds = []): Property
    {
        return DB::transaction(function () use ($property, $data, $amenityIds) {
            $reservedId = PropertyStatus::where('key', 'reserved')->value('id');
            $hasReservation = $property->activeReservation()->exists();

            if ($hasReservation && array_key_exists('status_id', $data) && (int) $data['status_id'] !== (int) $reservedId) {
                throw ValidationException::withMessages([
                    'status_id' => 'لا يمكن تغيير حالة العقار المحجوز. ألغِ ربط الحجز بالعميل أولاً.',
                ]);
            }

            if (! $hasReservation && $reservedId && array_key_exists('status_id', $data) && (int) $data['status_id'] === (int) $reservedId) {
                throw ValidationException::withMessages([
                    'status_id' => 'يتم حجز العقار من شاشة العميل، ولا يمكن اختيار «محجوز» يدويًا.',
                ]);
            }

            $property->update($data);
            $property->amenities()->sync($amenityIds);

            return $property;
        });
    }

    public function delete(Property $property): void
    {
        $property->delete();
    }

    /** توليد رمز مرجعي فريد ALM-### */
    public function generateReferenceCode(): string
    {
        $last = Property::orderByDesc('id')->value('id') ?? 0;

        do {
            $last++;
            $code = 'ALM-'.str_pad((string) $last, 3, '0', STR_PAD_LEFT);
        } while (Property::where('reference_code', $code)->exists());

        return $code;
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
