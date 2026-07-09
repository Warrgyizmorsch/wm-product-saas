<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScrapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category'    => 'required|string|in:raw_material,finished_good,scrap_metal,chemical',
            'reason_code' => 'required|string',
            'quantity'    => 'required|numeric|min:0.01',
            'cost'        => 'nullable|numeric|min:0',
            'ncr_id'      => 'nullable|exists:production_ncrs,id',
        ];
    }
}
