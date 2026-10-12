<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Validation\Rule;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'production_order_request_id' => 'nullable|exists:production_order_requests,id',
            'bom_id' => 'nullable|exists:production_boms,id',
            'routing_id' => 'nullable|exists:routings,id',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'sales_order_item_id' => 'nullable|exists:sales_order_items,id',
            'project_id' => 'nullable|integer',
            'project_milestone_id' => 'nullable|integer',
            'project_task_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
            'quantity_ordered' => 'required|numeric|min:0.0001',
            'production_mode' => 'nullable|string|in:standard,batch,serial,batch_and_serial',
            'production_model' => 'nullable|string|in:pure_manufacturing,subcontract_complete,subcontract_company_material,hybrid',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tenantId = require_tenant_id();
            $targetCompanyId = $this->input('company_id') ?? company_id() ?? (class_exists(\App\Core\Company\CompanyContext::class) ? app(\App\Core\Company\CompanyContext::class)->id() : null);
            $targetBranchId = $this->input('branch_id') ?? branch_id() ?? (class_exists(\App\Core\Branch\BranchContext::class) ? app(\App\Core\Branch\BranchContext::class)->id() : null);

            $projectId = $this->input('project_id');
            $projectTaskId = $this->input('project_task_id');
            $projectMilestoneId = $this->input('project_milestone_id');

            if ($projectTaskId) {
                $task = \App\Domains\Projects\Models\Task::where('tenant_id', $tenantId)->find($projectTaskId);
                if (! $task) {
                    $validator->errors()->add('project_task_id', 'Project task not found or belongs to a different tenant.');
                    return;
                }

                if ($projectId && (int) $task->project_id !== (int) $projectId) {
                    $validator->errors()->add('project_task_id', 'Project task does not belong to the selected project.');
                }

                if ($targetCompanyId && $task->company_id && (int) $targetCompanyId !== (int) $task->company_id) {
                    $validator->errors()->add('project_task_id', __('projects.company_mismatch_error') ?: 'Project task belongs to a different company.');
                }

                if ($targetBranchId && $task->branch_id && (int) $targetBranchId !== (int) $task->branch_id) {
                    $validator->errors()->add('project_task_id', __('projects.branch_mismatch_error') ?: 'Project task belongs to a different branch.');
                }

                if (! $projectId) {
                    $projectId = $task->project_id;
                }
            }

            if ($projectId) {
                $project = \App\Domains\Projects\Models\Project::where('tenant_id', $tenantId)->find($projectId);
                if (! $project) {
                    $validator->errors()->add('project_id', 'Project not found or belongs to a different tenant.');
                    return;
                }

                if ($targetCompanyId && $project->company_id && (int) $targetCompanyId !== (int) $project->company_id) {
                    $validator->errors()->add('project_id', __('projects.company_mismatch_error') ?: 'Project belongs to a different company.');
                }

                if ($targetBranchId && $project->branch_id && (int) $targetBranchId !== (int) $project->branch_id) {
                    $validator->errors()->add('project_id', __('projects.branch_mismatch_error') ?: 'Project belongs to a different branch.');
                }
            }

            if ($projectMilestoneId) {
                $milestone = \App\Domains\Projects\Models\Milestone::where('tenant_id', $tenantId)->find($projectMilestoneId);
                if (! $milestone) {
                    $validator->errors()->add('project_milestone_id', 'Project milestone not found or belongs to a different tenant.');
                    return;
                }

                if ($projectId && (int) $milestone->project_id !== (int) $projectId) {
                    $validator->errors()->add('project_milestone_id', 'Project milestone does not belong to the selected project.');
                }
            }
        });
    }
}
