<?php

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();
        $project = $this->route('project');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'owner_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'milestone_id' => [
                'nullable',
                'integer',
                Rule::exists('project_milestones', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $project?->id),
            ],
        ];
    }
}
