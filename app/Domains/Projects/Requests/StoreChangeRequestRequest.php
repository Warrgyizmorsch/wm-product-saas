<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = require_tenant_id();
        $project = $this->route('project');
        $projectId = $project instanceof Project ? $project->id : (int) $project;

        return [
            'project_review_id'    => [
                'nullable',
                'integer',
                Rule::exists('project_reviews', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $projectId),
            ],
            'title'                => ['required', 'string', 'max:255'],
            'description'          => ['required', 'string', 'max:10000'],
            'requested_by'         => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'impact_schedule_days' => ['nullable', 'integer', 'min:0'],
            'impact_budget_amount' => ['nullable', 'numeric', 'min:0'],
            'impact_budget_hours'  => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
