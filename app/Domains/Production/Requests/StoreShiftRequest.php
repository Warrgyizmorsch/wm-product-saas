<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:255',
            'code'             => 'required|string|max:50',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i',
            'break_minutes'    => 'required|integer|min:0',
            'overtime_allowed' => 'nullable|boolean',
            'hourly_labor_rate'=> 'nullable|numeric|min:0|max:9999999999.9999',
            'active'           => 'nullable|boolean',
        ];
    }
}
