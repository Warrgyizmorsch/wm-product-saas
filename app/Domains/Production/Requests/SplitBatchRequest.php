<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SplitBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_batch_id'           => 'required|integer',
            'splits'                    => 'required|array|min:1',
            'splits.*.planned_quantity' => 'required|numeric|min:0.0001',
            'splits.*.remarks'          => 'nullable|string|max:255',
        ];
    }
}
