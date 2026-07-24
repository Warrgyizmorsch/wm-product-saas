<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Services\ProjectMemberService;
use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

    public function rules(ProjectMemberService $members): array
    {
        $project = $this->route('project');

        return [
            'assignee_id' => [
                'nullable',
                'integer',
                $members->activeCollaboratorRule($project),
            ],
            'reviewer_id' => [
                'nullable',
                'integer',
                $members->activeCollaboratorRule($project),
            ],
        ];
    }
}
