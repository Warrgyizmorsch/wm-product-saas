<?php

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();

        return [
            'user_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'project_role' => ['nullable', 'string', 'max:255'],
            'rate_per_hour' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'cost_per_hour' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'budget_hours' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
