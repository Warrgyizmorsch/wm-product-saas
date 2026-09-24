<?php

namespace App\Domains\Production\Requests\Api;

class IssueMaterialApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reservation_id' => 'required|exists:production_order_reservations,id',
            'warehouse_id'   => 'nullable|exists:warehouses,id',
            'quantity'       => 'required|numeric|min:0.0001',
            'remarks'        => 'nullable|string|max:255',
        ];
    }
}
