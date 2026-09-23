<?php

namespace App\Domains\Production\Requests\Api;

class StartDowntimeApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'machine_id'                     => 'required|integer|exists:production_machines,id',
            'category'                       => 'required|string',
            'reason'                         => 'required|string|max:255',
            'production_order_id'            => 'nullable|integer',
            'production_order_operation_id'  => 'nullable|integer',
            'remarks'                        => 'nullable|string|max:1000',
        ];
    }
}
