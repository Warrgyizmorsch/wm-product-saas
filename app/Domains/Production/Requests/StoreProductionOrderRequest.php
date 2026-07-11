<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'          => 'required|exists:products,id',
            'bom_id'              => 'nullable|exists:production_boms,id',
            'routing_id'          => 'nullable|exists:routings,id',
            'sales_order_id'      => 'nullable|exists:sales_orders,id',
            'sales_order_item_id' => 'nullable|exists:sales_order_items,id',
            'quantity_ordered'    => 'required|numeric|min:0.0001',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after_or_equal:start_date',
            'description'         => 'nullable|string|max:1000',
        ];
    }
}
