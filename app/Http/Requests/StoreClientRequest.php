<?php

namespace App\Http\Requests;

class StoreClientRequest extends ClientFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clients.create') ?? false;
    }
}
