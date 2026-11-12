<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();
        $project = $this->route('project');
        $isUserBased = $project instanceof Project
            ? $project->billing_method === 'User Based'
            : false;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'project_role' => ['nullable', 'string', 'max:255'],
            'rate_per_hour' => [$isUserBased ? 'required' : 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'cost_per_hour' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'budget_hours' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'rate_per_hour.required' => __('projects.rate_required_for_user_billing'),
        ];
    }
}
