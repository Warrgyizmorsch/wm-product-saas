<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'                => 'required|string|in:temporary,permanent,customer_waiver',
            'description'         => 'required|string',
            'expiration_date'     => 'nullable|date',
            'expiration_quantity' => 'nullable|numeric',
        ];
    }
}
