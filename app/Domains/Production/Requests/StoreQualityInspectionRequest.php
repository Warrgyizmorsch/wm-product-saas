<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQualityInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quality_plan_id'               => 'required|exists:production_quality_plans,id',
            'stage'                         => 'required|string|in:incoming,in_process,final',
            'production_order_id'           => 'nullable|integer',
            'production_order_operation_id' => 'nullable|integer',
        ];
    }
}
