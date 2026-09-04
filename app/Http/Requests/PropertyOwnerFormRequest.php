<?php

namespace App\Http\Requests;

use App\Models\PropertyOwner;
use App\Support\PhoneCountries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** فورم مالك العقار: بيانات أساسية + أرقام تواصل متعددة + ملفات */
class PropertyOwnerFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->route('owner') ? 'property_owners.edit' : 'property_owners.create';

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

        $this->merge(['contacts' => $contacts]);
    }

    public function rules(): array
    {
        $ownerId = $this->route('owner')?->id ?? 0;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'registered_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'contacts' => ['required', 'array', 'min:1', 'max:20'],
            'contacts.*.id' => ['nullable', 'integer', Rule::exists('property_owner_contacts', 'id')->where('owner_id', $ownerId)],
            'contacts.*.phone_code' => ['required', 'string', Rule::in(PhoneCountries::codes())],
            'contacts.*.phone' => ['required', 'string', 'regex:/^[0-9]{4,15}$/'],
            'contacts.*.role' => ['nullable', 'string', 'max:60'],
            'contacts.*.name' => ['nullable', 'string', 'max:150'],

            'files' => ['nullable', 'array', 'max:30'],
            'files.*' => ['file', 'mimes:'.implode(',', PropertyOwner::FILE_EXTENSIONS), 'max:'.PropertyOwner::MAX_FILE_KB],
            'files_removed' => ['nullable', 'array'],
            'files_removed.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'contacts.required' => 'أضف رقم تواصل واحدًا على الأقل.',
            'contacts.min' => 'أضف رقم تواصل واحدًا على الأقل.',
            'contacts.*.phone.regex' => 'رقم الهاتف يجب أن يكون أرقامًا فقط (4 إلى 15 رقمًا).',
            'files.*.mimes' => 'الملفات المسموحة: صور، PDF، Word، Excel.',
            'files.*.max' => 'حجم الملف يجب ألا يتجاوز 15 ميجابايت.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'الاسم الكامل',
            'email' => 'البريد الإلكتروني',
            'area_id' => 'المنطقة',
            'registered_address' => 'العنوان المسجّل',
            'notes' => 'الملاحظات',
            'contacts' => 'أرقام التواصل',
            'contacts.*.phone_code' => 'مفتاح الدولة',
            'contacts.*.phone' => 'رقم الهاتف',
            'contacts.*.role' => 'صفته',
            'contacts.*.name' => 'إسمه',
            'files' => 'ملفات المالك',
            'files.*' => 'ملف المالك',
        ];
    }
}
