<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCapaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ncr_id'            => 'nullable|exists:production_ncrs,id',
            'action_owner_id'   => 'required|exists:users,id',
            'corrective_action' => 'required|string',
            'preventive_action' => 'nullable|string',
            'target_date'       => 'required|date',
        ];
    }
}
