<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenterSection;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * PO-IMPL-03 — Production Order Create engineering resolution (PO-01, PO-02, PO-04, PO-06, PO-07, PO-08, PO-10).
 *
 *  - requiresEngineering(): BOM / Routing are needed only for products made in-house (supplier_method = manufacture).
 *  - resolveAndValidate(): active BOM → its Routing (ProductionBom::routing(), never routings.product_id), blocking
 *    checks (BOM / sub-BOM / Routing status + dates, cost status, live resource health) and the Tab 1 selection
 *    (one operation per stage, one material per slot of every chosen operation).
 *  - getBranchAvailableStockMap(): Branch Available = SUM(max(0, quantity - reserved_qty)) over the branch's active
 *    standard warehouses (PO-04).
 *  - computeMultiLevelPlan(): level-order (BFS) explosion — child BOMs explode only for their shortage and use
 *    primary operations + primary materials only (PO-08, PO-10). Same math as the Create screen Tab 2.
 */
class ProductionOrderEngineeringService
{
    private const BOM_TYPES = ['manufacturing', 'subcontracting'];
    private const MAX_LEVELS = 20;

    public function __construct(
        private readonly RoutingCostStatusService $costStatusService,
        private readonly SectionCalculationService $sectionCalculationService,
    ) {}

    // ------------------------------------------------------------------ PO-02

    public function requiresEngineering(Product $product): bool
    {
        return strtolower((string) $product->supplier_method) === 'manufacture';
    }

    // ------------------------------------------------------------------ PO-04

    /** Active standard warehouses of the branch (tenant-wide only when no branch is known). */
    public function branchWarehouseIds(int $tenantId, ?int $branchId): array
    {
        return Warehouse::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', Warehouse::TYPE_STANDARD)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, float> product_id => branch available (net of reserved_qty, never negative per warehouse)
     */
    public function getBranchAvailableStockMap(int $tenantId, ?int $branchId, array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $map = array_fill_keys($productIds, 0.0);
        if ($productIds === []) {
            return [];
        }

        $warehouseIds = $this->branchWarehouseIds($tenantId, $branchId);
        if ($warehouseIds === []) {
            return $map;
        }

        $rows = ProductWarehouseStock::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'quantity', 'reserved_qty']);

        foreach ($rows as $row) {
            $map[(int) $row->product_id] += max(0.0, (float) $row->quantity - (float) $row->reserved_qty);
        }

