<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MesCompleteOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_produced' => 'required|numeric|min:0',
            'quantity_rejected' => 'nullable|numeric|min:0',
            'quantity_scrapped' => 'nullable|numeric|min:0',
            'setup_minutes' => 'nullable|numeric|min:0',
            'run_minutes' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
        ];
    }
}
