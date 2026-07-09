<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:255',
            'working_days' => 'required|array',
            'working_days.*' => 'integer|between:0,6', // 0=Sun, 1=Mon, ..., 6=Sat
            'is_default'   => 'nullable|boolean',
        ];
    }
}
