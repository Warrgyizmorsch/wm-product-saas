<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bom_number' => 'nullable|string|max:255',
            'bom_name' => 'nullable|string|max:255',
            'bom_type' => 'required|in:manufacturing,engineering,sales,phantom,subcontracting',
            'usage_context' => 'nullable|string|in:manufacturing,engineering,prototype,costing',
            'product_id' => 'required|exists:products,id',
            'base_quantity' => 'required|numeric|gt:0',
            'base_uom_id' => 'required|exists:uoms,id',
            'version' => 'required|string|max:50',
            'revision_reason' => 'nullable|string|max:1000',
            'routing_id' => 'nullable|exists:routings,id',
            'effective_date' => 'required|date',
            'expiry_date' => 'nullable|date|after_or_equal:effective_date',
            'notes' => 'nullable|string',
            
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:products,id',
            'items.*.child_bom_id' => 'nullable|integer|exists:production_boms,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.uom_id' => 'required|exists:uoms,id',
            'items.*.material_scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.is_alternative' => 'nullable',
            'items.*.alternative_group' => 'nullable|string|max:255',
            'items.*.priority' => 'nullable|integer|min:1',
            'items.*.sequence' => 'nullable|integer|min:1',
            'items.*.effective_from' => 'nullable|date',
            'items.*.effective_to' => 'nullable|date|after_or_equal:items.*.effective_from',
            'items.*.notes' => 'nullable|string',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $productId = (int) $this->input('product_id');
            $items = $this->input('items', []);
            $materialIds = [];

            foreach ($items as $index => $item) {
                $materialId = (int) ($item['material_id'] ?? 0);
                if ($materialId > 0) {
                    // Rule 1: Finished product cannot be its own component
                    if ($materialId === $productId) {
                        $validator->errors()->add("items.{$index}.material_id", "A finished product cannot be its own component material.");
                    }

                    // Rule 2: Duplicate component prevention
                    if (in_array($materialId, $materialIds)) {
                        $validator->errors()->add("items.{$index}.material_id", "Duplicate material component found in BOM.");
                    } else {
                        $materialIds[] = $materialId;
                    }
                }

                // Rule 3: Alternative material group validation
                $isAlternative = !empty($item['is_alternative']);
                $altGroup = trim($item['alternative_group'] ?? '');
                if ($isAlternative && empty($altGroup)) {
                    $validator->errors()->add("items.{$index}.alternative_group", "An alternative group code is required if alternative is checked.");
                }
            }
        });
    }
}
