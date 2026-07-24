<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Services\ProjectMemberService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handled in controller
    }

    public function rules(ProjectMemberService $members): array
    {
        $project = $this->route('project');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'owner_id' => [
                'nullable',
                'integer',
                $members->activeCollaboratorRule($project),
            ],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(Milestone::STATUSES)],
            'completion_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
