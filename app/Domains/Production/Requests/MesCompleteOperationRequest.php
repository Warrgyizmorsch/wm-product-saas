<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MesCompleteOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id' => 'nullable|integer|exists:production_batches,id',
            'production_batch_id' => 'nullable|integer|exists:production_batches,id',
            'quantity_produced' => 'required|numeric|min:0',
            'quantity_rejected' => 'nullable|numeric|min:0',
            'quantity_scrapped' => 'nullable|numeric|min:0',
            'setup_minutes' => 'nullable|numeric|min:0',
            'run_minutes' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
            // Temp no-BOM flow: raw materials consumed by this log
            'consumed_materials' => 'nullable|array',
            'consumed_materials.*.product_id' => 'nullable|integer|exists:products,id',
            'consumed_materials.*.quantity' => 'nullable|numeric|min:0',
            'consumed_materials.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ];
    }
}
