<?php

namespace App\Support;

use App\Models\FieldOwner;
use Illuminate\Support\Collection;
use Illuminate\Support\ViewErrorBag;

/**
 * تجهيز حالة فورم الزيارة الميدانية للواجهة (Alpine): سطور المسؤولين
 * (من old() أو الموديل)، أخطاء السطور، القوائم المترابطة، والموقع.
 */
final class FieldOwnerFormData
{
    public static function state(FieldOwner $record, ViewErrorBag $errors, Collection $areas, array $countries): array
    {
        return [
            'rows' => self::rows($record),
            'errors' => self::errorMap($errors),
            'countries' => $countries,
            'city' => (string) old('city_id', $record->city_id ?? ''),
            'area' => (string) old('area_id', $record->area_id ?? ''),
            'areas' => $areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'city_id' => $a->city_id])->values()->all(),
            'lat' => (string) old('latitude', $record->latitude ?? ''),
            'lng' => (string) old('longitude', $record->longitude ?? ''),
        ];
    }

    private static function blankRow(): array
    {
        return ['id' => '', 'phone_code' => PhoneCountries::DEFAULT, 'phone' => '', 'role' => '', 'name' => ''];
    }

    /** السطور: القيم المُدخلة بعد فشل التحقق، وإلا سطور الموديل، وإلا سطر فارغ */
    private static function rows(FieldOwner $record): array
    {
        $old = old('contacts');

        if (is_array($old)) {
            $rows = array_values(array_map(fn ($row) => [
                'id' => (string) ($row['id'] ?? ''),
                'phone_code' => (string) ($row['phone_code'] ?? PhoneCountries::DEFAULT),
                'phone' => (string) ($row['phone'] ?? ''),
                'role' => (string) ($row['role'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ], $old));
        } elseif ($record->exists) {
            $rows = $record->contacts->map(fn ($c) => [
                'id' => (string) $c->id,
                'phone_code' => $c->phone_code ?: PhoneCountries::DEFAULT,
                'phone' => (string) $c->phone,
                'role' => (string) $c->role,
                'name' => (string) $c->name,
            ])->values()->all();
        } else {
            $rows = [];
        }

        return $rows ?: [self::blankRow()];
    }

    /** أخطاء سطور المسؤولين بصيغة {"contacts.0.phone": "..."} */
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
