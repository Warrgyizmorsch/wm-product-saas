<?php

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'assignee_id' => [
                'nullable',
                'integer',
                Rule::exists('project_members', 'user_id')
                    ->where('tenant_id', $tenantId)
                    ->where('project_id', $project?->id)
                    ->where('is_active', true),
            ],
        ];
    }
}
