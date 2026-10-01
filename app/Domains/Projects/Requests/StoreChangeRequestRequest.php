<?php

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_review_id'    => ['nullable', 'integer'],
            'title'                => ['required', 'string', 'max:255'],
            'description'          => ['required', 'string', 'max:10000'],
            'impact_schedule_days' => ['nullable', 'integer', 'min:0'],
            'impact_budget_amount' => ['nullable', 'numeric', 'min:0'],
            'impact_budget_hours'  => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
