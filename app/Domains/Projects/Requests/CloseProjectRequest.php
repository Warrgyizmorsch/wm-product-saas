<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $project = $this->route('project');
        $startDate = $project instanceof Project ? $project->start_date?->format('Y-m-d') : null;

        return [
            'closure_status' => [
                'required',
                'string',
                Rule::in(Project::CLOSURE_STATUSES),
            ],
            'closure_date' => array_values(array_filter([
                'required',
                'date',
                $startDate ? 'after_or_equal:' . $startDate : null,
            ])),
            'client_approval_ref' => [
                'nullable',
                'string',
                'max:255',
            ],
            'final_remarks' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'closure_date.after_or_equal' => __('projects.closure_date_after_start_date', [
                'default' => 'The closure date must be on or after the project start date.',
            ]),
        ];
    }
}
