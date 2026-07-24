<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Services\ProjectMemberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskListRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'owner_id' => [
                'nullable',
                'integer',
                $members->activeCollaboratorRule($project),
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
