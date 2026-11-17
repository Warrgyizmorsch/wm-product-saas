<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\ProjectMemberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

    public function rules(ProjectMemberService $members): array
    {
        $tenantId = require_tenant_id();
        $project = $this->route('project');

        return [
            'name'           => ['required', 'string', 'max:255'],
            'customer_id'    => [
                'nullable', 'integer',
                Rule::exists('customers', 'id')->where('tenant_id', $tenantId),
            ],
            'owner_id'       => [
                'required', 'integer',
                $members->activeCollaboratorRule($project),
            ],
            'manager_id'     => [
                'nullable', 'integer',
                $members->activeCollaboratorRule($project),
            ],
            'start_date'     => ['required', 'date'],
            'end_date'       => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_type'    => ['nullable', Rule::in(Project::BUDGET_TYPES)],
            'budget_amount'  => ['nullable', 'numeric', 'min:0'],
            'budget_hours'   => ['nullable', 'numeric', 'min:0'],
            'billing_method' => ['nullable', Rule::in(Project::BILLING_METHODS)],
            'priority'       => ['required', Rule::in(Project::PRIORITIES)],
            'status'         => ['required', Rule::in(Project::STATUSES)],
            'description'    => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'A project cannot be set to that status.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $project = $this->route('project');
            if ($project instanceof Project && $project->isContractLocked()) {
                $reason = $project->contractLockReason();

                if ($this->has('customer_id') && (int) $this->input('customer_id') !== (int) $project->customer_id) {
                    $validator->errors()->add('customer_id', $reason);
                }
                if ($this->has('budget_type') && $this->input('budget_type') !== $project->budget_type) {
                    $validator->errors()->add('budget_type', $reason);
                }
                if ($this->has('billing_method') && $this->input('billing_method') !== $project->billing_method) {
                    $validator->errors()->add('billing_method', $reason);
                }
            }
        });
    }
}
