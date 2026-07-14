<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();

        return [
            'name'           => ['required', 'string', 'max:255'],
            'customer_id'    => [
                'nullable', 'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $tenantId),
            ],
            'owner_id'       => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'manager_id'     => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_type'    => ['nullable', Rule::in(Project::BUDGET_TYPES)],
            'budget_amount'  => ['nullable', 'numeric', 'min:0'],
            'budget_hours'   => ['nullable', 'numeric', 'min:0'],
            'billing_method' => ['nullable', Rule::in(Project::BILLING_METHODS)],
            'priority'       => ['required', Rule::in(Project::PRIORITIES)],
            'status'         => ['required', Rule::in(Project::EDITABLE_STATUSES)],
            'description'    => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'A project cannot be Closed from the edit form.',
        ];
    }
}
