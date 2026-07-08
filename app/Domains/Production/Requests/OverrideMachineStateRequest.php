<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OverrideMachineStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => 'required|integer',
            'state'      => 'required|string|in:Idle,Running,Setup,Waiting Material,Waiting Operator,Maintenance,Breakdown,Offline,Unknown',
            'reason'     => 'nullable|string|max:255',
            'remarks'    => 'nullable|string|max:1000',
        ];
    }
}
