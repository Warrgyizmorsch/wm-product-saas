<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Milestone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'owner_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(Milestone::STATUSES)],
            'completion_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
