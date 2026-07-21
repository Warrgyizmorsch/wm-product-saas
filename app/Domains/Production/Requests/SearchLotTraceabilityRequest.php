<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchLotTraceabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user() && auth()->user()->hasProductionPermission('production.mes.execute');
    }

    public function rules(): array
    {
        return [
            'type'      => 'required|string|in:batch,serial,order,lot',
            'code'      => 'required|string|max:100',
            'direction' => 'nullable|string|in:both,forward,backward',
        ];
    }
}
