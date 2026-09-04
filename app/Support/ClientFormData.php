<?php

namespace App\Support;

use App\Models\Area;
use App\Models\City;
use App\Models\Client;
use App\Models\ClientPropertyNeed;
use App\Models\ClientViewing;
use App\Models\Property;
use App\Models\UnitType;
use Illuminate\Support\ViewErrorBag;

/**
 * تجهيز بيانات فورم العميل للواجهة (Alpine): أسطر الاحتياجات والمعاينات
 * مع مراعاة old() بعد فشل التحقق، وقوائم المدن/المناطق/الأنواع ومفاتيح الدول.
 */
final class ClientFormData
{
    /** @return array<string, mixed> */
    public static function for(?Client $client): array
    {
        return [
            'needs' => self::needRows($client),
            'viewings' => self::viewingRows($client),
            'cities' => City::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (City $city) => ['id' => $city->id, 'name' => $city->name])->values()->all(),
            'areas' => Area::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (Area $area) => ['id' => $area->id, 'name' => $area->name, 'city_id' => $area->city_id])->values()->all(),
            'unitTypes' => UnitType::where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (UnitType $type) => ['id' => $type->id, 'name' => $type->name])->values()->all(),
            'countries' => PhoneCountries::all(),
            'lookupUrl' => route('dashboard.clients.property-lookup'),
        ];
    }

    /** @return array<int, array<string, string>> */
    public static function needRows(?Client $client): array
    {
        $old = old('needs');

        if (is_array($old)) {
            return array_values(array_map(fn ($row) => [
                'id' => (string) ($row['id'] ?? ''),
                'unit_type_id' => (string) ($row['unit_type_id'] ?? ''),
                'city_id' => (string) ($row['city_id'] ?? ''),
                'area_id' => (string) ($row['area_id'] ?? ''),
            ], $old));
        }

        return $client?->needs->map(fn (ClientPropertyNeed $need) => [
            'id' => (string) $need->id,
            'unit_type_id' => (string) ($need->unit_type_id ?? ''),
            'city_id' => (string) ($need->city_id ?? ''),
            'area_id' => (string) ($need->area_id ?? ''),
        ])->values()->all() ?? [];
    }

    /** @return array<int, array<string, string>> */
    public static function viewingRows(?Client $client): array
    {
        $old = old('viewings');

        if (is_array($old)) {
            $ids = collect($old)->pluck('property_id')->filter()->unique()->values();
            $labels = $ids->isEmpty()
                ? collect()
                : Property::whereIn('id', $ids)->get(['id', 'reference_code', 'title'])
                    ->mapWithKeys(fn (Property $p) => [$p->id => self::propertyLabel($p)]);

            return array_values(array_map(fn ($row) => [
                'id' => (string) ($row['id'] ?? ''),
                'property_id' => (string) ($row['property_id'] ?? ''),
                'property_label' => $labels[(int) ($row['property_id'] ?? 0)] ?? '',
                'scheduled_at' => (string) ($row['scheduled_at'] ?? ''),
                'in_person' => (string) ($row['in_person'] ?? '1'),
                'outcome' => (string) ($row['outcome'] ?? 'pending'),
                'notes' => (string) ($row['notes'] ?? ''),
            ], $old));
        }

        return $client?->viewings->map(fn (ClientViewing $viewing) => [
            'id' => (string) $viewing->id,
            'property_id' => (string) $viewing->property_id,
            'property_label' => $viewing->property ? self::propertyLabel($viewing->property) : '',
            'scheduled_at' => $viewing->scheduled_at?->format('Y-m-d H:i') ?? '',
            'in_person' => $viewing->in_person ? '1' : '0',
            'outcome' => $viewing->outcome,
            'notes' => (string) ($viewing->notes ?? ''),
        ])->values()->all() ?? [];
    }

    public static function propertyLabel(Property $property): string
    {
        return trim(($property->reference_code ?: '#'.$property->id).($property->title ? ' — '.$property->title : ''));
    }

    /** أخطاء الأسطر والهاتف فقط، بصيغة {"needs.0.area_id": "..."} لعرضها داخل Alpine */
    public static function errorMap(ViewErrorBag $errors): array
    {
        $map = [];

        foreach ($errors->getBag('default')->toArray() as $key => $messages) {
            if (str_starts_with($key, 'needs.') || str_starts_with($key, 'viewings.') || in_array($key, ['phone', 'phone_code'], true)) {
                $map[$key] = $messages[0] ?? '';
            }
        }

        return $map;
    }

    /** هل يجب فتح مودال التعديل تلقائياً بسبب أخطاء في الفورم؟ */
    public static function hasFormErrors(ViewErrorBag $errors): bool
    {
        $fields = ['name', 'phone', 'phone_code', 'email', 'preferred_contact', 'nationality', 'social_status', 'household_size', 'workplace', 'stage_id', 'agent_id', 'notes'];

        if ($errors->hasAny($fields)) {
            return true;
        }

        foreach ($errors->getBag('default')->keys() as $key) {
            if (str_starts_with($key, 'needs') || str_starts_with($key, 'viewings')) {
                return true;
            }
        }

        return false;
    }
}
