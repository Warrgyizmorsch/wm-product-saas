<?php

namespace App\Domains\Production\Requests\Api;

class StoreProductionOrderApiRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Domains\Production\Models\ProductionOrder::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id'                  => 'required|exists:products,id',
            'bom_id'                      => 'nullable|exists:production_boms,id',
            'routing_id'                  => 'nullable|exists:routings,id',
            'production_plan_id'          => 'nullable|exists:production_plans,id',
            'quantity_ordered'            => 'required|numeric|min:0.0001',
            'production_mode'             => 'nullable|string|in:standard,batch,serial,batch_and_serial',
            'production_model'            => 'nullable|string|in:pure_manufacturing,subcontract_complete,subcontract_company_material,hybrid',
            'start_date'                  => 'required|date',
            'end_date'                    => 'required|date|after_or_equal:start_date',
            'description'                 => 'nullable|string|max:1000',
        ];
    }
}
