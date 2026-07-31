<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.edit');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['call', 'meeting', 'whatsapp', 'email'])],
            'notes' => ['nullable', 'string'],
            'stage_id' => ['nullable', 'exists:client_stages,id'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'نوع التواصل',
            'notes' => 'الملاحظات',
            'stage_id' => 'المرحلة',
        ];
    }
}
