<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'              => 'required|string|max:100',
            'device_identifier' => 'nullable|string|max:100',
        ];
    }
}
