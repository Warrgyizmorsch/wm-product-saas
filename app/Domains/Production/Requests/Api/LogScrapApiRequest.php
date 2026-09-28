<?php

namespace App\Domains\Production\Requests\Api;

class LogScrapApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'operation_id'        => 'nullable|exists:production_order_operations,id',
            'product_id'          => 'nullable|exists:products,id',
            'quantity'            => 'nullable|numeric|min:0.0001',
            'reason'              => 'nullable|string|max:255',
            'measurement_type'    => 'nullable|string|in:linear,sheet,weight,count',
            'length'              => 'nullable|numeric|min:0.01',
            'width'               => 'nullable|numeric|min:0.01',
            'thickness'           => 'nullable|numeric|min:0.01',
            'pieces'              => 'nullable|integer|min:1',
            'weight'              => 'nullable|numeric|min:0.01',
            'weight_unit'         => 'nullable|string|in:kg,g',
            'scrap_type'          => 'nullable|string|max:40',
            'scrap_warehouse_id'  => 'nullable|integer|exists:warehouses,id',
            'storage_location'    => 'nullable|string|max:100',
            'create_ncr'          => 'nullable|boolean',
            'ncr_category'        => 'nullable|string|max:100',
        ];
    }
}
