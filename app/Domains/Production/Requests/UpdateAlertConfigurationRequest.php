<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'threshold' => 'required|numeric',
            'severity'  => 'required|string|in:info,warning,critical',
            'active'    => 'nullable|boolean',
        ];
    }
}
