<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** إضافة/تعديل مهمة (الحالة تُقبل في التعديل فقط — الإنشاء يبدأ من «جديد») */
class TaskFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => [$this->isMethod('POST') ? 'prohibited' : 'sometimes', Rule::in(array_keys(Task::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'mimes:'.implode(',', Task::FILE_EXTENSIONS), 'max:'.Task::MAX_FILE_KB],
            'files_removed' => ['nullable', 'array'],
            'files_removed.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'العنوان',
            'description' => 'الوصف',
            'status' => 'الحالة',
            'priority' => 'الأولوية',
            'due_date' => 'تاريخ الاستحقاق',
            'assignee_id' => 'المسند إليه',
            'property_id' => 'العقار',
            'files' => 'المرفقات',
            'files.*' => 'المرفق',
        ];
    }

    public function messages(): array
    {
        return [
            'files.*.mimes' => 'المرفق يجب أن يكون صورة أو PDF أو Word أو Excel.',
            'files.*.max' => 'حجم المرفق يجب ألا يتجاوز 15 ميجابايت.',
        ];
    }

    /** الملفات لا تدخل ضمن بيانات المهمة — يتولاها TaskService من الطلب */
    public function taskData(): array
    {
        return collect($this->validated())->except(['files', 'files_removed'])->all();
    }
}
