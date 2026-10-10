<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueReworkMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'          => 'required|integer|exists:products,id',
            'quantity'            => 'required|numeric|min:0.0001',
            'warehouse_id'        => 'nullable|integer|exists:warehouses,id',
            'rework_operation_id' => 'nullable|integer|exists:production_rework_operations,id',
            'remarks'             => 'nullable|string|max:500',
        ];
    }
}
