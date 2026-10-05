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
        return [
            'closure_status' => [
                'required',
                'string',
                Rule::in(Project::CLOSURE_STATUSES),
            ],
            'closure_date' => [
                'required',
                'date',
            ],
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
}
