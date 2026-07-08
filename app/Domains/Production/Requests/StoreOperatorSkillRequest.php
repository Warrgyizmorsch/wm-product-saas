<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOperatorSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'        => 'required|exists:users,id',
            'skill_code'     => 'required|string|max:100',
            'work_center_id' => 'nullable|exists:production_work_centers,id',
            'machine_id'     => 'nullable|exists:production_machines,id',
            'active'         => 'nullable|boolean',
        ];
    }
}
