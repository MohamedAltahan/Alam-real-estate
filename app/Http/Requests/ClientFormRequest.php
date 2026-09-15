<?php

namespace App\Http\Requests;

use App\Models\Area;
use App\Models\Client;
use App\Models\UnitType;
use App\Support\ClientFields;
use App\Support\PhoneCountries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** قواعد فورم العميل (إضافة/تعديل) — الاختلاف الوحيد بينهما هو الصلاحية. */
abstract class ClientFormRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_code' => $this->input('phone_code') ?: PhoneCountries::DEFAULT,
            'phone' => ltrim(preg_replace('/\D+/', '', (string) $this->input('phone')) ?? '', '0'),
            'needs' => $this->rows('needs', ['unit_type_id', 'city_id', 'area_id', 'category', 'area_size', 'rooms']),
            'viewings' => $this->rows('viewings', ['property_id', 'scheduled_at', 'notes']),
        ]);
    }

    /** الأسطر الفارغة بالكامل تُهمل ويُعاد ترقيم الباقي */
    private function rows(string $key, array $meaningful): array
    {
        $rows = $this->input($key);

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, function ($row) use ($meaningful) {
            if (! is_array($row)) {
                return false;
            }

            foreach ($meaningful as $field) {
                if (filled($row[$field] ?? null)) {
                    return true;
                }
            }

            return false;
        }));
    }

    public function rules(): array
    {
        $clientId = $this->route('client')?->id ?? 0;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_code' => ['required', Rule::in(PhoneCountries::codes())],
            'phone' => ['required', 'string', 'regex:/^[0-9]{4,15}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'preferred_contact' => ['nullable', Rule::in(array_keys(ClientFields::CONTACT_METHODS))],
            'nationality' => ['nullable', 'string', 'max:120'],
            'social_status' => ['nullable', Rule::in(array_keys(ClientFields::SOCIAL_STATUSES))],
            'household_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'workplace' => ['nullable', 'string', 'max:255'],
            'stage_id' => ['nullable', 'exists:client_stages,id'],
            'agent_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_featured' => ['nullable', 'boolean'],

            'needs' => ['nullable', 'array', 'max:20'],
            'needs.*.id' => ['nullable', 'integer', Rule::exists('client_property_needs', 'id')->where('client_id', $clientId)],
            'needs.*.category' => ['nullable', Rule::in(array_keys(UnitType::CATEGORIES))],
            'needs.*.unit_type_id' => ['nullable', 'exists:unit_types,id'],
            'needs.*.city_id' => ['nullable', 'exists:cities,id'],
            'needs.*.area_id' => ['nullable', 'exists:areas,id'],
            'needs.*.area_size' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'needs.*.rooms' => ['nullable', 'integer', 'min:0', 'max:200'],

            'viewings' => ['nullable', 'array', 'max:50'],
            'viewings.*.id' => ['nullable', 'integer', Rule::exists('client_viewings', 'id')->where('client_id', $clientId)],
            'viewings.*.property_id' => ['required', 'exists:properties,id'],
            'viewings.*.scheduled_at' => ['required', 'date_format:Y-m-d H:i'],
            'viewings.*.in_person' => ['nullable', 'boolean'],
            'viewings.*.outcome' => ['nullable', Rule::in(array_keys(ClientFields::OUTCOMES))],
            'viewings.*.notes' => ['nullable', 'string', 'max:2000'],

            'files' => ['nullable', 'array', 'max:30'],
            'files.*' => ['file', 'mimes:'.implode(',', Client::FILE_EXTENSIONS), 'max:'.Client::MAX_FILE_KB],
            'files_removed' => ['nullable', 'array'],
            'files_removed.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('needs', []) as $index => $row) {
                if (filled($row['city_id'] ?? null) && filled($row['area_id'] ?? null)) {
                    $cityId = Area::whereKey($row['area_id'])->value('city_id');

                    if ($cityId && (int) $cityId !== (int) $row['city_id']) {
                        $validator->errors()->add("needs.{$index}.area_id", 'المنطقة المختارة لا تتبع هذه المحافظة.');
                    }
                }

                // نوع الوحدة يتبع نوع العقار (سكني/تجاري) المختار في السطر نفسه
                if (filled($row['category'] ?? null) && filled($row['unit_type_id'] ?? null)) {
                    $category = UnitType::whereKey($row['unit_type_id'])->value('category');

                    if ($category && $category !== $row['category']) {
                        $validator->errors()->add("needs.{$index}.unit_type_id", 'نوع الوحدة لا يتبع نوع العقار المختار.');
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'رقم الهاتف يجب أن يكون أرقاماً فقط (من 4 إلى 15 رقماً).',
            'viewings.*.property_id.required' => 'اختر العقار لكل سطر معاينة.',
            'viewings.*.scheduled_at.required' => 'حدّد موعد المعاينة.',
            'viewings.*.scheduled_at.date_format' => 'صيغة موعد المعاينة غير صحيحة.',
            'files.*.mimes' => 'الملفات المسموحة: صور، PDF، Word، Excel.',
            'files.*.max' => 'حجم الملف يجب ألا يتجاوز 15 ميجابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم',
            'phone_code' => 'مفتاح الدولة',
            'phone' => 'رقم الهاتف',
            'email' => 'البريد الإلكتروني',
            'preferred_contact' => 'طريقة التواصل',
            'nationality' => 'الجنسية',
            'social_status' => 'الحالة الاجتماعية',
            'household_size' => 'عدد الأفراد',
            'workplace' => 'مكان العمل',
            'stage_id' => 'الحالة',
            'agent_id' => 'مندوب المبيعات',
            'notes' => 'الملاحظات',
            'is_featured' => 'طلب مميز',
            'needs.*.category' => 'نوع العقار',
            'needs.*.unit_type_id' => 'نوع الوحدة',
            'needs.*.city_id' => 'المحافظة',
            'needs.*.area_id' => 'المنطقة',
            'needs.*.area_size' => 'المساحة',
            'needs.*.rooms' => 'عدد الغرف',
            'viewings.*.property_id' => 'العقار',
            'viewings.*.scheduled_at' => 'موعد المعاينة',
            'viewings.*.in_person' => 'حضوري',
            'viewings.*.outcome' => 'النتيجة',
            'viewings.*.notes' => 'ملاحظة المعاينة',
            'files' => 'ملفات العميل',
            'files.*' => 'ملف العميل',
        ];
    }
}
