<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('clients.edit');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'type_id' => ['nullable', 'exists:client_types,id'],
            'stage_id' => ['nullable', 'exists:client_stages,id'],
            'agent_id' => ['nullable', 'exists:users,id'],
            'source_id' => ['nullable', 'exists:marketing_sources,id'],
            'rating' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
