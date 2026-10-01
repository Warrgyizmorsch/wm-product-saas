<?php

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\ProjectReview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SignOffProjectReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => [
                'required',
                Rule::in([ProjectReview::STATUS_APPROVED, ProjectReview::STATUS_REWORK_REQUIRED]),
            ],
            'status'        => ['nullable', 'string'],
            'comments'      => ['nullable', 'string', 'max:5000'],
            'sign_off_ref'  => ['nullable', 'string', 'max:255'],
            'evidence_file' => [
                'nullable',
                'file',
                'max:25600',
                'mimes:pdf,docx,xlsx,csv,png,jpg,jpeg,zip,txt',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('outcome') && $this->has('status')) {
            $this->merge(['outcome' => $this->input('status')]);
        }
    }
}
