<?php

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewer_id'   => ['nullable', 'exists:users,id'],
            'reviewer_name' => ['nullable', 'string', 'max:255'],
            'review_date'   => ['required', 'date'],
            'sign_off_ref'  => ['nullable', 'string', 'max:255'],
            'comments'      => ['nullable', 'string', 'max:5000'],
            'evidence_file' => [
                'nullable',
                'file',
                'max:25600',
                'mimes:pdf,docx,xlsx,csv,png,jpg,jpeg,zip,txt',
            ],
        ];
    }
}
