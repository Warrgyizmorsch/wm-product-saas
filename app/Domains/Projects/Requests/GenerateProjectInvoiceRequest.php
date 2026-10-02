<?php

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateProjectInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_date'       => ['required', 'date'],
            'due_date'           => ['required', 'date', 'after_or_equal:invoice_date'],
            'payment_terms'      => ['nullable', 'string', 'max:255'],
            'service_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'time_log_ids'       => ['nullable', 'array'],
            'time_log_ids.*'     => ['integer'],
            'milestone_ids'      => ['nullable', 'array'],
            'milestone_ids.*'    => ['integer'],
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
