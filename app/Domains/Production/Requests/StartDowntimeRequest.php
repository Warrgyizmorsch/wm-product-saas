<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartDowntimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id'                     => 'required|integer',
            'category'                       => 'required|string|in:Breakdown,Preventive Maintenance,Corrective Maintenance,Setup,Tool Change,Power Failure,Material Shortage,Operator Shortage,Quality Hold,Engineering Hold,Cleaning,Calibration,Other',
            'reason'                         => 'required|string|max:255',
            'production_order_id'            => 'nullable|integer',
            'production_order_operation_id'  => 'nullable|integer',
            'remarks'                        => 'nullable|string|max:1000',
        ];
    }
}
