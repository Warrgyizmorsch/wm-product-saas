<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Task::STATUSES)],
        ];
    }
}
