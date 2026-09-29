<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();
        $project = $this->route('project');
        $projectId = is_object($project) ? $project->id : $project;

        return [
            'task_id' => [
                'nullable',
                'integer',
                Rule::exists('project_tasks', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $projectId),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'severity' => ['sometimes', 'required', Rule::in(Issue::SEVERITIES)],
            'priority' => ['sometimes', 'required', Rule::in(Issue::PRIORITIES)],
            'status' => ['sometimes', 'required', Rule::in(Issue::STATUSES)],
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('project_members', 'user_id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $projectId)
                    ->where('is_active', true),
            ],
            'steps_to_reproduce' => ['nullable', 'string'],
            'resolution_notes' => ['nullable', 'string'],
        ];
    }
}
