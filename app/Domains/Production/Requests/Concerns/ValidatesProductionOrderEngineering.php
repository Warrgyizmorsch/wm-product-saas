<?php

namespace App\Domains\Production\Requests\Concerns;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Services\ProductionOrderEngineeringService;

/**
 * PO-IMPL-05 (PO-02 / PO-07): shared Web + API rules for Production Order Create —
 * BOM / Routing blocking checks and Tab 1 selection completeness, 422 on any failure.
 */
trait ValidatesProductionOrderEngineering
{
    protected function engineeringSelectionRules(): array
    {
        return [
            'selected_operation_ids'          => 'nullable|array',
            'selected_operation_ids.*'        => 'integer',
            'selected_material_ids'           => 'nullable|array',
            'selected_material_ids.*'         => 'integer',
            'engineering_selection_submitted' => 'nullable|boolean',
        ];
    }

    protected function validateProductionOrderEngineering($validator, int $tenantId): void
    {
        if ($validator->errors()->has('product_id') || !$this->input('product_id')) {
            return;
        }

        $product = Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($this->input('product_id'));
        if (!$product) {
            $validator->errors()->add('product_id', 'The selected product does not exist.');

            return;
        }

        $hasOps = $this->exists('selected_operation_ids');
        $hasMats = $this->exists('selected_material_ids');
        $requireExplicit = $this->boolean('engineering_selection_submitted') || $hasOps || $hasMats;

        $resolution = app(ProductionOrderEngineeringService::class)->resolveAndValidate(
            $product,
            $tenantId,
            $this->filled('bom_id') ? (int) $this->input('bom_id') : null,
            $this->filled('routing_id') ? (int) $this->input('routing_id') : null,
            $hasOps ? array_values((array) ($this->input('selected_operation_ids') ?? [])) : null,
            $hasMats ? array_values((array) ($this->input('selected_material_ids') ?? [])) : null,
            $requireExplicit
        );

        foreach ($resolution['errors'] as $field => $messages) {
            foreach (array_unique($messages) as $message) {
                $validator->errors()->add($field, $message);
            }
        }
    }
}
