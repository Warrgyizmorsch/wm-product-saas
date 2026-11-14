<?php

namespace App\Domains\Production\Requests\Concerns;

use App\Core\Tenant\TenantContext;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Services\BomLifecycleService;
use App\Domains\Production\Services\ProductionBomService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared validation rules and cross-field checks for 2-tier BOM creation and update
 * across Web and API FormRequests.
 */
trait ValidatesBomAllocations
{
    /**
     * Raw submitted item UoM IDs captured before server auto-fill so mixed-UoM tampering is still detected.
     *
     * @var array<int, mixed>
     */
    protected array $submittedItemUoms = [];

    protected function resolveBomTenantId(): int
    {
        return (int) (tenant_id() ?? app(TenantContext::class)->id() ?? auth()->user()?->tenant_id ?? 0);
    }

    protected function tenantExists(string $table): Exists
    {
        $tenantId = $this->resolveBomTenantId();

        return Rule::exists($table, 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId));
    }

    protected function resolveRouteBom(): ?ProductionBom
    {
        $param = $this->route('bom') ?? $this->route('production_bom');
        if ($param instanceof ProductionBom) {
            return $param;
        }
        if (is_numeric($param) && (int) $param > 0) {
            $tenantId = $this->resolveBomTenantId();
            $query = ProductionBom::withoutGlobalScopes()->where('id', (int) $param);
            if ($tenantId > 0) {
                $query->where('tenant_id', $tenantId);
            }

            return $query->first();
        }

        return null;
    }

    /**
     * Auto-resolve base_uom_id and item uom_id from Product master when omitted,
     * while preserving explicit submitted UoMs for mixed-UoM consistency detection.
     */
    protected function prepareBomAllocationsInput(): void
    {
        $tenantId = $this->resolveBomTenantId();

        $productId = $this->input('product_id') ?? $this->resolveRouteBom()?->product_id;
        if ($productId) {
            $productQuery = Product::withoutGlobalScopes()->where('id', (int) $productId);
            if ($tenantId > 0) {
                $productQuery->where('tenant_id', $tenantId);
            }
            $product = $productQuery->first();
            if ($product && $product->uom_id) {
                $this->merge(['base_uom_id' => $product->uom_id]);
            }
        }

        $items = $this->input('items');
        if (! is_array($items)) {
            return;
        }

        $modified = false;
        $materialIds = collect($items)->pluck('material_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();
        $materials = $materialIds !== []
            ? Product::withoutGlobalScopes()
                ->when($tenantId > 0, fn ($q) => $q->where('tenant_id', $tenantId))
                ->whereIn('id', $materialIds)
                ->get()
                ->keyBy('id')
            : collect();

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (array_key_exists('uom_id', $item) && $item['uom_id'] !== null && $item['uom_id'] !== '') {
                $this->submittedItemUoms[$index] = (int) $item['uom_id'];
            }

            if (! empty($item['material_id'])) {
                $material = $materials->get((int) $item['material_id']);
                if ($material && $material->uom_id && empty($item['uom_id'])) {
                    $items[$index]['uom_id'] = $material->uom_id;
                    $modified = true;
                }
            }

            if (! isset($item['material_scrap_percentage'])) {
                if (isset($item['scrap_factor'])) {
                    $items[$index]['material_scrap_percentage'] = $item['scrap_factor'];
                    $modified = true;
                } elseif (isset($item['wastage_percentage'])) {
                    $items[$index]['material_scrap_percentage'] = $item['wastage_percentage'];
                    $modified = true;
                }
            }

            if (array_key_exists('is_alternative', $item)) {
                $items[$index]['is_alternative'] = filter_var($item['is_alternative'], FILTER_VALIDATE_BOOLEAN);
                $modified = true;
            }
        }

        if ($modified) {
            $this->merge(['items' => $items]);
        }
    }

    protected function bomHeaderRules(bool $isUpdate = false, bool $isApiPartial = false): array
    {
        $req = $isApiPartial ? 'sometimes|required' : 'required';
        $reqArr = $isApiPartial ? ['sometimes', 'required'] : ['required'];
        $prodArr = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'bom_number'      => $isUpdate && ! $isApiPartial ? 'required|string|max:255' : 'nullable|string|max:255',
            'bom_name'        => 'nullable|string|max:255',
            'bom_type'        => "{$req}|in:manufacturing,engineering,sales,phantom,subcontracting",
            'usage_context'   => 'nullable|string|in:manufacturing,engineering,prototype,costing',
            'product_id'      => [...$prodArr, 'integer', $this->tenantExists('products')],
            'routing_id'      => [...$reqArr, 'integer', $this->tenantExists('routings')],
            'base_quantity'   => "{$req}|numeric|gt:0",
            'base_uom_id'     => [...$reqArr, 'integer', $this->tenantExists('uoms')],
            'version'         => ($isApiPartial && $isUpdate ? 'sometimes|required' : 'required') . '|string|max:50',
            'revision_reason' => 'nullable|string|max:1000',
            'effective_date'  => "{$req}|date",
            'expiry_date'     => 'nullable|date|after_or_equal:effective_date',
            'notes'           => 'nullable|string',
            'source_bom_id'   => ['nullable', 'integer', $this->tenantExists('production_boms')], // BM-IMPL-03
        ];
    }

    protected function bomAllocationRules(bool $itemsRequired = true): array
    {
        $req = $itemsRequired ? 'required' : 'required_with:items';

        return [
            'items'                             => $itemsRequired ? 'required|array|min:1' : 'nullable|array|min:1',
            'items.*.id'                        => 'nullable|integer',
            'items.*.material_id'               => [$req, 'integer', $this->tenantExists('products')],
            'items.*.routing_operation_id'      => [$req, 'integer', $this->tenantExists('production_routing_operations')],
            'items.*.child_bom_id'              => ['nullable', 'integer', $this->tenantExists('production_boms')],
            'items.*.quantity'                  => "{$req}|numeric|gt:0",
            'items.*.uom_id'                    => [$req, 'integer', $this->tenantExists('uoms')],
            'items.*.material_scrap_percentage' => 'nullable|numeric|min:0|max:100',
            'items.*.is_alternative'            => 'nullable|boolean',
            'items.*.replaces_item_id'          => 'nullable|integer',
            'items.*.replaces_row_uid'          => 'nullable|string|max:100',
            'items.*.row_uid'                   => 'nullable|string|max:100',
            'items.*.priority'                  => 'nullable|integer|min:1',
            'items.*.sequence'                  => 'nullable|integer|min:1',
            'items.*.notes'                     => 'nullable|string|max:1000',
        ];
    }

    protected function validateBomAllocations($validator, ?ProductionBom $existingBom = null): void
    {
        $tenantId = $this->resolveBomTenantId();
        $productId = (int) ($this->input('product_id') ?? $existingBom?->product_id ?? 0);
        $routingId = (int) ($this->input('routing_id') ?? $existingBom?->routing_id ?? 0);
        $effectiveDate = $this->input('effective_date') ?? ($existingBom?->effective_date?->toDateString());
        $expiryDate = $this->has('expiry_date')
            ? $this->input('expiry_date')
            : $existingBom?->expiry_date?->toDateString();
        $bomType = $this->input('bom_type') ?? $existingBom?->bom_type;

        $routing = null;
        $validOpIds = [];

        if ($routingId > 0) {
            $routing = Routing::withoutGlobalScopes()
                ->when($tenantId > 0, fn ($q) => $q->where('tenant_id', $tenantId))
                ->with('operations')
                ->find($routingId);

            if (! $routing) {
                $validator->errors()->add('routing_id', 'The selected routing does not exist for this tenant.');
            } else {
                $validOpIds = $routing->operations->pluck('id')->all();

                // BM-IMPL-01 #1: also on edit — a BOM keeps its routing only while that routing is still allowed
                if (in_array($routing->status, [Routing::STATUS_HISTORICAL, Routing::STATUS_CANCELLED], true)) {
                    $validator->errors()->add(
                        'routing_id',
                        "Routing {$routing->routing_number} is {$routing->status} and cannot be used for a BOM."
                    );
                }
            }
        }

        if ($productId > 0 && $effectiveDate) {
            try {
                app(BomLifecycleService::class)->validateEffectiveDates(
                    $effectiveDate,
                    $expiryDate,
                    $productId,
                    $existingBom?->id,
                    $tenantId ?: 1,
                    $bomType
                );
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('effective_date', $e->getMessage());
            }
        }

        if (! $this->has('items') || ! is_array($this->input('items'))) {
            return;
        }

        $items = $this->input('items', []);
        $materialNames = \App\Domains\Inventory\Models\Product::withoutGlobalScopes()
            ->whereIn('id', collect($items)->pluck('material_id')->filter()->unique())->pluck('name', 'id');
        $opSeqs = $routing ? $routing->operations->pluck('sequence', 'id') : collect();
        $matName = fn ($id) => $materialNames[(int) $id] ?? ('#' . $id);
        $opSeq = fn ($id) => $opSeqs[(int) $id] ?? $id;
        $materialChildBoms = [];
        $seenMaterialOperationKeys = [];
        $materialUoms = [];
        $allRows = [];
        $primaryCount = 0;
        $bomService = app(ProductionBomService::class);
        $parentEffectiveStr = $effectiveDate ? (string) $effectiveDate : now()->toDateString();

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            // BM-IMPL-09 #7: formula quantities were removed — refuse them clearly instead of ignoring them
            if (! empty($item['formula']) || (isset($item['quantity_type']) && $item['quantity_type'] !== 'fixed')) {
                $validator->errors()->add("items.{$index}.formula", 'Formula quantities are not supported; send a fixed quantity.');
            }

            $materialId = (int) ($item['material_id'] ?? 0);
            $opId = (int) ($item['routing_operation_id'] ?? 0);
            $uomId = (int) ($this->submittedItemUoms[$index] ?? ($item['uom_id'] ?? 0));
            $isAlt = ! empty($item['is_alternative']);

            if (! $isAlt) {
                $primaryCount++;
            }

            if ($routing && $opId > 0 && ! in_array($opId, $validOpIds, true)) {
                $removedOp = RoutingOperation::onlyTrashed()
                    ->where('routing_id', $routing->id)
                    ->find($opId);

                $validator->errors()->add(
                    "items.{$index}.routing_operation_id",
                    $removedOp
                        ? "Operation {$removedOp->operation_number} was removed from the routing. Move or remove its materials before saving."
                        : 'The selected operation does not belong to the chosen routing.'
                );
            }

            if ($materialId > 0) {
                if ($productId > 0 && $materialId === $productId) {
                    $validator->errors()->add(
                        "items.{$index}.material_id",
                        'A finished product cannot be its own component material.'
                    );
                }

                // BM-IMPL-06: a material is a PRIMARY only once per operation (alternatives: checked per replaced primary below)
                if (! $isAlt) {
                    $comboKey = "P_{$opId}_{$materialId}";
                    if (in_array($comboKey, $seenMaterialOperationKeys, true)) {
                        $validator->errors()->add(
                            "items.{$index}.material_id",
                            'Material ' . $matName($materialId) . ' is already a primary material in operation ' . $opSeq($opId) . '.'
                        );
                    } else {
                        $seenMaterialOperationKeys[] = $comboKey;
                    }
                }

                // BM-IMPL-08 #3: one sub-BOM per material — every allocation of the material carries the same choice
                $childChoice = ! empty($item['child_bom_id']) ? (int) $item['child_bom_id'] : 0;
                if (array_key_exists($materialId, $materialChildBoms) && $materialChildBoms[$materialId] !== $childChoice) {
                    $validator->errors()->add(
                        "items.{$index}.child_bom_id",
                        'Material ' . $matName($materialId) . ' uses different sub-BOMs in this BOM — choose one sub-BOM per material.'
                    );
                } else {
                    $materialChildBoms[$materialId] = $childChoice;
                }

                // BM-09: Same material must share the same UoM across all allocations in the BOM
                if ($uomId > 0) {
                    if (isset($materialUoms[$materialId]) && $materialUoms[$materialId] !== $uomId) {
                        $validator->errors()->add(
                            "items.{$index}.uom_id",
                            'All allocations of the same material in a BOM must use the same unit of measure.'
                        );
                    } else {
                        $materialUoms[$materialId] = $uomId;
                    }
                }
            }

            if (! empty($item['child_bom_id']) && $materialId > 0) {
                try {
                    $bomService->validateChildBomLink(
                        (int) $item['child_bom_id'],
                        $materialId,
                        $existingBom?->id,
                        $parentEffectiveStr,
                        $expiryDate ? (string) $expiryDate : null,
                        $tenantId
                    );
                } catch (\InvalidArgumentException $e) {
                    $validator->errors()->add("items.{$index}.child_bom_id", $e->getMessage());
                }
            }

            $rowUid = ! empty($item['row_uid']) ? (string) $item['row_uid'] : null;
            $rowId = ! empty($item['id']) ? (string) $item['id'] : null;
            $rowKey = $rowUid ?: ($rowId ?: "index_{$index}");

            $allRows[$rowKey] = [
                'key'                  => $rowKey,
                'index'                => $index,
                'is_alternative'       => $isAlt,
                'material_id'          => $materialId,
                'routing_operation_id' => $opId,
                'id'                   => $rowId,
                'row_uid'              => $rowUid,
            ];
        }

        if (count($items) > 0 && $primaryCount === 0) {
            $validator->errors()->add('items', 'A BOM must include at least one primary material allocation.');
        }

        // BM-07: Alternative target, same-operation constraint & per-group priority uniqueness
        $seenAltPriorities = [];
        foreach ($items as $index => $item) {
            if (! is_array($item) || empty($item['is_alternative'])) {
                continue;
            }

            $targetRef = $item['replaces_row_uid'] ?? ($item['replaces_item_id'] ?? null);
            $opId = (int) ($item['routing_operation_id'] ?? 0);
            $altMaterialId = (int) ($item['material_id'] ?? 0);

            // BM-IMPL-07 #2: an alternative needs its own priority (no silent default)
            if (! isset($item['priority']) || $item['priority'] === '' || $item['priority'] === null) {
                $validator->errors()->add("items.{$index}.priority", 'Priority is required for an alternative material.');
                continue;
            }

            if (empty($targetRef)) {
                $validator->errors()->add(
                    "items.{$index}.replaces_row_uid",
                    'An alternative component must specify the primary component it replaces.'
                );
                continue;
            }

            $targetKey = (string) $targetRef;
            $targetInfo = null;
            foreach ($allRows as $candidate) {
                if (
                    $candidate['key'] === $targetKey
                    || (string) ($candidate['id'] ?? '') === $targetKey
                    || (string) ($candidate['row_uid'] ?? '') === $targetKey
                ) {
                    $targetInfo = $candidate;
                    break;
                }
            }

            if (! $targetInfo) {
                $validator->errors()->add(
                    "items.{$index}.replaces_row_uid",
                    'The referenced primary component was not found in this BOM.'
                );
                continue;
            }

            if ($targetInfo['index'] === $index) {
                $validator->errors()->add(
                    "items.{$index}.replaces_row_uid",
                    'A component cannot be an alternative of itself.'
                );
                continue;
            }

            if ($targetInfo['is_alternative']) {
                $validator->errors()->add(
                    "items.{$index}.replaces_row_uid",
                    'An alternative component cannot reference another alternative component.'
                );
                continue;
            }

            if ($opId > 0 && (int) $targetInfo['routing_operation_id'] !== $opId) {
                $validator->errors()->add(
                    "items.{$index}.replaces_row_uid",
                    'The alternative must be in the same operation as the primary it replaces (Op ' . $opSeq($targetInfo['routing_operation_id']) . ').'
                );
                continue;
            }

            // BM-IMPL-06: never an alternative of itself; a primary has a given material as its alternative only once
            if ($altMaterialId > 0 && $altMaterialId === (int) $targetInfo['material_id']) {
                $validator->errors()->add("items.{$index}.material_id", 'A material cannot be an alternative of itself.');
                continue;
            }
            $altKey = "A_{$opId}_{$targetInfo['key']}_{$altMaterialId}";
            if (in_array($altKey, $seenMaterialOperationKeys, true)) {
                $validator->errors()->add(
                    "items.{$index}.material_id",
                    'Material ' . $matName($altMaterialId) . ' is already an alternative for ' . $matName($targetInfo['material_id']) . ' in operation ' . $opSeq($opId) . '.'
                );
                continue;
            }
            $seenMaterialOperationKeys[] = $altKey;

            $priority = (int) ($item['priority'] ?? 1);
            $priorityGroupKey = "{$opId}_{$targetInfo['key']}";
            if (! isset($seenAltPriorities[$priorityGroupKey])) {
                $seenAltPriorities[$priorityGroupKey] = [];
            }

            if (in_array($priority, $seenAltPriorities[$priorityGroupKey], true)) {
                $validator->errors()->add(
                    "items.{$index}.priority",
                    'Priority ' . $priority . ' is already used for an alternative of ' . $matName($targetInfo['material_id']) . '.'
                );
            } else {
                $seenAltPriorities[$priorityGroupKey][] = $priority;
            }
        }
    }
}

