<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateProjectInvoiceRequest extends FormRequest
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
            'invoice_date'       => ['required', 'date'],
            'due_date'           => ['required', 'date', 'after_or_equal:invoice_date'],
            'payment_terms'      => ['nullable', 'string', 'max:255'],
            'service_product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'time_log_ids'       => ['nullable', 'array'],
            'time_log_ids.*'     => [
                'integer',
                Rule::exists('project_time_logs', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $projectId),
            ],
            'milestone_ids'      => ['nullable', 'array'],
            'milestone_ids.*'    => [
                'integer',
                Rule::exists('project_milestones', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $projectId),
            ],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $timeLogIds = $this->input('time_log_ids', []);
            $milestoneIds = $this->input('milestone_ids', []);

            if (empty($timeLogIds) && empty($milestoneIds)) {
                $validator->errors()->add('selection', __('projects.select_at_least_one_deliverable') ?: 'Please select at least one time log or completed milestone to invoice.');
            }
        });
    }
}
