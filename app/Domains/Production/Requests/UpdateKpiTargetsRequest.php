<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKpiTargetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'oee'          => 'required|numeric|min:0|max:100',
            'availability' => 'required|numeric|min:0|max:100',
            'performance'  => 'required|numeric|min:0|max:100',
            'quality'      => 'required|numeric|min:0|max:100',
            'throughput'   => 'required|numeric|min:0',
            'utilization'  => 'required|numeric|min:0|max:100',
            'scrap_rate'   => 'required|numeric|min:0|max:100',
            'downtime'     => 'required|numeric|min:0',
        ];
    }
}
