<?php

namespace App\Http\Requests;

class UpdateClientRequest extends ClientFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clients.edit') ?? false;
    }
}
