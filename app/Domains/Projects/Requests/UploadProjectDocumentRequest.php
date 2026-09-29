<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\ProjectDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadProjectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:25600', // 25 MB
                'mimes:pdf,docx,xlsx,csv,png,jpg,jpeg,zip,txt',
            ],
            'category' => ['required', Rule::in(ProjectDocument::CATEGORIES)],
            'title' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:500'],
            'attachable_type' => ['nullable', 'string'],
            'attachable_id' => ['nullable', 'integer'],
        ];
    }
}
