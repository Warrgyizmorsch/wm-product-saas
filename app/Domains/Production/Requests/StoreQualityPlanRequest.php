<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Quality Plan create / edit (docs/production_quality_rules.md):
 *  - QR-02: scope product | section | process; the matching target is required and must belong to the tenant
 *  - QR-07 / QR-08: status and version are never taken from the form (set by the lifecycle service)
 *  - QR-06: numeric rows need max ≥ min; min/max/unit/basis of other types are ignored server-side
 */
class StoreQualityPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = function_exists('tenant_id') ? tenant_id() : null;

        return [
            'name'       => 'required|string|max:255',
            'type'       => 'required|string|in:product,section,process',
            'product_id' => [
                'nullable',
                'required_if:type,product',
                Rule::exists('products', 'id')->where(fn ($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q),
            ],
            'section_id' => [
                'nullable',
                'required_if:type,section',
                Rule::exists('production_work_center_sections', 'id')->where(fn ($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q),
            ],
            'parameters'                   => 'required|array|min:1',
            'parameters.*.name'            => 'required|string|max:255',
            'parameters.*.type'            => 'required|string|in:numeric,pass_fail,text',
            'parameters.*.min_value'       => 'nullable|numeric',
            'parameters.*.max_value'       => 'nullable|numeric',
            // Unit is picked from the UOM master (numeric rows only)
            'parameters.*.uom_id'          => [
                'nullable',
                'integer',
                Rule::exists('uoms', 'id')->where(fn ($q) => $tenantId ? $q->where('tenant_id', $tenantId) : $q),
            ],
            'parameters.*.basis'           => 'nullable|string|max:1000',
            'parameters.*.is_mandatory'    => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required_if' => 'Choose the target product for a product-specific plan.',
            'section_id.required_if' => 'Choose the target section for a section-specific plan.',
            'parameters.required'    => 'A quality plan needs at least one checklist parameter.',
            'parameters.min'         => 'A quality plan needs at least one checklist parameter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            foreach ((array) $this->input('parameters', []) as $i => $param) {
                if (($param['type'] ?? null) !== 'numeric') {
                    continue;
                }
                $min = $param['min_value'] ?? null;
                $max = $param['max_value'] ?? null;
                if ($min !== null && $min !== '' && $max !== null && $max !== '' && is_numeric($min) && is_numeric($max) && (float) $max < (float) $min) {
                    $row = (int) $i + 1;
                    $v->errors()->add("parameters.{$i}.max_value", "Row {$row}: Max value must be greater than or equal to Min value.");
                }
            }
        });
    }
}
