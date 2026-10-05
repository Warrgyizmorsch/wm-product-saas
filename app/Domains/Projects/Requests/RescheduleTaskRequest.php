<?php

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized via TaskPolicy in controller
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'shift_mode' => ['required', 'string', 'in:ripple,isolated'],
        ];
    }
}
