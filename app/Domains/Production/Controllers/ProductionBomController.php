<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Repositories\ProductionBomRepositoryInterface;
use App\Domains\Production\Requests\StoreProductionBomRequest;
use App\Domains\Production\Requests\UpdateProductionBomRequest;
use App\Domains\Production\Services\ProductionBomService;
use App\Domains\Production\Services\BomExplosionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ProductionBomController extends Controller
{
    public function __construct(
        private readonly ProductionBomRepositoryInterface $bomRepository,
        private readonly ProductionBomService $bomService,
        private readonly BomExplosionService $explosionService,
        private readonly \App\Domains\Production\Services\ProductionBomVersionService $versionService,
        private readonly \App\Domains\Production\Services\ProductionCostService $costService,
        private readonly \App\Domains\Production\Services\BomWhereUsedService $whereUsedService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProductionBom::class);

        $filters = $request->only(['product_id', 'status', 'search', 'sort_by', 'sort_order']);
        $boms = $this->bomRepository->paginateAll($filters, 10)->withQueryString();

        $products = Product::whereIn('type', ['finished_good', 'finished_goods', 'semi_finished', 'semi_finished_goods'])
            ->whereIn('supplier_method', ['manufacture', 'both'])
            ->get();

        return view('modules.production.bom.index', compact('boms', 'products'));
    }

    public function checkChildBom(int $productId)
    {
        $tenantId = require_tenant_id();

        $boms = $this->bomRepository->getBomsByProductUnscoped($productId, $tenantId);
        $today = Carbon::today()->toDateString();

        // Only approved BOMs that are currently active or scheduled (exclude cancelled, draft, inactive, expired)
        $validBoms = $boms->filter(function ($bom) use ($today) {
            if (!in_array($bom->status, ['approved', 'active', 'scheduled'], true)) {
                return false;
            }
            if ($bom->expiry_date && Carbon::parse($bom->expiry_date)->toDateString() < $today) {
                return false;
            }
            return true;
        })->values();

        $versions = $validBoms->map(function ($bom) use ($today) {
            $effectiveDate = $bom->effective_date ? Carbon::parse($bom->effective_date)->toDateString() : null;
            $isScheduled = ($bom->status === 'scheduled') || ($effectiveDate && $effectiveDate > $today);
            $displayStatus = $isScheduled ? 'scheduled' : 'active';

            return [
                'id' => $bom->id,
                'bom_number' => $bom->bom_number,
                'version' => $bom->version,
                'bom_name' => $bom->bom_name,
                'status' => $displayStatus,
                'effective_date' => $effectiveDate,
                'expiry_date' => $bom->expiry_date ? Carbon::parse($bom->expiry_date)->toDateString() : null,
            ];
        });

        $activeBom = $validBoms->first(function ($bom) use ($today) {
            $effectiveDate = $bom->effective_date ? Carbon::parse($bom->effective_date)->toDateString() : null;
            return !$effectiveDate || $effectiveDate <= $today;
        }) ?? $validBoms->first();

        return response()->json([
            'status' => $activeBom ? 'approved' : 'none',
            'bom_id' => $activeBom?->id,
            'bom_number' => $activeBom?->bom_number,
            'version' => $activeBom?->version,
            'bom_name' => $activeBom?->bom_name,
            'versions' => $versions,
        ]);
    }

    public function createRevision(int $id, Request $request): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->authorize('create', ProductionBom::class);

        $bumpType = $request->input('bump_type', 'patch');

        $newVersion = match ($bumpType) {
            'major' => $this->versionService->incrementMajor($bom->version),
            'minor' => $this->versionService->incrementMinor($bom->version),
            default => $this->versionService->incrementPatch($bom->version),
        };

        $tenantId = require_tenant_id();
        while ($this->bomRepository->versionExistsUnscoped($bom->product_id, $newVersion, $tenantId)) {
            $newVersion = match ($bumpType) {
                'major' => $this->versionService->incrementMajor($newVersion),
                'minor' => $this->versionService->incrementMinor($newVersion),
                default => $this->versionService->incrementPatch($newVersion),
            };
        }

        // BM-IMPL-03: Copy-without-saving — redirect to pre-filled create form
        return redirect()
            ->route('production.boms.create', [
                'copy_from' => $bom->id,
                'mode'      => 'revision',
                'version'   => $newVersion,
            ])
            ->with('info', __('production.bom_copy_notice', [
                'number'  => $bom->bom_number,
                'version' => $bom->version,
                'mode'    => __('production.bom_mode_revision'),
            ]));
    }

    public function show(int $id, Request $request): View
    {
        // BM-IMPL-15 #6: master rows + allocations with their operations (incl. removed ones)
        $bom = $this->bomRepository->getBomWithComponents($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->authorize('view', $bom);

        // Requirements multi-level explosion
        $calcQty = $request->input('calc_qty') ? (float) $request->input('calc_qty') : $bom->base_quantity;
        $explosion = $this->explosionService->explodeBom($bom->id, $calcQty, $bom->tenant_id) /* BM-IMPL-16: the version being viewed */;

        ['costSummary' => $costSummary, 'rollupSummary' => $rollupSummary, 'costSimulatorData' => $costSimulatorData] = $this->buildCostSummaryPayload($bom);

        $costSourceRows = $bom->operationMaterials->isNotEmpty() ? $bom->operationMaterials : $bom->items;
        $componentProductIds = $costSourceRows->pluck('material_id')->unique();
        $componentBoms = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $bom->tenant_id)
            ->whereIn('product_id', $componentProductIds)
            ->get()
            ->groupBy('product_id');

        $materialCost = $costSummary['material_cost'];
        $routingCost = $costSummary['routing_cost'];
        $totalMfgCost = $costSummary['total_cost'];
        $whereUsedParents = $this->whereUsedService->findParents($bom->product);
        $whereUsedBoms = $this->whereUsedService->findParentBoms($bom->product);

        $parentProduct = null;
        $parentBom = null;
        if ($request->filled('parent_product_id')) {
            $parentProduct = Product::find($request->input('parent_product_id'));
            if ($parentProduct) {
                $parentBom = ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $bom->tenant_id)
                    ->where('product_id', $parentProduct->id)
                    ->orderByRaw("case when status in ('approved', 'active') then 1 when status = 'draft' then 2 else 3 end")
                    ->first();
            }
        }

        return view('modules.production.bom.show', compact(
            'bom', 'explosion', 'calcQty', 'costSummary', 'rollupSummary', 'costSimulatorData',
            'componentBoms', 'materialCost', 'routingCost', 'totalMfgCost', 'whereUsedParents',
            'parentProduct', 'parentBom', 'whereUsedBoms'
        ));
    }

    /**
     * AJAX endpoint for recalculating Tab 2 Multi-Level BOM Explosion without reloading the page.
     */
    public function explosion(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->authorize('view', $bom);

        $calcQty = (float) $request->input('calc_qty', $bom->base_quantity);
        if ($calcQty <= 0) {
            return response()->json([
                'message' => 'Quantity must be greater than zero.',
            ], 422);
        }

        $explosion = $this->explosionService->explodeBom($bom->id, $calcQty, $bom->tenant_id);
        $html = view('modules.production.bom.partials._explosion_rows', compact('explosion'))->render();

        return response()->json([
            'calc_qty' => $calcQty,
            'html' => $html,
        ]);
    }

    /**
     * Build Tab 4 cost breakdown, multi-level roll-up summary, and Alpine Simulate Cost payload.
     */
    private function buildCostSummaryPayload(ProductionBom $bom): array
    {
        $costCalculations = $this->costService->calculateCost($bom);
        $rollupSummary = $this->costService->calculateMultiLevelRollupCost($bom);
        $itemRollups = $rollupSummary['item_rollups'] ?? [];

        $costSourceRows = $bom->operationMaterials->isNotEmpty() ? $bom->operationMaterials : $bom->items;
        $allocById = $bom->operationMaterials->keyBy('id');

        $costDetails = [];
        foreach ($costSourceRows as $item) {
            $qty = (float) $item->quantity;
            $scrapPct = (float) ($item->material_scrap_percentage ?? 0.0);
            $grossQty = isset($item->gross_quantity) && $item->gross_quantity !== null
                ? (float) $item->gross_quantity
                : \App\Domains\Production\Models\RoutingOperationMaterial::calculateGrossQuantity($qty, $scrapPct);
            $unitCost = (float) ($item->material?->unit_cost ?? 0.0);
            $itemCost = $grossQty * $unitCost;

            $isOpMat = $item instanceof \App\Domains\Production\Models\RoutingOperationMaterial;
            $op = $isOpMat ? $item->routingOperation : null;
            $opIsAlt = (bool) ($op?->is_alternative ?? false);
            $isAlt = (bool) ($item->is_alternative ?? false);
            $isExcluded = $isAlt || $opIsAlt;

            $rollupInfo = (!$isExcluded && $isOpMat && isset($itemRollups[$item->id]))
                ? $itemRollups[$item->id]
                : null;

            $replacesId = $item->replaces_routing_material_id ?? $item->replaces_item_id ?? null;
            $replacesName = ($isAlt && $replacesId && $allocById->has($replacesId))
                ? ($allocById->get($replacesId)->material?->name ?? null)
                : null;

            $costDetails[] = [
                'id' => $item->id,
                'routing_operation_id' => $isOpMat ? $item->routing_operation_id : null,
                'operation_number' => $op?->operation_number ?? ($op ? ('Op ' . $op->sequence) : '—'),
                'operation_name' => $op?->name ?? null,
                'operation_is_alternative' => $opIsAlt,
                'material_name' => $item->material?->name ?? 'Unknown Material',
                'material_sku' => $item->material?->sku ?? '—',
                'quantity' => $qty,
                'scrap_percentage' => $scrapPct,
                'gross_quantity' => $grossQty,
                'uom_code' => $item->uom?->code ?? $item->material?->uom?->code ?? 'PCS',
                'unit_cost' => $unitCost,
                'total_cost' => $itemCost,
                'has_sub_bom' => (bool) ($rollupInfo['has_sub_bom'] ?? ($isOpMat && (bool) $item->child_bom_id)),
                'sub_bom_number' => $rollupInfo['sub_bom_number'] ?? ($isOpMat ? $item->childBom?->bom_number : null),
                'child_bom_id' => $isOpMat ? $item->child_bom_id : null,
                'rollup_cost' => $rollupInfo['rollup_cost'] ?? ($isExcluded ? null : $itemCost),
                'rollup_raw_material_cost' => $rollupInfo['raw_material_cost'] ?? ($isExcluded ? null : $itemCost),
                'rollup_sub_routing_cost' => $rollupInfo['sub_routing_cost'] ?? 0.0,
                'is_alternative' => $isAlt,
                'is_excluded_from_baseline' => $isExcluded,
                'replaces_item_id' => $replacesId,
                'replaces_material_name' => $replacesName,
                'priority' => $item->priority,
            ];
        }

        $costSummary = array_merge([
            'items' => $costDetails,
        ], $costCalculations);

        $costSimulatorData = $this->buildCostSimulatorData($bom, $costCalculations, $rollupSummary, $itemRollups);

        return [
            'costSummary' => $costSummary,
            'rollupSummary' => $rollupSummary,
            'costSimulatorData' => $costSimulatorData,
        ];
    }

    /**
     * Build active-currency payload for Tab 4's Simulate Cost modal (alternative routes + alternative materials).
     */
    private function buildCostSimulatorData(ProductionBom $bom, array $costCalculations, array $rollupSummary, array $itemRollups): array
    {
        $baseQty = (float) $bom->base_quantity > 0 ? (float) $bom->base_quantity : 1.0;
        $ops = $bom->routing?->operations ?? collect();
        $allAllocs = $bom->operationMaterials->isNotEmpty() ? $bom->operationMaterials : $bom->items;
        $allocsByOp = $allAllocs->groupBy(fn ($om) => (int) ($om->routing_operation_id ?? 0));

        $mapMaterialOption = function ($om) use ($bom, $itemRollups): array {
            $qty = (float) $om->quantity;
            $scrapPct = (float) ($om->material_scrap_percentage ?? 0.0);
            $grossQty = isset($om->gross_quantity) && $om->gross_quantity !== null
                ? (float) $om->gross_quantity
                : \App\Domains\Production\Models\RoutingOperationMaterial::calculateGrossQuantity($qty, $scrapPct);
            $unitCost = (float) ($om->material?->unit_cost ?? 0.0);
            $directCost = $grossQty * $unitCost;
            $scrapCost = max(0.0, $grossQty - $qty) * $unitCost;

            $rollupDirect = $directCost;
            $rollupRaw = $directCost;
            $rollupSubRouting = 0.0;
            $rollupSubSetup = 0.0;
            $hasSubBom = false;
            $subBomNumber = null;

            if (isset($itemRollups[$om->id])) {
                $r = $itemRollups[$om->id];
                $hasSubBom = (bool) $r['has_sub_bom'];
                $subBomNumber = $r['sub_bom_number'];
                $rollupDirect = (float) $r['rollup_cost'];
                $rollupRaw = (float) $r['raw_material_cost'];
                $rollupSubRouting = (float) $r['sub_routing_cost'];
                $rollupSubSetup = (float) $r['setup_cost_per_run'];
            } elseif ($om instanceof \App\Domains\Production\Models\RoutingOperationMaterial && $om->child_bom_id) {
                $childBom = ProductionBom::withoutGlobalScopes()->whereNull('deleted_at')->find($om->child_bom_id);
                if ($childBom && (int) $om->material_id !== (int) $bom->product_id) {
                    $sub = $this->costService->calculateMultiLevelRollupCost($childBom, $grossQty, [$bom->product_id => true]);
                    $hasSubBom = true;
                    $subBomNumber = $childBom->bom_number;
                    $rollupDirect = (float) $sub['rolled_total_excl_setup'];
                    $rollupRaw = (float) $sub['rolled_raw_material_cost'];
                    $rollupSubRouting = (float) $sub['rolled_routing_cost'];
                    $rollupSubSetup = (float) $sub['rolled_setup_cost_per_run'];
                }
            }

            return [
                'id' => $om->id,
                'material_name' => $om->material?->name ?? 'Unknown Material',
                'material_sku' => $om->material?->sku ?? '—',
                'quantity' => $qty,
                'scrap_percentage' => $scrapPct,
                'gross_quantity' => $grossQty,
                'uom_code' => $om->uom?->code ?? $om->material?->uom?->code ?? 'PCS',
                'unit_cost' => round(convert_from_base($unitCost), 4),
                'direct_cost' => round(convert_from_base($directCost), 4),
                'scrap_cost' => round(convert_from_base($scrapCost), 4),
                'rollup_cost' => round(convert_from_base($rollupDirect), 4),
                'rollup_raw_material_cost' => round(convert_from_base($rollupRaw), 4),
                'rollup_sub_routing_cost' => round(convert_from_base($rollupSubRouting), 4),
                'rollup_sub_setup_per_run' => round(convert_from_base($rollupSubSetup), 4),
                'has_sub_bom' => $hasSubBom,
                'sub_bom_number' => $subBomNumber,
                'is_alternative' => (bool) $om->is_alternative,
                'priority' => (int) ($om->priority ?? 1),
            ];
        };

        $buildMaterialSlots = function ($opAllocs) use ($mapMaterialOption): array {
            $primaries = $opAllocs->where('is_alternative', false)->sortBy('sequence')->values();
            $altsByTarget = $opAllocs->where('is_alternative', true)->groupBy(fn ($a) => $a->replaces_routing_material_id ?? $a->replaces_item_id);
            $placedAltIds = [];
            $slots = [];

            foreach ($primaries as $prim) {
                $alts = $altsByTarget->get($prim->id, collect())->sortBy('priority')->values();
                foreach ($alts as $a) {
                    $placedAltIds[$a->id] = true;
                }
                $options = array_merge([$mapMaterialOption($prim)], $alts->map($mapMaterialOption)->all());
                $slots[] = [
                    'slot_key' => 'mat_' . $prim->id,
                    'primary_id' => $prim->id,
                    'has_alternatives' => $alts->isNotEmpty(),
                    'options' => $options,
                ];
            }

            // Include any orphan alternative material rows cleanly as locked info or selectable if needed
            $orphanAlts = $opAllocs->where('is_alternative', true)->filter(fn ($a) => !isset($placedAltIds[$a->id]))->values();
            foreach ($orphanAlts as $oAlt) {
                $slots[] = [
                    'slot_key' => 'mat_' . $oAlt->id,
                    'primary_id' => $oAlt->id,
                    'has_alternatives' => false,
                    'options' => [$mapMaterialOption($oAlt)],
                ];
            }

            return $slots;
        };

        $operationsData = [];
        foreach ($ops as $op) {
            $u = $this->costService->resolveOperationUnitCosts($op);
            $labor = round(convert_from_base($u['labor'] * $baseQty), 4);
            $machine = round(convert_from_base($u['machine'] * $baseQty), 4);
            $subcontract = round(convert_from_base($u['subcontract'] * $baseQty), 4);
            $setupRun = round(convert_from_base($u['setup_per_run']), 4);

            $resName = 'Generic Capacity';
            if ($op->machine) {
                $resName = $op->machine->name . ($op->machine->code ? ' (' . $op->machine->code . ')' : '');
            } elseif ($op->manualProduction) {
                $resName = $op->manualProduction->batch_name . ' (Manual Batch)';
            } elseif ($op->is_external) {
                $resName = $op->vendor?->name ?? 'External Subcontractor';
            }

            $operationsData[$op->id] = [
                'id' => $op->id,
                'sequence' => (int) $op->sequence,
                'operation_number' => $op->operation_number ?? ('OP-' . $op->sequence),
                'name' => $op->name,
                'is_alternative' => (bool) $op->is_alternative,
                'replaces_operation_id' => $op->replaces_operation_id,
                'priority' => (int) ($op->priority ?? 1),
                'work_center' => $op->workCenter?->name ?? ($op->is_external ? 'External' : '—'),
                'section' => $op->section?->name ?? '—',
                'resource' => $resName,
                'labor_cost' => $labor,
                'machine_cost' => $machine,
                'subcontract_cost' => $subcontract,
                'routing_cost' => round($labor + $machine + $subcontract, 4),
                'setup_cost_per_run' => $setupRun,
                'material_slots' => $buildMaterialSlots($allocsByOp->get($op->id, collect())),
            ];
        }

        $stagesData = [];
        $primaryOps = $ops->where('is_alternative', false)->sortBy('sequence')->values();
        $allAltOps = $ops->where('is_alternative', true);
        $claimedAltOpIds = [];

        foreach ($primaryOps as $idx => $pOp) {
            $alts = $allAltOps->where('replaces_operation_id', $pOp->id)->sortBy('priority')->values();
            foreach ($alts as $a) {
                $claimedAltOpIds[] = $a->id;
            }
            $stagesData[] = [
                'stage_number' => $idx + 1,
                'primary_id' => $pOp->id,
                'has_alternatives' => $alts->isNotEmpty(),
                'options' => array_merge([$pOp->id], $alts->pluck('id')->all()),
            ];
        }

        // Attach orphan alternative operations to the preceding primary stage (matches routing/show)
        $orphanAltOps = $allAltOps->whereNotIn('id', $claimedAltOpIds);
        if ($orphanAltOps->isNotEmpty() && !empty($stagesData)) {
            foreach ($orphanAltOps as $oAlt) {
                $assigned = false;
                for ($i = count($stagesData) - 1; $i >= 0; $i--) {
                    if ($operationsData[$stagesData[$i]['primary_id']]['sequence'] < (int) $oAlt->sequence) {
                        $stagesData[$i]['options'][] = $oAlt->id;
                        $stagesData[$i]['has_alternatives'] = true;
                        $assigned = true;
                        break;
                    }
                }
                if (!$assigned) {
                    $stagesData[0]['options'][] = $oAlt->id;
                    $stagesData[0]['has_alternatives'] = true;
                }
            }
        }

        // Unassigned or deleted-op allocations (if any) stay visible as a locked stage
        $knownOpIds = $ops->pluck('id')->all();
        $unmatchedAllocs = $allAllocs->filter(fn ($om) => !in_array((int) ($om->routing_operation_id ?? 0), $knownOpIds, true));
        if ($unmatchedAllocs->isNotEmpty()) {
            $operationsData[0] = [
                'id' => 0,
                'sequence' => 9999,
                'operation_number' => 'UNASSIGNED',
                'name' => 'Unassigned / Legacy Operation Materials',
                'is_alternative' => false,
                'replaces_operation_id' => null,
                'priority' => 1,
                'work_center' => '—',
                'section' => '—',
                'resource' => '—',
                'labor_cost' => 0.0,
                'machine_cost' => 0.0,
                'subcontract_cost' => 0.0,
                'routing_cost' => 0.0,
                'setup_cost_per_run' => 0.0,
                'material_slots' => $buildMaterialSlots($unmatchedAllocs),
            ];
            $stagesData[] = [
                'stage_number' => count($stagesData) + 1,
                'primary_id' => 0,
                'has_alternatives' => false,
                'options' => [0],
            ];
        }

        $altOpsCount = $allAltOps->count();
        $altMatsCount = $allAllocs->where('is_alternative', true)->count();

        return [
            'base_quantity' => $baseQty,
            'currency_symbol' => active_currency_symbol(),
            'alt_ops_count' => $altOpsCount,
            'alt_mats_count' => $altMatsCount,
            'has_alternatives' => ($altOpsCount > 0 || $altMatsCount > 0),
            'baseline' => [
                'material_cost' => round(convert_from_base((float) $costCalculations['material_cost']), 4),
                'scrap_adjustment' => round(convert_from_base((float) $costCalculations['scrap_adjustment']), 4),
                'labor_cost' => round(convert_from_base((float) $costCalculations['labor_cost']), 4),
                'machine_cost' => round(convert_from_base((float) $costCalculations['machine_cost']), 4),
                'subcontract_cost' => round(convert_from_base((float) $costCalculations['subcontract_cost']), 4),
                'routing_cost' => round(convert_from_base((float) $costCalculations['routing_cost']), 4),
                'setup_cost_per_run' => round(convert_from_base((float) $costCalculations['setup_cost_per_run']), 4),
                'direct_total' => round(convert_from_base((float) $costCalculations['total_cost']), 4),
                'direct_cost_per_unit' => round(convert_from_base((float) $costCalculations['cost_per_unit']), 4),
                'rolled_raw_material' => round(convert_from_base((float) $rollupSummary['rolled_raw_material_cost']), 4),
                'rolled_routing' => round(convert_from_base((float) $rollupSummary['rolled_routing_cost']), 4),
                'rolled_total' => round(convert_from_base((float) $rollupSummary['rolled_total_excl_setup']), 4),
                'rolled_cost_per_unit' => round(convert_from_base((float) $rollupSummary['rolled_cost_per_unit']), 4),
            ],
            'stages' => $stagesData,
            'operations' => $operationsData,
        ];
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProductionBom::class);

        $products = Product::whereIn('type', ['finished_good', 'finished_goods', 'semi_finished', 'semi_finished_goods'])
            ->whereIn('supplier_method', ['manufacture', 'both'])
            ->get();
        $materials = Product::whereIn('type', ['raw_material', 'component', 'finished_good', 'finished_goods', 'semi_finished', 'semi_finished_goods'])->get();
        $uoms = Uom::all();
        $routings = Routing::with(['operations.workCenter', 'operations.section'])
            ->whereIn('status', [Routing::STATUS_ACTIVE, Routing::STATUS_SCHEDULED, 'approved'])
            ->get();
        $selectedProductId = $request->query('product_id');

        // BM-IMPL-03: copy_from prefill without saving to DB
        $copyFromBom = null;
        $copyMode = null;
        $suggestedVersion = null;
        $copyItems = null;
        $selectedRoutingId = '';

        if ($request->filled('copy_from')) {
            $sourceBom = $this->bomRepository->getBomWithComponents((int) $request->query('copy_from'));
            if ($sourceBom && $sourceBom->tenant_id === require_tenant_id()) {
                $this->authorize('view', $sourceBom);
                $copyFromBom = $sourceBom;
                $copyMode = $request->query('mode') === 'revision' ? 'revision' : 'duplicate';
                $selectedProductId = $selectedProductId ?: $sourceBom->product_id;

                $reqVersion = trim((string) $request->query('version', ''));
                if ($reqVersion !== '' && !$this->bomRepository->versionExistsUnscoped($sourceBom->product_id, $reqVersion, $sourceBom->tenant_id)) {
                    $suggestedVersion = $reqVersion;
                } else {
                    $candidate = $this->versionService->incrementPatch($sourceBom->version ?: '1.0.0');
                    while ($this->bomRepository->versionExistsUnscoped($sourceBom->product_id, $candidate, $sourceBom->tenant_id)) {
                        $candidate = $this->versionService->incrementPatch($candidate);
                    }
                    $suggestedVersion = $candidate;
                }

                // Include source routing in list if non-historical/non-cancelled so operations render cleanly
                if ($sourceBom->routing_id) {
                    $existingRouting = $routings->firstWhere('id', $sourceBom->routing_id);
                    if (!$existingRouting) {
                        $sourceRouting = Routing::with(['operations.workCenter', 'operations.section'])->find($sourceBom->routing_id);
                        if ($sourceRouting && !in_array($sourceRouting->status, [Routing::STATUS_HISTORICAL, Routing::STATUS_CANCELLED], true)) {
                            $routings->push($sourceRouting);
                            $existingRouting = $sourceRouting;
                        }
                    }
                    if ($existingRouting) {
                        $selectedRoutingId = (string) $existingRouting->id;
                    }
                }

                $this->attachDeletedOperationsForBom($sourceBom->id, $routings);

                $copyItems = $sourceBom->operationMaterials->isNotEmpty()
                    ? $sourceBom->operationMaterials->map(fn ($om) => [
                        'uid'                       => 'copy_' . $om->id,
                        'routing_operation_id'      => $om->routing_operation_id,
                        'material_id'               => $om->material_id,
                        'quantity'                  => (float) $om->quantity,
                        'uom_id'                    => $om->uom_id,
                        'material_scrap_percentage' => (float) ($om->material_scrap_percentage ?? 0),
                        'is_alternative'            => (bool) $om->is_alternative,
                        'priority'                  => $om->priority ?: 1,
                        'replaces_row_uid'          => $om->replaces_routing_material_id ? 'copy_' . $om->replaces_routing_material_id : '',
                        'child_bom_id'              => $om->child_bom_id,
                        'notes'                     => $om->notes,
                        'sequence'                  => $om->sequence,
                    ])->values()->all()
                    : [];
            }
        }

        $routingAdvisoriesMap = $this->buildRoutingAdvisoriesMap($routings);

        return view('modules.production.bom.create', compact(
            'products', 'materials', 'uoms', 'routings', 'selectedProductId',
            'copyFromBom', 'copyMode', 'suggestedVersion', 'copyItems',
            'selectedRoutingId', 'routingAdvisoriesMap'
        ));
    }

    public function store(StoreProductionBomRequest $request): RedirectResponse
    {
        $this->authorize('create', ProductionBom::class);

        try {
            $dto = ProductionBomDTO::fromArray($request->validated());
            $bom = $this->bomService->create($dto, auth()->id());

            $routeParams = ['bom' => $bom->id];
            if ($request->filled('parent_product_id')) {
                $routeParams['parent_product_id'] = $request->input('parent_product_id');
            }

            return redirect()
                ->route('production.boms.show', $routeParams)
                ->with('success', 'BOM created successfully in draft mode.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function edit(int $id): View
    {
        $bom = $this->bomRepository->getBomWithComponents($id);
        abort_if(!$bom, 404, 'BOM not found.');
        abort_if(!$bom->isDraft() && !$bom->isUnderRevision(), 403, 'Approved BOMs cannot be edited directly.');

        $this->authorize('update', $bom);

        $products = Product::whereIn('type', ['finished_good', 'finished_goods', 'semi_finished', 'semi_finished_goods'])
            ->whereIn('supplier_method', ['manufacture', 'both'])
            ->get();
        $materials = Product::whereIn('type', ['raw_material', 'component', 'finished_good', 'finished_goods', 'semi_finished', 'semi_finished_goods'])->get(); // support multi-level
        $uoms = Uom::all();
        $routings = Routing::with(['operations.workCenter', 'operations.section'])
            ->where(function ($q) use ($bom) {
                $q->whereIn('status', [Routing::STATUS_ACTIVE, Routing::STATUS_SCHEDULED, 'approved']);
                if ($bom->routing_id) {
                    $q->orWhere('id', $bom->routing_id);
                }
            })
            ->get();

        $this->attachDeletedOperationsForBom($bom->id, $routings);
        $routingAdvisoriesMap = $this->buildRoutingAdvisoriesMap($routings);

        return view('modules.production.bom.edit', compact('bom', 'products', 'materials', 'uoms', 'routings', 'routingAdvisoriesMap'));
    }

    public function update(int $id, UpdateProductionBomRequest $request): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->authorize('update', $bom);

        try {
            $dto = ProductionBomDTO::fromArray($request->validated());
            $this->bomService->update($id, $dto);

            $routeParams = ['bom' => $bom->id];
            if ($request->filled('parent_product_id')) {
                $routeParams['parent_product_id'] = $request->input('parent_product_id');
            }

            return redirect()
                ->route('production.boms.show', $routeParams)
                ->with('success', 'BOM updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->authorize('delete', $bom);

        $this->bomRepository->delete($id);

        return redirect()
            ->route('production.boms.index')
            ->with('success', 'BOM deleted successfully.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $action = $request->input('action');
        $ids = $request->input('ids');

        if (empty($ids) || !is_array($ids)) {
            return redirect()
                ->back()
                ->with('error', 'No BOMs selected.');
        }

        $tenantId = require_tenant_id();
        $boms = ProductionBom::whereIn('id', $ids)
            ->where('tenant_id', $tenantId)
            ->get();

        $successCount = 0;
        $failedCount = 0;

        switch ($action) {
            case 'delete':
                foreach ($boms as $bom) {
                    if (auth()->user()->can('delete', $bom)) {
                        $this->bomRepository->delete($bom->id);
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "deleted";
                break;

            case 'submit_approval':
                foreach ($boms as $bom) {
                    if (($bom->isDraft() || $bom->isUnderRevision()) && auth()->user()->can('update', $bom)) {
                        try {
                            $this->bomService->submitApproval($bom->id);
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "submitted for approval";
                break;

            case 'approve':
                foreach ($boms as $bom) {
                    if ($bom->isPendingApproval() && auth()->user()->can('approve', $bom)) {
                        try {
                            $this->bomService->approve($bom->id, auth()->id());
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "approved";
                break;

            case 'cancel':
                foreach ($boms as $bom) {
                    if (auth()->user()->can('cancel', $bom)) {
                        try {
                            $this->bomService->cancel($bom->id, auth()->id(), 'Bulk cancelled.');
                            $successCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                        }
                    } else {
                        $failedCount++;
                    }
                }
                $messagePrefix = "cancelled";
                break;

            default:
                return redirect()
                    ->back()
                    ->with('error', 'Invalid action.');
        }

        if ($failedCount > 0) {
            if ($successCount > 0) {
                return redirect()
                    ->route('production.boms.index')
                    ->with('success', "Successfully {$messagePrefix} {$successCount} BOM(s). {$failedCount} BOM(s) could not be processed due to status or permission rules.");
            }
            return redirect()
                ->route('production.boms.index')
                ->with('error', "No BOMs could be {$messagePrefix}. Check status restrictions and your permissions.");
        }

        return redirect()
            ->route('production.boms.index')
            ->with('success', "Successfully {$messagePrefix} {$successCount} BOM(s).");
    }

    public function submitApproval(int $id): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->authorize('update', $bom);

        try {
            $this->bomService->submitApproval($id);
            return redirect()
                ->back()
                ->with('success', 'BOM submitted for approval.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function approve(int $id): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->authorize('approve', $bom);

        try {
            $this->bomService->approve($id, auth()->id());
            return redirect()
                ->back()
                ->with('success', 'BOM approved successfully. Any older approved version has been set to inactive.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function reject(int $id, Request $request): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->authorize('approve', $bom);

        $request->validate(['comments' => 'nullable|string|max:1000']);
        try {
            $this->bomService->reject($id, auth()->id(), $request->input('comments'));
            return redirect()
                ->back()
                ->with('success', 'BOM rejected successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function cancel(int $id, Request $request): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->authorize('cancel', $bom);

        $request->validate(['comments' => 'nullable|string|max:1000']);
        try {
            $this->bomService->cancel($id, auth()->id(), $request->input('comments'));
            return redirect()
                ->back()
                ->with('success', 'BOM cancelled successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function duplicateVersion(Request $request, int $id): RedirectResponse
    {
        $bom = $this->bomRepository->find($id);
        abort_if(!$bom, 404, 'BOM not found.');

        $this->authorize('create', ProductionBom::class);

        $request->validate([
            'new_version' => 'required|string|max:50',
        ]);

        try {
            $newVersion = $request->input('new_version');
            $this->bomService->checkBomConflicts($bom->product_id, $newVersion, null, $bom->tenant_id);

            // BM-IMPL-03: Copy-without-saving — redirect to pre-filled create form
            return redirect()
                ->route('production.boms.create', [
                    'copy_from' => $bom->id,
                    'mode'      => 'duplicate',
                    'version'   => $newVersion,
                ])
                ->with('info', __('production.bom_copy_notice', [
                    'number'  => $bom->bom_number,
                    'version' => $bom->version,
                    'mode'    => __('production.bom_mode_duplicate'),
                ]));
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Attach soft-deleted routing operations that still carry this BOM's materials so they render as Deleted cards.
     */
    private function attachDeletedOperationsForBom(int $bomId, $routings): void
    {
        $removedOpIds = \App\Domains\Production\Models\RoutingOperationMaterial::where('bom_id', $bomId)
            ->pluck('routing_operation_id')
            ->unique()
            ->filter();
        if ($removedOpIds->isEmpty()) {
            return;
        }

        $removedOps = \App\Domains\Production\Models\RoutingOperation::onlyTrashed()
            ->whereIn('id', $removedOpIds)
            ->with(['workCenter', 'section'])
            ->get();

        foreach ($removedOps->groupBy('routing_id') as $routingId => $ops) {
            $routingModel = $routings->firstWhere('id', $routingId);
            if (!$routingModel) {
                continue;
            }
            $ops->each(function ($op) {
                $op->setAttribute('is_deleted', true);
                $op->setAttribute('sequence', 99000 + $op->id);
            });
            $routingModel->setRelation('operations', $routingModel->operations->concat($ops)->values());
        }
    }

    /**
     * Precompute structured live routing advisories for the BOM form routing dropdown.
     *
     * @return array<int, array<int, array{code: string, message: string, blocks_approval: bool}>>
     */
    private function buildRoutingAdvisoriesMap($routings): array
    {
        $map = [];
        foreach ($routings as $r) {
            $map[$r->id] = ProductionBom::buildRoutingAdvisories($r);
        }
        return $map;
    }
}
