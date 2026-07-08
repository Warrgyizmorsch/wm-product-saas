<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveDashboardPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dashboard_type' => 'required|string',
            'widgets'        => 'required|array',
            'layout'         => 'nullable|string',
        ];
    }
}
