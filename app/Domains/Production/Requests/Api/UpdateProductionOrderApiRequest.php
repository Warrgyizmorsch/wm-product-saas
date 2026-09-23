<?php

namespace App\Domains\Production\Requests\Api;

class UpdateProductionOrderApiRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'quantity_ordered' => 'sometimes|required|numeric|min:0.0001',
            'production_mode'  => 'nullable|string|in:standard,batch,serial,batch_and_serial',
            'production_model' => 'nullable|string|in:pure_manufacturing,subcontract_complete,subcontract_company_material,hybrid',
            'start_date'       => 'sometimes|required|date',
            'end_date'         => 'sometimes|required|date|after_or_equal:start_date',
            'description'      => 'nullable|string|max:1000',
        ];
    }
}
