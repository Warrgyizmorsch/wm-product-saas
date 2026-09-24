<?php

namespace App\Domains\Production\Requests\Api;

class LogScrapApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'operation_id' => 'nullable|exists:production_order_operations,id',
            'product_id'   => 'nullable|exists:products,id',
            'quantity'     => 'required|numeric|min:0.0001',
            'reason'       => 'nullable|string|max:255',
            'create_ncr'   => 'nullable|boolean',
            'ncr_category' => 'nullable|string|max:100',
        ];
    }
}
