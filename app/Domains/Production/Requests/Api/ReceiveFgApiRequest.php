<?php

namespace App\Domains\Production\Requests\Api;

class ReceiveFgApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'quantity_received' => 'required|numeric|min:0.0001',
            'warehouse_id'      => 'nullable|exists:warehouses,id',
            'quality_status'    => 'required|string|in:passed,quarantine,failed',
            'remarks'           => 'nullable|string|max:255',
        ];
    }
}
