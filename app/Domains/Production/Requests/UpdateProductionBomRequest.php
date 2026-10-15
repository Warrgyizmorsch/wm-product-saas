<?php

namespace App\Domains\Production\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $productId = $this->input('product_id');
        if ($productId) {
            $product = \App\Domains\Inventory\Models\Product::find($productId);
            if ($product && $product->uom_id) {
                $this->merge(['base_uom_id' => $product->uom_id]);
            }
        }

        $items = $this->input('items', []);
        if (is_array($items)) {
            $modified = false;
            foreach ($items as $index => $item) {
                if (!empty($item['material_id'])) {
                    $material = \App\Domains\Inventory\Models\Product::find($item['material_id']);
                    if ($material && $material->uom_id) {
                        $items[$index]['uom_id'] = $material->uom_id;
                        $modified = true;
                    }
                }
            }
            if ($modified) {
                $this->merge(['items' => $items]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'bom_number' => 'required|string|max:255',
            'bom_name' => 'nullable|string|max:255',
            'bom_type' => 'required|in:manufacturing,engineering,sales,phantom,subcontracting',
            'usage_context' => 'nullable|string|in:manufacturing,engineering,prototype,costing',
            'product_id' => 'required|exists:products,id',
            'base_quantity' => 'required|numeric|gt:0',
            'base_uom_id' => 'required|exists:uoms,id',
            'version' => 'required|string|max:50',
            'revision_reason' => 'nullable|string|max:1000',
            'effective_date' => 'required|date',
            'notes' => 'nullable|string',
            
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.material_id' => 'required|exists:products,id',
            'items.*.child_bom_id' => 'nullable|integer|exists:production_boms,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.quantity_type' => 'nullable|string|in:fixed,formula',
            'items.*.formula' => 'nullable|string|max:500',
            'items.*.uom_id' => 'required|exists:uoms,id',
            'items.*.material_scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.is_alternative' => 'nullable',
            'items.*.replaces_item_id' => 'nullable|integer',
            'items.*.replaces_row_uid' => 'nullable|string|max:100',
            'items.*.row_uid' => 'nullable|string|max:100',
            'items.*.priority' => 'nullable|integer|min:1',
            'items.*.sequence' => 'nullable|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $productId = (int) $this->input('product_id');
            $items = $this->input('items', []);
            $materialIds = [];
            $allRows = [];

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

                $rowUid = !empty($item['row_uid']) ? (string) $item['row_uid'] : null;
                $rowId = !empty($item['id']) ? (string) $item['id'] : null;
                $key = $rowUid ?: ($rowId ?: "index_{$index}");

                $allRows[$key] = [
                    'index' => $index,
                    'is_alternative' => !empty($item['is_alternative']),
                    'id' => $rowId,
                    'row_uid' => $rowUid,
                ];
            }

            foreach ($items as $index => $item) {
                $isAlternative = !empty($item['is_alternative']);
                $targetRef = $item['replaces_row_uid'] ?? ($item['replaces_item_id'] ?? null);

                if ($isAlternative) {
                    if (empty($targetRef)) {
                        $validator->errors()->add("items.{$index}.replaces_row_uid", "An alternative component must specify the primary component it replaces.");
                        continue;
                    }

                    $targetKey = (string) $targetRef;
                    $targetFound = false;
                    $targetIndex = null;
                    $targetIsAlt = false;

                    foreach ($allRows as $k => $info) {
                        if ($k === $targetKey || (string)($info['id'] ?? '') === $targetKey || (string)($info['row_uid'] ?? '') === $targetKey) {
                            $targetFound = true;
                            $targetIndex = $info['index'];
                            $targetIsAlt = $info['is_alternative'];
                            break;
                        }
                    }

                    if (!$targetFound) {
                        $validator->errors()->add("items.{$index}.replaces_row_uid", "The referenced primary component was not found in this BOM.");
                    } elseif ($targetIndex === $index) {
                        $validator->errors()->add("items.{$index}.replaces_row_uid", "A component cannot be an alternative of itself.");
                    } elseif ($targetIsAlt) {
                        $validator->errors()->add("items.{$index}.replaces_row_uid", "An alternative component cannot reference another alternative component.");
                    }
                }
            }
        });
    }
}
