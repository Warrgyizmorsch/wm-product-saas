<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeBatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_batch_ids'        => 'required|array|min:2',
            'parent_batch_ids.*'      => 'integer',
            'target_planned_quantity' => 'required|numeric|min:0.0001',
            'remarks'                 => 'nullable|string|max:1000',
        ];
    }
}
