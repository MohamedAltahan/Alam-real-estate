<?php

namespace App\Support;

use App\Models\Property;

/** بحث العقارات بالرقم المرجعي أو العنوان لحقول الاختيار (معاينات العملاء · المهام) */
final class PropertyLookup
{
    /**
     * كل العقارات قابلة للاختيار؛ الحالة غير «متاح» تظهر كشارة فقط (مباع / قيد التدقيق…) ولا تمنع الاختيار.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $q, int $limit = 20): array
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

        return $properties->map(function (Property $property) {
            $statusKey = $property->status?->key;

            return [
                'id' => $property->id,
                'reference_code' => $property->reference_code,
                'building' => $property->buildingLabel(),
                'title' => $property->title,
                'label' => ClientFormData::propertyLabel($property),
                'area' => $property->area?->name,
                'status' => $property->status?->name,
                'status_key' => $statusKey,
                'purpose' => $property->purpose,
                'badge' => $statusKey && $statusKey !== 'available' ? (string) $property->status?->name : null,
                'badge_color' => $property->status?->color,
            ];
        })->values()->all();
    }
}
