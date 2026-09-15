<?php

namespace App\Support;

use App\Models\Property;

/** بحث العقارات بالرقم المرجعي أو العنوان لحقول الاختيار (معاينات العملاء · المهام) */
final class PropertyLookup
{
    /**
     * @param  bool  $flagSold  تعليم العقار المباع كمحجوب عن الاختيار
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $q, bool $flagSold = true, int $limit = 20): array
    {
        $q = trim($q);
        $term = '%'.mb_strtolower($q).'%';

        $properties = Property::query()
            ->with(['status', 'area'])
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($term) {
                $w->whereRaw('LOWER(reference_code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(title) LIKE ?', [$term]);
            }))
            ->latest()
            ->limit($limit)
            ->get();

        return $properties->map(function (Property $property) use ($flagSold) {
            $statusKey = $property->status?->key;

            return [
                'id' => $property->id,
                'reference_code' => $property->reference_code,
                'title' => $property->title,
                'label' => ClientFormData::propertyLabel($property),
                'area' => $property->area?->name,
                'status' => $property->status?->name,
                'status_key' => $statusKey,
                'purpose' => $property->purpose,
                'blocked' => $flagSold && $statusKey === 'sold' ? 'مباع' : null,
            ];
        })->values()->all();
    }
}
