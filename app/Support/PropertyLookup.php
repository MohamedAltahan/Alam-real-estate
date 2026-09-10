<?php

namespace App\Support;

use App\Models\ClientViewing;
use App\Models\Property;

/** بحث العقارات بالرقم المرجعي أو العنوان لحقول الاختيار (معاينات العملاء · المهام) */
final class PropertyLookup
{
    /**
     * @param  bool  $flagBusy  تعليم العقار «مشغولاً» لو اختاره عميل آخر بالفعل (معاينة تحت المعاينة لا تشغله)
     * @param  int|null  $exceptClientId  معاينات هذا العميل لا تُحسب (شاشة تعديل عميل موجود)
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $q, bool $flagSold = true, int $limit = 20, bool $flagBusy = false, ?int $exceptClientId = null): array
    {
        $q = trim($q);
        $term = '%'.mb_strtolower($q).'%';

        $properties = Property::query()
            ->with(['status', 'area'])
            ->when($flagBusy, fn ($query) => $query->with([
                'busyViewings' => fn ($viewings) => self::busyViewings($viewings, $exceptClientId),
            ]))
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($term) {
                $w->whereRaw('LOWER(reference_code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(title) LIKE ?', [$term]);
            }))
            ->latest()
            ->limit($limit)
            ->get();

        return $properties->map(function (Property $property) use ($flagSold, $flagBusy) {
            $statusKey = $property->status?->key;
            $busy = $flagBusy ? $property->busyViewings->first() : null;

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
                'busy' => $busy ? self::busyText($busy) : null,
            ];
        })->values()->all();
    }

    /**
     * المعاينات التي تشغل العقار: يُطبَّق على Property::busyViewings() (المختارة فقط)،
     * ويستثني عميلاً بعينه، الأحدث موعداً أولاً.
     *
     * @template T of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\HasMany
     *
     * @param  T  $query
     * @return T
     */
    public static function busyViewings($query, ?int $exceptClientId = null)
    {
        return $query
            ->when($exceptClientId, fn ($q) => $q->where('client_id', '!=', $exceptClientId))
            ->with('client:id,name')
            ->latest('scheduled_at');
    }

    /** «اختاره العميل فلان · ينتهي العقد 2026-12-31» */
    public static function busyText(ClientViewing $viewing): string
    {
        $text = 'اختاره العميل '.($viewing->client?->name ?: 'آخر');

        if ($viewing->contract_ends_at) {
            $text .= ' · ينتهي العقد '.$viewing->contract_ends_at->format('Y-m-d');
        }

        return $text;
    }
}