        return array_map(fn ($v) => round($v, 6), $map);
    }

    /** Branch standard warehouse holding the most net stock of the product, else the branch default / first one. */
    public function resolvePrimaryBranchWarehouseId(int $tenantId, int $productId, ?int $branchId): ?int
    {
        $warehouseIds = $this->branchWarehouseIds($tenantId, $branchId);
        if ($warehouseIds === []) {
            return null;
        }

        $best = null;
        $bestQty = 0.0;
        $rows = ProductWarehouseStock::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->get(['warehouse_id', 'quantity', 'reserved_qty']);
        foreach ($rows as $row) {
            $net = max(0.0, (float) $row->quantity - (float) $row->reserved_qty);
            if ($net > $bestQty) {
                $bestQty = $net;
                $best = (int) $row->warehouse_id;
            }
        }

        return $best ?? $warehouseIds[0];
    }

    // ------------------------------------------------------------------ PO-01 / PO-02 / PO-07

    /**
     * @return array{requires_engineering: bool, can_create_order: bool, bom: ?ProductionBom, routing: ?Routing,
     *   bom_status: string, routing_status: string, errors: array<string, string[]>, warnings: string[],
     *   stages: array, selected_operation_ids: int[], selected_material_ids: int[]}
     */
    public function resolveAndValidate(
        Product $product,
        int $tenantId,
        ?int $requestedBomId = null,
        ?int $requestedRoutingId = null,
        ?array $selectedOperationIds = null,
        ?array $selectedMaterialIds = null,
        bool $requireExplicitSelections = false
    ): array {
        $result = [
            'requires_engineering'   => false,
            'can_create_order'       => true,
            'bom'                    => null,
            'routing'                => null,
            'bom_status'             => 'not_required',
            'routing_status'         => 'not_required',
            'errors'                 => [],
            'warnings'               => [],
            'stages'                 => [],
            'selected_operation_ids' => [],
            'selected_material_ids'  => [],
        ];

        if (!$this->requiresEngineering($product)) {
            return $result;
        }

        $result['requires_engineering'] = true;
        $errors = [];
        $add = function (string $field, string $message) use (&$errors): void {
            $errors[$field][] = $message;
        };

        // ---- BOM -------------------------------------------------------------------------------------------
        $bom = $this->resolveActiveBom($product, $tenantId);
        if ($requestedBomId) {
            $requested = ProductionBom::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($requestedBomId);
            if (!$requested || (int) $requested->product_id !== (int) $product->id) {
                $add('bom_id', 'The selected BOM does not belong to this product.');
                $bom = null;
            } elseif (in_array($requested->bom_type, ['engineering', 'sales'], true)) {
                $add('bom_id', "BOM {$requested->bom_number} is a " . ucfirst($requested->bom_type) . ' BOM and cannot be used for a Production Order.');
                $bom = null;
            } elseif (!$requested->isCurrentlyActive()) {
                $add('bom_id', $this->bomInactiveMessage($requested));
                $bom = null;
            } else {
                $bom = $requested;
            }
        } elseif (!$bom) {
            $add('bom_id', $this->missingBomMessage($product, $tenantId));
        }

        if ($bom) {
            $result['bom'] = $bom;
            $result['bom_status'] = 'active';
            foreach ($this->bomTreeProblems($bom, $tenantId) as $problem) {
                $add('bom_id', $problem);
            }
        } else {
            $result['bom_status'] = 'blocked';
        }

        // ---- Routing ---------------------------------------------------------------------------------------
        $routing = $bom ? Routing::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($bom->routing_id) : null;
        if ($bom && !$routing) {
            $add('routing_id', "BOM {$bom->bom_number} has no Process Routing.");
        }
        if ($routing && $requestedRoutingId && (int) $requestedRoutingId !== (int) $routing->id) {
            $add('routing_id', "The selected Routing does not match the Routing of BOM {$bom->bom_number} ({$routing->routing_number}).");
        }
        if ($routing) {
            $result['routing'] = $routing;
            foreach ($this->routingProblems($routing) as $problem) {
                $add('routing_id', $problem);
            }
            $result['routing_status'] = isset($errors['routing_id']) ? 'blocked' : 'active';
        } else {
            $result['routing_status'] = 'blocked';
        }

        // ---- Selection (PO-06 / PO-07) ---------------------------------------------------------------------
        if ($bom && $routing) {
            $stages = $this->buildStages($bom, $routing);
            $result['stages'] = $stages;

            $explicit = $requireExplicitSelections || $selectedOperationIds !== null || $selectedMaterialIds !== null;
            if (!$explicit) {
                [$opIds, $matIds] = $this->defaultSelection($stages);
            } else {
                $opIds = array_map('intval', $selectedOperationIds ?? []);
                $matIds = array_map('intval', $selectedMaterialIds ?? []);
                foreach ($this->selectionProblems($stages, $opIds, $matIds, $routing) as [$field, $message]) {
                    $add($field, $message);
                }
            }
            $result['selected_operation_ids'] = array_values(array_unique($opIds));
            $result['selected_material_ids'] = array_values(array_unique($matIds));
        }

        $result['errors'] = $errors;
        $result['can_create_order'] = $errors === [];

        return $result;
    }

    /** Throws the first blocking reason (service-level guard for createDirect / createFromPlan). */
    public function assertCanCreate(array $resolution): void
    {
        if (!empty($resolution['can_create_order'])) {
            return;
        }
        $messages = collect($resolution['errors'])->flatten()->unique()->values();
        throw new InvalidArgumentException('Cannot create production order: ' . $messages->implode(' '));
    }

    public function resolveActiveBom(Product $product, int $tenantId): ?ProductionBom
    {
        return ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->whereIn('bom_type', self::BOM_TYPES)
            ->active()
            ->reorder()
            ->orderByRaw("CASE WHEN bom_type = 'manufacturing' THEN 1 ELSE 2 END")
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->first(fn (ProductionBom $b) => $b->isCurrentlyActive());
    }

    private function missingBomMessage(Product $product, int $tenantId): string
    {
        $latest = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->whereIn('bom_type', self::BOM_TYPES)
            ->orderByDesc('id')
            ->first();

        if (!$latest) {
            return "No Manufacturing or Subcontracting BOM exists for {$product->name}. Create and approve a BOM first.";
        }

        return $this->bomInactiveMessage($latest);
    }

    private function bomInactiveMessage(ProductionBom $bom): string
    {
        $label = "BOM {$bom->bom_number} v{$bom->version}";
        $today = now()->toDateString();
        if (in_array($bom->status, ['approved', 'active', 'scheduled'], true)) {
            if ($bom->effective_date && $bom->effective_date->toDateString() > $today) {
                return "{$label} starts on {$bom->effective_date->toDateString()}; it cannot be used before then.";
            }
            if ($bom->expiry_date && $bom->expiry_date->toDateString() < $today) {
                return "{$label} expired on {$bom->expiry_date->toDateString()}.";
            }
        }

        return "{$label} is " . str_replace('_', ' ', $bom->status) . '; only an approved, active BOM can be used.';
    }

    /** Component warnings, blocked sub-BOMs (BM-08) and every reachable child BOM + its routing. */
    private function bomTreeProblems(ProductionBom $bom, int $tenantId, array &$visited = [], int $depth = 0): array
    {
        if (isset($visited[$bom->id]) || $depth > self::MAX_LEVELS) {
            return [];
        }
        $visited[$bom->id] = true;
        $problems = [];

        foreach ($bom->getComponentWarnings() as $warning) {
            if (in_array($warning['type'] ?? '', ['cancelled', 'inactive', 'missing', 'expired'], true)) {
                $problems[] = $warning['message'] ?? 'A sub-BOM used by this BOM is not active.';
            }
        }

        try {
            $tmp = [];
            app(ProductionBomService::class)->validateBomTreeForExecution($bom, $tenantId, $tmp);
        } catch (InvalidArgumentException $e) {
            $problems[] = $e->getMessage();
        }

        foreach ($this->primaryAllocations($bom) as $row) {
            if (!$row->child_bom_id) {
                continue;
            }
            $child = ProductionBom::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($row->child_bom_id);
            if (!$child || isset($visited[$child->id])) {
                continue;
            }
            if (!$child->isCurrentlyActive()) {
                $problems[] = 'Sub-' . $this->bomInactiveMessage($child);
                continue;
            }
            $childRouting = $child->routing_id ? Routing::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($child->routing_id) : null;
            if ($childRouting) {
                foreach ($this->routingProblems($childRouting) as $p) {
                    $problems[] = "Sub-BOM {$child->bom_number}: {$p}";
                }
            } elseif ($child->operationMaterials()->exists()) {
                $problems[] = "Sub-BOM {$child->bom_number} has no Process Routing.";
            }
            $problems = array_merge($problems, $this->bomTreeProblems($child, $tenantId, $visited, $depth + 1));
        }

        return array_values(array_unique($problems));
    }

    /** Status / dates, cost status (RT-IMPL-14) and live resource health (RT-IMPL-18) of a routing. */
    public function routingProblems(Routing $routing): array
    {
        $problems = [];
        $label = "Routing {$routing->routing_number}";
        $today = now()->toDateString();

        if (!$routing->isCurrentlyActive()) {
            if (in_array($routing->status, [Routing::STATUS_ACTIVE, Routing::STATUS_SCHEDULED], true)
                && $routing->effective_from && $routing->effective_from->toDateString() > $today) {
                $problems[] = "{$label} starts on {$routing->effective_from->toDateString()}; it cannot be used before then.";
            } elseif ($routing->effective_to && $routing->effective_to->toDateString() < $today) {
                $problems[] = "{$label} ended on {$routing->effective_to->toDateString()}.";
            } else {
                $problems[] = "{$label} is " . str_replace('_', ' ', (string) $routing->status) . '; only an active Routing can be used.';
            }

            return $problems;
        }

        $status = $this->costStatusService->check($routing);
        if (!empty($status['not_calculated'])) {
            $problems[] = "{$label}: costs are not calculated — open and save the routing.";
        }
        foreach ($status['operations'] ?? [] as $op) {
            foreach ($op['messages'] as $m) {
                if (!str_starts_with($m, 'Costs not calculated')) {
                    $problems[] = "{$label} {$op['operation_number']}: {$m}";
                }
            }
        }
        foreach ($status['inactive'] ?? [] as $row) {
            $problems[] = "{$label}: {$row['message']}";
        }

        $sections = [];
        foreach ($routing->operations as $op) {
            if (!$op->is_external && $op->section_id) {
                $sections[$op->section_id][] = $op->operation_number;
            }
        }
        foreach ($sections as $sectionId => $opNumbers) {
            $section = WorkCenterSection::find($sectionId);
            if (!$section || $section->status !== 'active') {
                continue; // reported by inactiveResources()
            }
            $meta = $this->sectionCalculationService->getSnapshotResourceCosts($section);
            if (!empty($meta['not_calculated'])) {
                $problems[] = "{$label}: section {$section->code} is not calculated yet.";
            } elseif (!empty($meta['has_pending_changes'])) {
                $problems[] = "{$label}: section {$section->code} has changes waiting for recalculation.";
            }
        }

        return array_values(array_unique($problems));
    }

    // ------------------------------------------------------------------ Stages & selection

    /**
     * Stage = primary operation + its alternatives (priority order); slot = primary material + its alternatives.
     */
    public function buildStages(ProductionBom $bom, Routing $routing): array
    {
        $routing->loadMissing(['operations.workCenter', 'operations.section', 'operations.machine', 'operations.manualProduction']);
        $ops = $routing->operations->sortBy('sequence')->values();
        $allocations = RoutingOperationMaterial::withoutGlobalScopes()
            ->where('bom_id', $bom->id)
            ->whereIn('routing_operation_id', $ops->pluck('id'))
            ->with(['material.uom', 'uom', 'childBom'])
            ->orderBy('sequence')
            ->orderBy('id')
            ->get()
            ->groupBy('routing_operation_id');

        $primaries = $ops->where('is_alternative', false)->values();
        $alternatives = $ops->where('is_alternative', true)->groupBy('replaces_operation_id');

        $stages = [];
        foreach ($primaries as $primary) {
            $stageOps = collect([$primary])->merge(
                ($alternatives->get($primary->id) ?? collect())->sortBy(fn ($o) => [$o->priority ?? 999, $o->sequence])->values()
            );
            $stages[] = [
                'stage_key'           => 'S' . $primary->id,
                'primary_operation_id' => $primary->id,
                'operation_number'    => $primary->operation_number,
                'name'                => $primary->name,
                'sequence'            => $primary->sequence,
                'has_alternative_ops' => $stageOps->count() > 1,
                'operations'          => $stageOps->map(fn (RoutingOperation $op) => $this->operationPayload($op, $allocations->get($op->id) ?? collect(), $bom))->values()->all(),
            ];
        }

        return $stages;
    }

    private function operationPayload(RoutingOperation $op, Collection $rows, ProductionBom $bom): array
    {
        $rows = $rows->filter(fn ($r) => (int) $r->material_id !== (int) $bom->product_id);
        $primaryRows = $rows->where('is_alternative', false)->values();
        $altRows = $rows->where('is_alternative', true)->groupBy('replaces_routing_material_id');

        $slots = [];
        foreach ($primaryRows as $primary) {
            $options = collect([$primary])->merge(
                ($altRows->get($primary->id) ?? collect())->sortBy(fn ($r) => [$r->priority ?? 999, $r->id])->values()
            );
            $slots[] = [
                'slot_key'            => 'M' . $primary->id,
                'primary_material_id' => $primary->id,
                'has_alternatives'    => $options->count() > 1,
                'options'             => $options->map(fn ($r) => $this->materialPayload($r))->values()->all(),
            ];
        }

        return [
            'id'                         => $op->id,
            'operation_number'           => $op->operation_number,
            'name'                       => $op->name,
            'sequence'                   => $op->sequence,
            'is_alternative'             => (bool) $op->is_alternative,
            'priority'                   => $op->priority,
            'is_external'                => (bool) $op->is_external,
            'work_center'                => $op->workCenter?->name,
            'work_center_code'           => $op->workCenter?->code,
            'section'                    => $op->section?->name,
            'resource'                   => $op->machine?->name ?? $op->manualProduction?->batch_name,
            'setup_time_minutes'         => (float) $op->setup_time_minutes,
            'setup_time_per_piece_minutes' => (float) ($op->setup_time_per_piece_minutes ?? 0),
            'processing_time_minutes'    => (float) $op->processing_time_minutes,
            'expected_yield_percentage'  => $op->expected_yield_percentage !== null ? (float) $op->expected_yield_percentage : null,
            'material_slots'             => $slots,
            'default_selected_material_ids' => array_map(fn ($s) => $s['primary_material_id'], $slots),
        ];
    }

    private function materialPayload(RoutingOperationMaterial $r): array
    {
        $qty = (float) $r->quantity;
        $scrap = (float) ($r->material_scrap_percentage ?? 0);

        return [
            'id'                        => $r->id,
            'material_id'               => $r->material_id,
            'name'                      => $r->material?->name ?? ('#' . $r->material_id),
            'sku'                       => $r->material?->sku,
            'type'                      => $r->material?->type,
            'uom'                       => $r->uom?->code ?? $r->material?->uom?->code ?? 'PCS',
            'uom_id'                    => $r->uom_id ?? $r->material?->uom_id,
            'quantity'                  => $qty,
            'material_scrap_percentage' => $scrap,
            'gross_quantity'            => $this->grossUnit($r),
            'is_alternative'            => (bool) $r->is_alternative,
            'priority'                  => $r->priority,
            'child_bom_id'              => $r->child_bom_id,
            'child_bom_number'          => $r->childBom?->bom_number,
        ];
    }

    /** @return array{0:int[],1:int[]} primary op of every stage + primary material of every slot (all ops). */
    public function defaultSelection(array $stages): array
    {
        $opIds = [];
        $matIds = [];
        foreach ($stages as $stage) {
            $opIds[] = $stage['primary_operation_id'];
            foreach ($stage['operations'] as $op) {
                if ((int) $op['id'] === (int) $stage['primary_operation_id']) {
                    foreach ($op['material_slots'] as $slot) {
                        $matIds[] = $slot['primary_material_id'];
                    }
                }
            }
        }

        return [$opIds, $matIds];
    }

    /** @return array<int, array{0:string,1:string}> */
    private function selectionProblems(array $stages, array $opIds, array $matIds, Routing $routing): array
    {
        $problems = [];
        $opIndex = [];   // op id => stage
        $matIndex = [];  // material row id => [op id, slot]
        foreach ($stages as $stage) {
            foreach ($stage['operations'] as $op) {
                $opIndex[$op['id']] = $stage;
                foreach ($op['material_slots'] as $slot) {
                    foreach ($slot['options'] as $opt) {
                        $matIndex[$opt['id']] = [$op, $slot];
                    }
                }
            }
        }

        if (count($opIds) !== count(array_unique($opIds))) {
            $problems[] = ['selected_operation_ids', 'An operation was selected more than once.'];
        }
        foreach (array_unique($opIds) as $id) {
            if (!isset($opIndex[$id])) {
                $problems[] = ['selected_operation_ids', "Operation #{$id} is not part of Routing {$routing->routing_number}."];
            }
        }

        $chosenOps = [];
        foreach ($stages as $stage) {
            $picked = array_values(array_filter(array_unique($opIds), fn ($id) => isset($opIndex[$id]) && $opIndex[$id]['stage_key'] === $stage['stage_key']));
            if (count($picked) === 0) {
                $problems[] = ['selected_operation_ids', "Stage {$stage['operation_number']} ({$stage['name']}): choose an operation (primary or alternative)."];
            } elseif (count($picked) > 1) {
                $problems[] = ['selected_operation_ids', "Stage {$stage['operation_number']} ({$stage['name']}): only one operation can be chosen."];
            } else {
                $chosenOps[$picked[0]] = collect($stage['operations'])->firstWhere('id', $picked[0]);
            }
        }

        if (count($matIds) !== count(array_unique($matIds))) {
            $problems[] = ['selected_material_ids', 'A material was selected more than once.'];
        }
        foreach (array_unique($matIds) as $id) {
            if (!isset($matIndex[$id])) {
                $problems[] = ['selected_material_ids', "Material allocation #{$id} does not belong to this BOM's operations."];
                continue;
            }
            [$op] = $matIndex[$id];
            if (!isset($chosenOps[$op['id']])) {
                $name = collect($matIndex[$id][1]['options'])->firstWhere('id', $id)['name'] ?? "#{$id}";
                $problems[] = ['selected_material_ids', "{$name} belongs to operation {$op['operation_number']}, which is not selected."];
            }
        }

        foreach ($chosenOps as $op) {
            foreach ($op['material_slots'] as $slot) {
                $optionIds = array_column($slot['options'], 'id');
                $picked = array_values(array_intersect($optionIds, array_unique($matIds)));
                $primaryName = $slot['options'][0]['name'];
                if (count($picked) === 0) {
                    $problems[] = ['selected_material_ids', "Operation {$op['operation_number']}: choose a material for {$primaryName} (primary or alternative)."];
                } elseif (count($picked) > 1) {
                    $problems[] = ['selected_material_ids', "Operation {$op['operation_number']}: only one material can be chosen for {$primaryName}."];
                }
            }
        }

        return $problems;
    }

    // ------------------------------------------------------------------ PO-08 / PO-10 multi-level plan

    /**
     * @return array{rows: array, child_summaries: array, consolidated_materials: array, snapshot_tree_operations: array}
     */
    public function computeMultiLevelPlan(ProductionBom $parentBom, array $chosenOpIds, array $chosenMaterialIds, float $orderQty, int $tenantId, ?int $branchId): array
    {
        $chosenOpIds = array_map('intval', $chosenOpIds);
        $chosenMaterialIds = array_map('intval', $chosenMaterialIds);
        $rows = [];
        $stock = [];
        $stockFor = function (int $productId) use (&$stock, $tenantId, $branchId): float {
            if (!array_key_exists($productId, $stock)) {
                $stock += $this->getBranchAvailableStockMap($tenantId, $branchId, [$productId]) + [$productId => 0.0];
            }

            return (float) $stock[$productId];
        };
        $ensureRow = function ($material, ?int $uomId, ?int $childBomId) use (&$rows) {
            $id = (int) $material->id;
            if (!isset($rows[$id])) {
                $rows[$id] = [
                    'product_id'     => $id,
                    'name'           => $material->name,
                    'sku'            => $material->sku,
                    'type'           => $material->type,
                    'uom'            => $material->uom?->code ?? 'PCS',
                    'uom_id'         => $uomId ?? $material->uom_id,
                    'child_bom_id'   => $childBomId,
                    'bom_item_id'    => null,
                    'parent_qty'     => 0.0,
                    'child_qty'      => 0.0,
                    'parent_sources' => [],
                    'child_sources'  => [],
                ];
            }
            if ($childBomId && !$rows[$id]['child_bom_id']) {
                $rows[$id]['child_bom_id'] = $childBomId;
            }

            return $id;
        };

        $pending = [];   // child_bom_id => [product_id, level, required (not yet processed), requested_by[]]
        $register = function (int $childBomId, int $productId, int $level, float $qty, string $by) use (&$pending): void {
            if (!isset($pending[$childBomId])) {
                $pending[$childBomId] = ['product_id' => $productId, 'level' => $level, 'queued' => 0.0, 'requested_by' => []];
            }
            $pending[$childBomId]['queued'] += $qty;
            $pending[$childBomId]['level'] = max($pending[$childBomId]['level'], $level);
            $pending[$childBomId]['requested_by'][] = ['by' => $by, 'qty' => $qty];
        };

        $snapshotOps = [];
        $parentRouting = $parentBom->routing_id ? Routing::withoutGlobalScopes()->find($parentBom->routing_id) : null;
        $parentScale = $orderQty / ((float) $parentBom->base_quantity > 0 ? (float) $parentBom->base_quantity : 1.0);

        // ---- Level 1: selected parent operations + selected materials
        $parentAllocs = RoutingOperationMaterial::withoutGlobalScopes()
            ->where('bom_id', $parentBom->id)
            ->with(['material.uom', 'routingOperation'])
            ->get();

        if ($parentAllocs->isEmpty()) {
            // Legacy BOM with master rows only — no operation mapping
            foreach ($parentBom->items()->with('material.uom')->get() as $item) {
                if (!$item->material || (int) $item->material_id === (int) $parentBom->product_id) {
                    continue;
                }
                $net = (float) $item->quantity * $parentScale;
                $gross = $net * (1 + (float) ($item->material_scrap_percentage ?? 0) / 100);
                $pid = $ensureRow($item->material, $item->uom_id, $item->child_bom_id);
                $rows[$pid]['parent_qty'] += $gross;
                $rows[$pid]['bom_item_id'] ??= $item->id;
                $rows[$pid]['parent_sources'][] = ['level' => 1, 'bom_id' => $parentBom->id, 'operation_id' => null, 'operation' => null, 'selection' => 'Primary', 'net_qty' => $net, 'scrap_pct' => (float) ($item->material_scrap_percentage ?? 0), 'gross_qty' => $gross];
                if ($item->child_bom_id) {
                    $register((int) $item->child_bom_id, $pid, 2, $gross, $parentBom->bom_number);
                }
            }
        }

        if ($parentRouting) {
            foreach ($parentRouting->operations()->whereIn('id', $chosenOpIds)->orderBy('sequence')->get() as $op) {
                $selected = $parentAllocs->where('routing_operation_id', $op->id)->filter(fn ($a) => in_array((int) $a->id, $chosenMaterialIds, true));
                $snapshotOps[] = $this->snapshotEntry($op, $parentBom, $parentRouting, $parentBom->product_id, 1, $orderQty, false, $selected->pluck('id')->map(fn ($v) => (int) $v)->values()->all());

                foreach ($selected as $alloc) {
                    if (!$alloc->material || (int) $alloc->material_id === (int) $parentBom->product_id) {
                        continue;
                    }
                    $net = (float) $alloc->quantity * $parentScale;
                    $gross = $this->grossUnit($alloc) * $parentScale;
                    $pid = $ensureRow($alloc->material, $alloc->uom_id, $alloc->child_bom_id);
                    $rows[$pid]['parent_qty'] += $gross;
                    $rows[$pid]['bom_item_id'] ??= $alloc->bom_item_id;
                    $rows[$pid]['parent_sources'][] = [
                        'level'        => 1,
                        'bom_id'       => $parentBom->id,
                        'operation_id' => $op->id,
                        'operation'    => trim($op->operation_number . ' · ' . $op->name),
                        'selection'    => ($op->is_alternative ? 'Alt Op' : 'Primary Op') . ' / ' . ($alloc->is_alternative ? 'Alt (P' . $alloc->priority . ')' : 'Primary'),
                        'net_qty'      => $net,
                        'scrap_pct'    => (float) ($alloc->material_scrap_percentage ?? 0),
                        'gross_qty'    => $gross,
                    ];
                    if ($alloc->child_bom_id) {
                        $register((int) $alloc->child_bom_id, $pid, 2, $gross, $parentBom->bom_number);
                    }
                }
            }
        }

        // ---- Level 2+: BFS over child BOMs, primary operations + primary materials only
        $remaining = [];
        $summaries = [];
        $snapshotIndex = [];  // child bom id => [snapshot op indexes]
        for ($level = 2; $level <= self::MAX_LEVELS + 1; $level++) {
            $batch = array_filter($pending, fn ($p) => $p['level'] <= $level && $p['queued'] > 0);
            if ($batch === []) {
                if (collect($pending)->every(fn ($p) => $p['queued'] <= 0)) {
                    break;
                }
                continue;
            }
            if ($level > self::MAX_LEVELS) {
                throw new InvalidArgumentException('BOM tree is deeper than ' . self::MAX_LEVELS . ' levels or contains a loop.');
            }

            foreach ($batch as $childBomId => $entry) {
                $qty = $entry['queued'];
                $pending[$childBomId]['queued'] = 0.0;
                $productId = $entry['product_id'];
                $remaining[$productId] ??= $stockFor($productId);
                $fromStock = min($qty, max(0.0, $remaining[$productId]));
                $remaining[$productId] -= $fromStock;
                $toProduce = max(0.0, $qty - $fromStock);

                $child = ProductionBom::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($childBomId);
                $summaries[$childBomId] ??= [
                    'child_bom_id' => $childBomId,
                    'bom_number'   => $child?->bom_number,
                    'version'      => $child?->version,
                    'status'       => $child?->status,
                    'product_id'   => $productId,
                    'level'        => $level,
                    'required_qty' => 0.0,
                    'branch_avail' => $stockFor($productId),
                    'from_stock'   => 0.0,
                    'to_produce'   => 0.0,
                    'requested_by' => [],
                ];
                $summaries[$childBomId]['required_qty'] += $qty;
                $summaries[$childBomId]['from_stock'] += $fromStock;
                $summaries[$childBomId]['to_produce'] += $toProduce;
                $summaries[$childBomId]['requested_by'] = $entry['requested_by'];

                if ($toProduce <= 0 || !$child) {
                    continue;
                }

                $cScale = $toProduce / ((float) $child->base_quantity > 0 ? (float) $child->base_quantity : 1.0);
                $childRouting = $child->routing_id ? Routing::withoutGlobalScopes()->find($child->routing_id) : null;
                $childAllocs = RoutingOperationMaterial::withoutGlobalScopes()
                    ->where('bom_id', $child->id)
                    ->where('is_alternative', false)
                    ->with(['material.uom', 'routingOperation'])
                    ->get()
                    ->filter(fn ($a) => !$a->routingOperation || !$a->routingOperation->is_alternative);

                $lines = $childAllocs->isNotEmpty()
                    ? $childAllocs
                    : $child->items()->with('material.uom')->get();

                if ($childRouting) {
                    foreach ($childRouting->operations()->where('is_alternative', false)->orderBy('sequence')->get() as $cOp) {
                        if (isset($snapshotIndex[$childBomId][$cOp->id])) {
                            $snapshotOps[$snapshotIndex[$childBomId][$cOp->id]]['target_produced_qty'] += $toProduce;
                            continue;
                        }
                        $snapshotIndex[$childBomId][$cOp->id] = count($snapshotOps);
                        $snapshotOps[] = $this->snapshotEntry($cOp, $child, $childRouting, $productId, $level, $toProduce, true, $childAllocs->where('routing_operation_id', $cOp->id)->pluck('id')->map(fn ($v) => (int) $v)->values()->all());
                    }
                }

                foreach ($lines as $line) {
                    if (!$line->material || (int) $line->material_id === (int) $child->product_id) {
                        continue;
                    }
                    $net = (float) $line->quantity * $cScale;
                    $gross = $this->grossUnit($line) * $cScale;
                    $pid = $ensureRow($line->material, $line->uom_id, $line->child_bom_id);
                    $rows[$pid]['child_qty'] += $gross;
                    $rows[$pid]['bom_item_id'] ??= $line instanceof RoutingOperationMaterial ? $line->bom_item_id : $line->id;
                    $cOpModel = $line instanceof RoutingOperationMaterial ? $line->routingOperation : null;
                    $rows[$pid]['child_sources'][] = [
                        'level'         => $level,
                        'bom_id'        => $child->id,
                        'bom_number'    => $child->bom_number,
                        'producing_qty' => $toProduce,
                        'operation_id'  => $cOpModel?->id,
                        'operation'     => $cOpModel ? trim($cOpModel->operation_number . ' · ' . $cOpModel->name) : null,
                        'selection'     => 'Primary Auto',
                        'net_qty'       => $net,
                        'scrap_pct'     => (float) ($line->material_scrap_percentage ?? 0),
                        'gross_qty'     => $gross,
                    ];
                    if ($line->child_bom_id) {
                        $register((int) $line->child_bom_id, $pid, $level + 1, $gross, $child->bom_number);
                    }
                }
            }
        }

        // ---- Consolidation: one row per product, branch stock pooled once
        $consolidated = [];
        foreach ($rows as $pid => &$row) {
            $row['total_req'] = $row['parent_qty'] + $row['child_qty'];
            $row['branch_avail'] = $stockFor($pid);
            $row['from_stock'] = min($row['total_req'], $row['branch_avail']);
            $row['shortage'] = max(0.0, $row['total_req'] - $row['branch_avail']);
            $produced = $row['child_bom_id'] && isset($summaries[$row['child_bom_id']]);
            $row['to_produce'] = $produced ? $summaries[$row['child_bom_id']]['to_produce'] : 0.0;

            // Semi-finished with a child BOM: reserve what comes from stock; the rest is produced in this order.
            $planned = $produced ? $row['from_stock'] : $row['total_req'];
            if ($planned > 0) {
                $consolidated[] = [
                    'product_id'       => $pid,
                    'quantity_planned' => round($planned, 6),
                    'uom_id'           => $row['uom_id'],
                    'bom_item_id'      => $row['bom_item_id'],
                    'child_bom_id'     => $row['child_bom_id'],
                ];
            }
        }
        unset($row);

        return [
            'rows'                     => array_values($rows),
            'child_summaries'          => $summaries,
            'consolidated_materials'   => $consolidated,
            'snapshot_tree_operations' => $snapshotOps,
        ];
    }

    private function snapshotEntry(RoutingOperation $op, ProductionBom $bom, Routing $routing, int $productId, int $level, float $target, bool $intermediate, array $materialIds): array
    {
        return [
            'routing_operation'             => $op,
            'routing_operation_id'          => $op->id,
            'source_bom_id'                 => $bom->id,
            'source_routing_id'             => $routing->id,
            'source_product_id'             => $productId,
            'bom_level'                     => $level,
            'target_produced_qty'           => $target,
            'is_intermediate'               => $intermediate,
            'selected_routing_material_ids' => $materialIds,
        ];
    }

    // ------------------------------------------------------------------ Workspace payload (Create screen + API)

    public function buildInteractiveWorkspacePayload(Product $product, int $tenantId, ?int $branchId, float $quantity = 1.0): array
    {
        $resolution = $this->resolveAndValidate($product, $tenantId);
        $bom = $resolution['bom'];
        $routing = $resolution['routing'];

        $payload = [
            'validation' => [
                'requires_engineering' => $resolution['requires_engineering'],
                'can_create_order'     => $resolution['can_create_order'],
                'bom_status'           => $resolution['bom_status'],
                'routing_status'       => $resolution['routing_status'],
                'errors'               => $resolution['errors'],
                'blocking_reasons'     => collect($resolution['errors'])->flatten()->unique()->values()->all(),
                'warnings'             => $resolution['warnings'],
            ],
            'bom' => $bom ? [
                'id'            => $bom->id,
                'bom_number'    => $bom->bom_number,
                'bom_name'      => $bom->bom_name,
                'version'       => $bom->version,
                'status'        => $bom->status,
                'base_quantity' => (float) $bom->base_quantity ?: 1.0,
            ] : null,
            'routing' => $routing ? [
                'id'             => $routing->id,
                'routing_number' => $routing->routing_number,
                'name'           => $routing->name,
                'version'        => $routing->version,
                'status'         => $routing->status,
                'cost_status'    => $resolution['routing_status'] === 'active' ? 'calculated' : 'blocked',
            ] : null,
            'parent_workspace'            => ['stages' => $resolution['stages']],
            'child_boms'                  => [],
            'branch_available_by_product' => [],
            'product'                     => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku],
        ];

        if (!$bom) {
            return $payload;
        }

        // Defaults (PO-06): primary op of every stage; primary material of every slot (on alternative ops too)
        foreach ($payload['parent_workspace']['stages'] as &$stage) {
            $stage['default_selected_operation_id'] = $stage['primary_operation_id'];
        }
        unset($stage);

        $productIds = [];
        foreach ($resolution['stages'] as $stage) {
            foreach ($stage['operations'] as $op) {
                foreach ($op['material_slots'] as $slot) {
                    foreach ($slot['options'] as $opt) {
                        $productIds[] = $opt['material_id'];
                        if ($opt['child_bom_id']) {
                            $this->collectChildBoms((int) $opt['child_bom_id'], $tenantId, $payload['child_boms'], $productIds);
                        }
                    }
                }
            }
        }
        if (!$routing || $resolution['stages'] === []) {
            foreach ($bom->items()->get() as $item) {
                $productIds[] = $item->material_id;
                if ($item->child_bom_id) {
                    $this->collectChildBoms((int) $item->child_bom_id, $tenantId, $payload['child_boms'], $productIds);
                }
            }
        }
        $payload['branch_available_by_product'] = $this->getBranchAvailableStockMap($tenantId, $branchId, $productIds);

        if ($routing && $resolution['can_create_order']) {
            $plan = $this->computeMultiLevelPlan($bom, $resolution['selected_operation_ids'], $resolution['selected_material_ids'], max($quantity, 0.0001), $tenantId, $branchId);
            $payload['default_preview'] = [
                'rows'            => $plan['rows'],
                'child_summaries' => array_values($plan['child_summaries']),
            ];
        }

        return $payload;
    }

    /** Child BOM (primary operations + primary materials only, PO-10 / PO-11) and its descendants. */
    private function collectChildBoms(int $childBomId, int $tenantId, array &$map, array &$productIds, int $depth = 0): void
    {
        if (isset($map[$childBomId]) || $depth > self::MAX_LEVELS) {
            return;
        }
        $child = ProductionBom::withoutGlobalScopes()->where('tenant_id', $tenantId)->with('product')->find($childBomId);
        if (!$child) {
            return;
        }
        $productIds[] = $child->product_id;
        $map[$childBomId] = []; // reserve (loop guard)

        $routing = $child->routing_id ? Routing::withoutGlobalScopes()->find($child->routing_id) : null;
        $allocs = RoutingOperationMaterial::withoutGlobalScopes()
            ->where('bom_id', $child->id)
            ->where('is_alternative', false)
            ->with(['material.uom', 'uom', 'routingOperation', 'childBom'])
            ->get()
            ->filter(fn ($a) => !$a->routingOperation || !$a->routingOperation->is_alternative);

        $operations = [];
        if ($routing && $allocs->isNotEmpty()) {
            foreach ($routing->operations()->where('is_alternative', false)->orderBy('sequence')->get() as $op) {
                $operations[] = [
                    'id'               => $op->id,
                    'operation_number' => $op->operation_number,
                    'name'             => $op->name,
                    'sequence'         => $op->sequence,
                    'materials'        => $allocs->where('routing_operation_id', $op->id)
                        ->filter(fn ($a) => (int) $a->material_id !== (int) $child->product_id)
                        ->map(fn ($a) => $this->materialPayload($a))->values()->all(),
                ];
            }
        } else {
            $operations[] = [
                'id' => null, 'operation_number' => '—', 'name' => 'Materials', 'sequence' => 0,
                'materials' => $child->items()->with(['material.uom', 'uom'])->get()->map(fn (ProductionBomItem $i) => [
                    'id' => 'I' . $i->id, 'material_id' => $i->material_id, 'name' => $i->material?->name, 'sku' => $i->material?->sku,
                    'type' => $i->material?->type, 'uom' => $i->uom?->code ?? $i->material?->uom?->code ?? 'PCS', 'uom_id' => $i->uom_id,
                    'quantity' => (float) $i->quantity, 'material_scrap_percentage' => (float) ($i->material_scrap_percentage ?? 0),
                    'gross_quantity' => (float) ($i->gross_quantity ?: $i->quantity * (1 + (float) ($i->material_scrap_percentage ?? 0) / 100)),
                    'is_alternative' => false, 'priority' => null, 'child_bom_id' => $i->child_bom_id, 'child_bom_number' => null,
                ])->values()->all(),
            ];
        }

        $map[$childBomId] = [
            'id'            => $child->id,
            'bom_number'    => $child->bom_number,
            'version'       => $child->version,
            'status'        => $child->status,
            'base_quantity' => (float) $child->base_quantity ?: 1.0,
            'product_id'    => $child->product_id,
            'product_name'  => $child->product?->name,
            'product_sku'   => $child->product?->sku,
            'operations'    => $operations,
        ];

        foreach ($operations as $op) {
            foreach ($op['materials'] as $m) {
                $productIds[] = $m['material_id'];
                if (!empty($m['child_bom_id'])) {
                    $this->collectChildBoms((int) $m['child_bom_id'], $tenantId, $map, $productIds, $depth + 1);
                }
            }
        }
    }

    // ------------------------------------------------------------------ helpers

    private function primaryAllocations(ProductionBom $bom): Collection
    {
        $bom->loadMissing(['operationMaterials.routingOperation', 'items']);
        if ($bom->operationMaterials->isEmpty()) {
            return $bom->items;
        }

        return $bom->operationMaterials
            ->where('is_alternative', false)
            ->filter(fn ($a) => !$a->routingOperation || !$a->routingOperation->is_alternative)
            ->values();
    }

    private function grossUnit($row): float
    {
        $gross = $row->gross_quantity ?? null;
        if ($gross !== null && (float) $gross > 0) {
            return (float) $gross;
        }

        return (float) $row->quantity * (1 + (float) ($row->material_scrap_percentage ?? 0) / 100);
    }
}
