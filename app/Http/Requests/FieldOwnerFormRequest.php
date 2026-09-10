<?php

namespace App\Http\Requests;

use App\Models\Area;
use App\Models\FieldOwner;
use App\Support\FieldOwnerFields;
use App\Support\PhoneCountries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** فورم الزيارة الميدانية: مسؤولون متعددون + متابعة + بيانات العقار وموقعه + صور */
class FieldOwnerFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->route('fieldOwner') ? 'field_owners.edit' : 'field_owners.create';

        return (bool) $this->user()?->can($ability);
    }

    protected function prepareForValidation(): void
    {
        $contacts = [];

        foreach ((array) $this->input('contacts', []) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $phone = preg_replace('/\D+/', '', (string) ($row['phone'] ?? '')) ?? '';
            $role = trim((string) ($row['role'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            // سطر فارغ بالكامل يُتجاهل
            if ($phone === '' && $role === '' && $name === '') {
                continue;
            }

            $contacts[] = [
                'id' => $row['id'] ?? null,
                'phone_code' => filled($row['phone_code'] ?? null) ? $row['phone_code'] : PhoneCountries::DEFAULT,
                'phone' => ltrim($phone, '0'),
                'role' => $role,
                'name' => $name,
            ];
        }

        $this->merge([
            'contacts' => $contacts,
            'latitude' => $this->blankToNull('latitude'),
            'longitude' => $this->blankToNull('longitude'),
        ]);
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        return $value === '' || $value === null ? null : $value;
    }

    public function rules(): array
    {
        $recordId = $this->route('fieldOwner')?->id ?? 0;

        return [
            'contacts' => ['required', 'array', 'min:1', 'max:20'],
            'contacts.*.id' => ['nullable', 'integer', Rule::exists('field_owner_contacts', 'id')->where('field_owner_id', $recordId)],
            'contacts.*.name' => ['required', 'string', 'max:150'],
            'contacts.*.role' => ['nullable', 'string', 'max:60'],
            'contacts.*.phone_code' => ['required', 'string', Rule::in(PhoneCountries::codes())],
            'contacts.*.phone' => ['required', 'string', 'regex:/^[0-9]{4,15}$/'],

            'contact_method' => ['required', Rule::in(array_keys(FieldOwnerFields::CONTACT_METHODS))],
            'stage' => ['required', Rule::in(array_keys(FieldOwnerFields::STAGES))],
            'notes' => ['nullable', 'string', 'max:5000'],

            'property_number' => ['nullable', 'string', 'max:60'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],

            'photos' => ['nullable', 'array', 'max:30'],
            'photos.*' => FieldOwner::imageRules(),
            'photos_removed' => ['nullable', 'array'],
            'photos_removed.*' => ['integer'],
        ];
    }

    /** المنطقة يجب أن تتبع المحافظة المختارة */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $cityId = $this->input('city_id');
            $areaId = $this->input('area_id');

            if (! $cityId || ! $areaId) {
                return;
            }

            $area = Area::find($areaId);

            if ($area && $area->city_id && (int) $area->city_id !== (int) $cityId) {
                $v->errors()->add('area_id', 'المنطقة المختارة لا تتبع المحافظة المختارة.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'contacts.required' => 'أضف مسؤولًا واحدًا على الأقل.',
            'contacts.min' => 'أضف مسؤولًا واحدًا على الأقل.',
            'contacts.*.name.required' => 'اسم المسؤول مطلوب.',
            'contacts.*.phone.required' => 'رقم الهاتف مطلوب.',
            'contacts.*.phone.regex' => 'رقم الهاتف يجب أن يكون أرقامًا فقط (4 إلى 15 رقمًا).',
            'latitude.required_with' => 'حدّد الموقع على الخريطة أو امسح الإحداثيات.',
            'longitude.required_with' => 'حدّد الموقع على الخريطة أو امسح الإحداثيات.',
            'photos.*.image' => 'الملف يجب أن يكون صورة.',
            'photos.*.mimetypes' => 'الصيغ المسموحة: JPG، PNG، WebP.',
            'photos.*.max' => 'حجم الصورة يجب ألا يتجاوز 6 ميجابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'contacts' => 'المسؤولون',
            'contacts.*.name' => 'الاسم',
            'contacts.*.role' => 'صفته',
            'contacts.*.phone_code' => 'مفتاح الدولة',
            'contacts.*.phone' => 'رقم الهاتف',
            'contact_method' => 'طريقة التواصل',
            'stage' => 'المرحلة',
            'notes' => 'الملاحظات',
            'property_number' => 'رقم العقار',
            'city_id' => 'المحافظة',
            'area_id' => 'المنطقة',
            'address' => 'العنوان',
            'latitude' => 'خط العرض',
            'longitude' => 'خط الطول',
            'photos' => 'الصور',
            'photos.*' => 'الصورة',
        ];
    }
}
