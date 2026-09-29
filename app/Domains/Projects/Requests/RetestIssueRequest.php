<?php

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetestIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'passed' => ['required', 'boolean'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
