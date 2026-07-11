<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
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
            'task_list_id' => [
                'required',
                'integer',
                Rule::exists('project_task_lists', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $project?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('project_members', 'user_id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $project?->id)
                    ->where('is_active', true),
            ],
            'reviewer_id' => [
                'nullable',
                'integer',
                Rule::exists('project_members', 'user_id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $project?->id)
                    ->where('is_active', true),
            ],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
