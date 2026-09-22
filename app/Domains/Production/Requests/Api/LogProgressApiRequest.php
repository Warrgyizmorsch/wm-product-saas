<?php

namespace App\Domains\Production\Requests\Api;

class LogProgressApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'operation_id'         => 'required|exists:production_order_operations,id',
            'batch_id'             => 'nullable|exists:production_batches,id',
            'quantity_produced'    => 'required|numeric|min:0',
            'quantity_rejected'    => 'nullable|numeric|min:0',
            'quantity_scrapped'    => 'nullable|numeric|min:0',
            'setup_minutes_logged' => 'nullable|numeric|min:0',
            'run_minutes_logged'   => 'nullable|numeric|min:0',
            'machine_id'           => 'nullable|exists:production_machines,id',
            'complete_operation'   => 'nullable|boolean',
            'remarks'              => 'nullable|string|max:255',
        ];
    }
}
