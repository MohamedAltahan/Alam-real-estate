<?php

namespace App\Support;

use App\Models\Property;
use App\Models\PropertyOwner;
use Illuminate\Support\ViewErrorBag;

/**
 * تجهيز بيانات فورم مالك العقار للواجهة (Alpine): بيانات التعديل،
 * إعادة فتح المودال بقيم old() بعد فشل التحقق، وأخطاء أسطر أرقام التواصل.
 */
final class OwnerFormData
{
    /** بيانات المالك للتعديل (startEdit) */
    public static function editPayload(PropertyOwner $owner): array
    {
        return [
            'id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'area_id' => $owner->area_id,
            'registered_address' => $owner->registered_address,
            'notes' => $owner->notes,
            'contacts' => $owner->contacts->map(fn ($c) => [
                'id' => $c->id,
                'phone_code' => $c->phone_code ?: PhoneCountries::DEFAULT,
                'phone' => $c->phone,
                'role' => $c->role,
                'name' => $c->name,
            ])->values()->all(),
            'files' => $owner->filePayload(),
        ];
    }

    /** ملخص عقار المالك للمودال (صورة + بيانات أساسية) */
    public static function propertyPayload(Property $property): array
    {
        $user = auth()->user();

        return [
            'id' => $property->id,
            'reference' => $property->reference_code,
            'title' => $property->title,
            'cover' => $property->cover_url,
            'type' => $property->unitType?->name,
            'area' => $property->area?->name,
            'city' => $property->area?->city?->name,
            'purpose' => $property->purpose === 'rent'
                ? 'إيجار / '.($property->price_period === 'yearly' ? 'سنة' : 'شهر')
                : 'بيع',
            'price' => number_format((float) $property->price).' '.($user?->currencySymbol() ?? 'د.ك'),
            'status' => $property->status?->name,
            'status_color' => $property->status?->color ?: '#6B7280',
            'bedrooms' => $property->bedrooms,
            'bathrooms' => $property->bathrooms,
            'area_size' => $property->area_size ? rtrim(rtrim((string) $property->area_size, '0'), '.') : null,
            'agent' => $property->agent?->name,
            'url' => $user?->can('properties.view') ? route('dashboard.properties.show', $property) : null,
        ];
    }

    /** بعد فشل التحقق: القيم المُدخلة لإعادة فتح المودال (أو null) */
    public static function reopen(ViewErrorBag $errors): ?array
    {
        if (! $errors->any() || old('contacts') === null && old('name') === null) {
            return null;
        }

        $contacts = array_values(array_map(fn ($row) => [
            'id' => (string) ($row['id'] ?? ''),
            'phone_code' => (string) ($row['phone_code'] ?? PhoneCountries::DEFAULT),
            'phone' => (string) ($row['phone'] ?? ''),
            'role' => (string) ($row['role'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
        ], (array) old('contacts', [])));

        $ownerId = old('owner_id');
        $owner = $ownerId ? PropertyOwner::find($ownerId) : null;

        return [
            'mode' => $owner ? 'edit' : 'add',
            'id' => $owner?->id,
            'form' => [
                'name' => (string) old('name', ''),
                'email' => (string) old('email', ''),
                'area_id' => (string) old('area_id', ''),
                'registered_address' => (string) old('registered_address', ''),
                'notes' => (string) old('notes', ''),
            ],
            'contacts' => $contacts,
            'files' => $owner?->filePayload() ?? [],
        ];
    }

    /** أخطاء أسطر أرقام التواصل بصيغة {"contacts.0.phone": "..."} */
    public static function errorMap(ViewErrorBag $errors): array
    {
        $map = [];

        foreach ($errors->getBag('default')->toArray() as $key => $messages) {
            if (str_starts_with($key, 'contacts.')) {
                $map[$key] = $messages[0] ?? '';
            }
        }

        return $map;
    }
}
