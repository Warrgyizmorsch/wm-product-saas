<?php

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskDependencyRequest extends FormRequest
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
            'depends_on_task_id' => [
                'required',
                'integer',
                Rule::exists('project_tasks', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $project?->id),
                Rule::unique('project_task_dependencies', 'depends_on_task_id')
                    ->where('task_id', $this->route('task')?->id),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $task = $this->route('task');

            if ($task && (int) $this->input('depends_on_task_id') === $task->id) {
                $validator->errors()->add('depends_on_task_id', 'A task cannot depend on itself.');
            }
        });
    }
}
