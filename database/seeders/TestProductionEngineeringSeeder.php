<?php

namespace Database\Seeders;

use App\Core\Tenant\TenantRunner;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomApproval;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\RoutingOperationQualityPlan;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\BomLifecycleService;
use App\Domains\Production\Services\RoutingLifecycleService;
use App\Domains\Production\Services\RoutingOperationCostCalculator;
use App\Domains\Production\Services\SectionCalculationService;
use App\Models\Tenant;
use Database\Seeders\Concerns\LoadsTestProductionData;
use Database\Seeders\Support\TestProductionLinks;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The engineering side of the test production dataset (docs/production_seeder_audit_2026-11-26.md, SR-06 / SR-10):
 * 12 routings and 17 BOM rows (16 products; test-semi-04 has two versions). Routings 010–012 and the last four BOMs
 * are the subcontract examples (Part C): outsourced operations in every mode, with and without QC.
 *
 * Only the inputs are data, written with codes (section TEST-SEC-1, machine TEST-MAH-5, batch test-batch-1, material
 * test-raw-01, plan name …). Everything derived is produced by the application's own code, as in NonWovenPlantSeeder:
 *  - operation figures + resource snapshot: RoutingOperationCostCalculator on the section's latest calculation snapshot
 *    (the work-centre engine ran at the end of TestProductionMasterDataSeeder), so order creation finds no stale cost
 *  - routing approval and cost snapshot: RoutingLifecycleService::transitionOnApproval
 *  - BOM master rows: derived from the operation allocations with the rules of ProductionBomService::persistBomItems
 *  - BOM approval and the supersede of an older version: BomLifecycleService::transitionOnApproval
 *  - QC: approved section / process plans of TestProductionQualitySeeder linked to operations (QR-15 / QR-16)
 *
 * Money is INR = the base currency. Safe to run twice: a routing number / BOM number + version that exists is skipped.
 */
class TestProductionEngineeringSeeder extends Seeder
{
    use LoadsTestProductionData;

    private TestProductionLinks $links;

    private int $tenantId;

    private int $userId;

    public function run(?int $tenantId = null): void
    {
        $this->tenantId = $this->resolveTenantId($tenantId);
        $this->links = new TestProductionLinks($this->tenantId);
        $this->userId = $this->links->userId(TestProductionLinks::SEED_USER_EMAIL);
        $tenant = Tenant::findOrFail($this->tenantId);

        [$routings, $boms] = app(TenantRunner::class)->run($tenant, fn () => DB::transaction(fn () => [
            $this->seedRoutings(),
            $this->seedBoms(),
        ]));

        $this->command?->info("TestProductionEngineeringSeeder: {$routings} routing(s) and {$boms} BOM(s) added for Tenant ID {$this->tenantId}.");
    }

    // ─── Routings ────────────────────────────────────────────────────────────────

    private function seedRoutings(): int
    {
        $count = 0;
        foreach ($this->routings() as $data) {
            if (Routing::withTrashed()->where('tenant_id', $this->tenantId)->where('routing_number', $data['routing_number'])->exists()) {
                continue;
            }

            $routing = Routing::create([
                'tenant_id' => $this->tenantId,
                'routing_number' => $data['routing_number'],
                'name' => $data['name'],
                'description' => $data['description'],
                'version' => '1.0.0',
                'revision' => 0,
                'is_default' => true,
                'effective_from' => now()->toDateString(),
                'status' => Routing::STATUS_DRAFT,
                'created_by' => $this->userId,
            ]);
            $this->routingLog($routing, RoutingApproval::ACTION_CREATED, 'Initial routing draft created.');

            $this->seedOperations($routing, $data['operations']);

            $routing->update(['status' => Routing::STATUS_PENDING_APPROVAL]);
            $this->routingLog($routing, RoutingApproval::ACTION_SUBMITTED, 'Submitted for engineering and production manager approval.');
            app(RoutingLifecycleService::class)->transitionOnApproval($routing->fresh(), $this->userId);
            $count++;
        }

        return $count;
    }

    private function seedOperations(Routing $routing, array $operations): void
    {
        $calculator = app(RoutingOperationCostCalculator::class);
        $sectionService = app(SectionCalculationService::class);
        $created = [];          // sequence => RoutingOperation
        $previousPrimary = null; // nearest earlier non-alternative operation

        foreach ($operations as $data) {
            $opNumber = 'OP-' . str_pad((string) $data['seq'], 3, '0', STR_PAD_LEFT);
            $primary = isset($data['alt_of']) ? ($created[$data['alt_of']] ?? throw new \RuntimeException("{$routing->routing_number} {$opNumber}: alternative of an unknown operation {$data['alt_of']}.")) : null;

            if (isset($data['external'])) {
                $model = RoutingOperation::create($this->outsourcedOperation($routing, $data, $opNumber, $primary, $previousPrimary, $calculator));
                $this->linkQualityPlans($model, null, $data['qc'] ?? []);
                $created[$data['seq']] = $model;
                if ($primary === null) {
                    $previousPrimary = $model;
                }
                continue;
            }

            $section = WorkCenterSection::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->where('code', $data['section'])->firstOrFail();

            $op = [
                'tenant_id' => $this->tenantId,
                'routing_id' => $routing->id,
                'sequence' => $data['seq'],
                'operation_number' => $opNumber,
                'name' => $data['name'],
                'operation_type' => empty($data['qc']) ? RoutingOperation::TYPE_MANUFACTURING : RoutingOperation::TYPE_INSPECTION, // as the routing form does
                'work_center_id' => $section->work_center_id,
                'section_id' => $section->id,
                'machine_id' => isset($data['machine']) ? $this->links->machineId($data['machine']) : null,
                'section_manual_production_id' => isset($data['batch']) ? $this->links->batchId($data['batch']) : null,
                'is_alternative' => $primary !== null,
                'replaces_operation_id' => $primary?->id,
                'priority' => 1, // NOT NULL, default 1 (RoutingOperationDTO always writes 1; one alternative per primary here)
                // alternatives take their primary's predecessor; primaries chain to the previous primary (RoutingService rule)
                'previous_operation_id' => $primary ? $primary->previous_operation_id : $previousPrimary?->id,
                'setup_time_minutes' => (float) ($data['setup'] ?? 0),
                'setup_time_per_piece_minutes' => (float) ($data['per_piece'] ?? 0),
                'wait_time_minutes' => 0,
                'expected_yield_percentage' => (float) ($data['yield'] ?? 100),
                'quality_required' => !empty($data['qc']),
                'is_external' => false,
            ];

            $resources = $sectionService->getSnapshotResourceCosts($section);
            if (!empty($resources['not_calculated']) || !empty($resources['has_pending_changes'])) {
                throw new \RuntimeException("{$routing->routing_number} {$opNumber}: section {$section->code} has no current calculation snapshot. TestProductionMasterDataSeeder recalculates the work centres first.");
            }
            $result = $calculator->calculate($op, $resources);
            if ($result['error'] !== null) {
                throw new \RuntimeException("{$routing->routing_number} {$opNumber}: the resource is not in the latest snapshot of {$section->code} ({$result['error']}).");
            }
            foreach (RoutingOperationCostCalculator::FIELDS as $field) {
                $op[$field] = $result['figures'][$field] ?? 0;
            }
            $op['resource_snapshot'] = $calculator->buildResourceSnapshot($op, $result['resource'], $resources);
            $op['section_calculation_id'] = $resources['section_calculation_id'] ?? null;
            $op['section_snapshot_number'] = $resources['snapshot_number'] ?? null;
            $op['work_center_calculation_id'] = $resources['work_center_calculation_id'] ?? null;
            $op['costs_calculated_at'] = now();

            $model = RoutingOperation::create($op);
            $this->linkQualityPlans($model, $section, $data['qc'] ?? []);

            $created[$data['seq']] = $model;
            if ($primary === null) {
                $previousPrimary = $model;
            }
        }
    }

    /**
     * An outsourced operation, saved the way RoutingOperationDTO saves one from the routing form (SF-1,
     * docs/production_ui_rules_review_2026-11-27.md): type 'outsourcing', no work centre / section / machine / batch,
     * no setup or run time; only the vendor and the three times in hours (dispatch, processing, receipt — calendar).
     * No rate (cost 0, the job work is charged on the vendor bill), resource snapshot type 'outsourced'. What goes to
     * the vendor is decided in the BOM (Previous OP materials / rows / Vendor-supplied materials tick).
     */
    private function outsourcedOperation(Routing $routing, array $data, string $opNumber, ?RoutingOperation $primary, ?RoutingOperation $previousPrimary, RoutingOperationCostCalculator $calculator): array
    {
        $ext = $data['external'];
        $where = "{$routing->routing_number} {$opNumber}";
        $vendorId = $this->links->vendorId($ext['vendor']);
        if (strcasecmp((string) DB::table('vendors')->where('id', $vendorId)->value('status'), 'active') !== 0) {
            throw new \RuntimeException("{$where}: vendor {$ext['vendor']} is not active.");
        }
        $previousId = $primary ? $primary->previous_operation_id : $previousPrimary?->id;

        $op = [
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'sequence' => $data['seq'],
            'operation_number' => $opNumber,
            'name' => $data['name'],
            'operation_type' => RoutingOperation::TYPE_OUTSOURCING, // RoutingOperationDTO: an outsourced op is always 'outsourcing'
            'work_center_id' => null,
            'section_id' => null,
            'machine_id' => null,
            'section_manual_production_id' => null,
            'is_alternative' => $primary !== null,
            'replaces_operation_id' => $primary?->id,
            'priority' => 1,
            'previous_operation_id' => $previousId,
            'setup_time_minutes' => 0,
            'setup_time_per_piece_minutes' => null,
            'processing_time_minutes' => 0,
            'wait_time_minutes' => 0,
            'expected_yield_percentage' => (float) ($data['yield'] ?? 100),
            'quality_required' => !empty($data['qc']),
            'is_external' => true,
            'vendor_id' => $vendorId,
            'vendor_dispatch_hours' => (float) $ext['dispatch_h'],
            'vendor_processing_hours' => (float) $ext['processing_h'],
            'vendor_receipt_hours' => (float) $ext['receipt_h'],
        ];

        $result = $calculator->calculate($op, null);
        foreach (RoutingOperationCostCalculator::FIELDS as $field) {
            $op[$field] = $result['figures'][$field] ?? 0;
        }
        $op['resource_snapshot'] = $calculator->buildResourceSnapshot($op, null, null);
        $op['section_calculation_id'] = null;
        $op['section_snapshot_number'] = null;
        $op['work_center_calculation_id'] = null;
        $op['costs_calculated_at'] = now();

        return $op;
    }

    /**
     * QR-15 / QR-16: the gate (quality_required) is on, the mapping table holds the plans with their Mandatory flag.
     * Same rules as ValidatesRoutingOperations::validateRoutingOperationQualityPlans — approved section plans of the
     * operation's own section or process plans — except a link marked 'outdated', the SD-02 demo of an old version.
     * An outsourced operation ($section null) can only carry process plans.
     */
    private function linkQualityPlans(RoutingOperation $op, ?WorkCenterSection $section, array $links): void
    {
        foreach (array_values($links) as $i => $link) {
            $query = ProductionQualityPlan::where('tenant_id', $this->tenantId)->where('name', $link['plan']);
            $plan = isset($link['version'])
                ? $query->where('version', (string) $link['version'])->first()
                : $query->where('status', ProductionQualityPlan::STATUS_APPROVED)->first();

            $where = "{$op->operation_number} {$op->name}";
            if (!$plan) {
                throw new \RuntimeException("Test routing {$where}: quality plan '{$link['plan']}'" . (isset($link['version']) ? " v{$link['version']}" : ' (approved)') . ' not found. TestProductionQualitySeeder must run first.');
            }
            if ($plan->type === ProductionQualityPlan::SCOPE_PRODUCT) {
                throw new \RuntimeException("Test routing {$where}: product plans cannot be linked to a routing ({$plan->name}).");
            }
            if ($plan->type === ProductionQualityPlan::SCOPE_SECTION && (int) $plan->section_id !== (int) $section?->id) {
                throw new \RuntimeException("Test routing {$where}: section plan {$plan->name} " . ($section ? "belongs to another section than {$section->code}." : 'cannot be linked to an outsourced operation (no section).'));
            }
            if (!$plan->isApproved() && empty($link['outdated'])) {
                throw new \RuntimeException("Test routing {$where}: quality plan {$plan->name} v{$plan->version} is not approved.");
            }

            RoutingOperationQualityPlan::create([
                'tenant_id' => $this->tenantId,
                'routing_operation_id' => $op->id,
                'quality_plan_id' => $plan->id,
                'is_mandatory' => (bool) ($link['mandatory'] ?? true),
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function routingLog(Routing $routing, string $action, string $comments): void
    {
        RoutingApproval::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'user_id' => $this->userId,
            'action' => $action,
            'comments' => $comments,
        ]);
    }

    // ─── BOMs ────────────────────────────────────────────────────────────────────

    private function seedBoms(): int
    {
        $count = 0;
        foreach ($this->boms() as $data) {
            $version = $data['version'] ?? '1.0.0';
            if (ProductionBom::withTrashed()->where('tenant_id', $this->tenantId)->where('bom_number', $data['bom_number'])->where('version', $version)->exists()) {
                continue;
            }

            $productId = $this->links->productId($data['product']);
            $routing = Routing::where('tenant_id', $this->tenantId)->where('routing_number', $data['routing'])->firstOrFail();
            $previous = ProductionBom::where('tenant_id', $this->tenantId)->where('bom_number', $data['bom_number'])->orderByDesc('id')->first();

            $bom = ProductionBom::create([
                'tenant_id' => $this->tenantId,
                'bom_number' => $data['bom_number'],
                'bom_name' => $data['name'],
                'bom_type' => $data['bom_type'] ?? 'manufacturing',
                'usage_context' => 'manufacturing',
                'product_id' => $productId,
                'routing_id' => $routing->id,
                'base_quantity' => 1,
                'base_uom_id' => $this->productUomId($productId),
                'version' => $version,
                'revision' => $previous ? ((int) $previous->revision + 1) : 0,
                'revision_reason' => $data['revision_reason'] ?? null,
                'effective_date' => now()->toDateString(),
                'status' => 'draft',
                'has_warning' => false,
                'operation_input_flags' => $this->operationInputFlags($routing),
                'operation_vendor_material_flags' => $this->vendorMaterialFlags($routing, $data['vendor_ops'] ?? []),
                'created_by' => $this->userId,
            ]);
            $this->bomLog($bom, 'Created', 'Initial BOM draft created.');
            if ($previous) {
                $this->bomLog($bom, 'Revision Created', "Version {$version} created from version {$previous->version}.");
            }

            $this->seedAllocations($bom, $routing, $data['materials']);

            $bom->update(['status' => 'pending_approval']);
            $this->bomLog($bom, 'Submitted', 'Submitted for engineering review and approval.');
            app(BomLifecycleService::class)->transitionOnApproval($bom->fresh(), $this->userId); // supersedes an older version of the product
            $this->bomLog($bom, 'Approved', 'BOM version approved and set active.');
            $count++;
        }

        return $count;
    }

    /**
     * TF-01 "Previous OP materials": the BOM form posts a flag for every operation of the routing (hidden input per
     * card), so a saved BOM always holds one. Same default as the form: on for an operation that has a predecessor
     * and is not parallel, off for the first operation.
     *
     * @return array<string, bool> routing_operation_id => uses the previous operation's output
     */
    private function operationInputFlags(Routing $routing): array
    {
        return RoutingOperation::where('routing_id', $routing->id)->orderBy('sequence')->get()
            ->mapWithKeys(fn (RoutingOperation $op) => [(string) $op->id => !empty($op->previous_operation_id) && !$op->is_parallel])
            ->all();
    }

    /**
     * SF-2 "Vendor-supplied materials": the BOM tick per outsourced operation (only ticked operations are stored).
     *
     * @param int[] $sequences operation sequences ticked in the BOM data ('vendor_ops')
     * @return array<string, bool>
     */
    private function vendorMaterialFlags(Routing $routing, array $sequences): array
    {
        if ($sequences === []) {
            return [];
        }
        $ops = RoutingOperation::where('routing_id', $routing->id)->whereIn('sequence', $sequences)->get();
        foreach ($ops as $op) {
            if (!$op->isOutsourced()) {
                throw new \RuntimeException("{$routing->routing_number} {$op->operation_number}: only an outsourced operation can have vendor-supplied materials.");
            }
        }

        return $ops->mapWithKeys(fn (RoutingOperation $op) => [(string) $op->id => true])->all();
    }

    /**
     * Operation allocations (production_routing_operation_materials) and the master rows (production_bom_items) derived
     * from them exactly like ProductionBomService::persistBomItems: per material, quantity = primary allocations,
     * alternative_quantity = alternative allocations (a row on an alternative operation counts as alternative),
     * gross = primary × (1 + scrap %), role primary / alternative / mixed.
     */
    private function seedAllocations(ProductionBom $bom, Routing $routing, array $materials): void
    {
        $ops = RoutingOperation::where('routing_id', $routing->id)->get()->keyBy('sequence');
        $rows = [];
        foreach ($materials as $m) {
            $op = $ops[$m['op']] ?? throw new \RuntimeException("{$bom->bom_number} v{$bom->version}: routing {$routing->routing_number} has no operation {$m['op']}.");
            $materialId = $this->links->productId($m['material']);
            $rows[] = $m + [
                'material_id' => $materialId,
                'operation' => $op,
                'uom_id' => $this->productUomId($materialId),
                'is_alternative' => isset($m['alt_of']),
                'child_bom_id' => isset($m['child']) ? $this->currentChildBomId($m['child'], $materialId, $bom) : null,
            ];
        }

        $items = [];
        foreach (collect($rows)->groupBy('material_id') as $materialId => $group) {
            $primary = $alternative = $gross = 0.0;
            foreach ($group as $row) {
                if ($row['is_alternative'] || $row['operation']->is_alternative) {
                    $alternative += (float) $row['qty'];
                } else {
                    $primary += (float) $row['qty'];
                    $gross += (float) $row['qty'] * (1 + ((float) ($row['scrap'] ?? 0)) / 100);
                }
            }
            $items[$materialId] = ProductionBomItem::create([
                'tenant_id' => $this->tenantId,
                'bom_id' => $bom->id,
                'material_id' => $materialId,
                'quantity' => $primary,
                'alternative_quantity' => round($alternative, 6),
                'gross_quantity' => round($gross, 6),
                'uom_id' => $group->first()['uom_id'],
                'allocation_role' => $primary > 0 && $alternative > 0 ? 'mixed' : ($alternative > 0 ? 'alternative' : 'primary'),
                'material_scrap_percentage' => 0.00,
            ]);
        }

        $byOpAndMaterial = []; // "opId:materialId" => primary allocation, for alternative materials
        foreach ($rows as $i => $row) {
            $scrap = (float) ($row['scrap'] ?? 0);
            $replaces = null;
            if ($row['is_alternative']) {
                $replaces = $byOpAndMaterial[$row['operation']->id . ':' . $this->links->productId($row['alt_of'])]
                    ?? throw new \RuntimeException("{$bom->bom_number}: alternative {$row['material']} replaces {$row['alt_of']}, which is not a primary on the same operation (list the primary first).");
            }

            $allocation = RoutingOperationMaterial::create([
                'tenant_id' => $this->tenantId,
                'bom_id' => $bom->id,
                'bom_item_id' => $items[$row['material_id']]->id,
                'routing_operation_id' => $row['operation']->id,
                'material_id' => $row['material_id'],
                'child_bom_id' => $row['child_bom_id'],
                'quantity' => (float) $row['qty'],
                'gross_quantity' => RoutingOperationMaterial::calculateGrossQuantity((float) $row['qty'], $scrap),
                'uom_id' => $row['uom_id'],
                'is_alternative' => $row['is_alternative'],
                'priority' => $row['is_alternative'] ? (int) ($row['priority'] ?? 1) : null,
                'replaces_routing_material_id' => $replaces?->id,
                'material_scrap_percentage' => $scrap,
                'sequence' => $i + 1,
            ]);

            if (!$row['is_alternative']) {
                $byOpAndMaterial[$row['operation']->id . ':' . $row['material_id']] = $allocation;
            }
        }
    }


    /** BM-08: the sub-BOM link always points at the child's current approved version, made for that material. */
    private function currentChildBomId(string $bomNumber, int $materialId, ProductionBom $parent): int
    {
        $child = ProductionBom::where('tenant_id', $this->tenantId)->where('bom_number', $bomNumber)->where('status', 'approved')->orderByDesc('id')->first();
        if (!$child) {
            throw new \RuntimeException("{$parent->bom_number}: sub-BOM {$bomNumber} has no approved version yet (children are seeded before their parents).");
        }
        if ((int) $child->product_id !== $materialId) {
            throw new \RuntimeException("{$parent->bom_number}: sub-BOM {$bomNumber} is not a BOM of that material.");
        }

        return (int) $child->id;
    }

    private function productUomId(int $productId): int
    {
        return (int) (DB::table('products')->where('id', $productId)->value('uom_id')
            ?? throw new \RuntimeException("Test production data: product {$productId} has no unit."));
    }

    private function bomLog(ProductionBom $bom, string $action, string $comments): void
    {
        ProductionBomApproval::create([
            'tenant_id' => $this->tenantId,
            'bom_id' => $bom->id,
            'user_id' => $this->userId,
            'action' => $action,
            'comments' => $comments,
        ]);
    }

    // ─── Data ────────────────────────────────────────────────────────────────────

    /** @return array<int, array{plan: string, mandatory: bool}> */
    private static function qc(array $mandatory, array $optional = []): array
    {
        return array_merge(
            array_map(fn ($plan) => ['plan' => $plan, 'mandatory' => true], $mandatory),
            array_map(fn ($plan) => ['plan' => $plan, 'mandatory' => false], $optional),
        );
    }

    /**
     * 12 routings. Operations: section code, one machine (code) or one manual batch (name), setup per run / per piece in
     * minutes, yield %, 'alt_of' = the primary's sequence, 'qc' = linked plans in order (mandatory first); an outsourced
     * operation has 'external' instead of section / machine / batch.
     *
     * @return array<int, array<string, mixed>>
     */
    public function routings(): array
    {
        $dimensional = 'Dimensional check — general';
        $visual = 'Visual surface check — general';
        $safety = 'Safety & sharp-edge check — general';
        $cleanliness = 'Cleanliness check — general';
        $subcontractReceipt = 'Subcontract receipt check — general';

        return [
            ['routing_number' => 'RTG-2026-000001', 'name' => 'Tube Cutting & Deburring', 'description' => 'Steel tube cut to length (cold saw, plasma as alternative) and deburred.', 'operations' => [
                ['seq' => 10, 'name' => 'Cut tube to length', 'section' => 'TEST-SEC-1', 'machine' => 'TEST-MAH-5', 'setup' => 10, 'yield' => 98, 'qc' => self::qc(['Cut length check'], [$dimensional])],
                ['seq' => 20, 'name' => 'Cut tube to length — plasma', 'alt_of' => 10, 'section' => 'TEST-SEC-1', 'machine' => 'TEST-MAH-9', 'setup' => 8, 'yield' => 98, 'qc' => self::qc(['Cut length check'])],
                ['seq' => 30, 'name' => 'Deburr & clean', 'section' => 'TEST-SEC-3', 'batch' => 'test-batch-1', 'setup' => 5, 'per_piece' => 0.5, 'qc' => self::qc(['Edge deburr check', $safety])],
            ]],
            ['routing_number' => 'RTG-2026-000002', 'name' => 'Sheet Blanking & Bending', 'description' => 'CRCA sheet laser-blanked, bent (press brake, forge press as alternative) and deburred.', 'operations' => [
                ['seq' => 10, 'name' => 'Laser blanking', 'section' => 'TEST-SEC-1', 'machine' => 'TEST-MAH-1', 'setup' => 15],
                ['seq' => 20, 'name' => 'Press-brake bending', 'section' => 'TEST-SEC-2', 'machine' => 'TEST-MAH-2', 'setup' => 12, 'qc' => self::qc(['Bend angle check'])],
                ['seq' => 30, 'name' => 'Forge-press bending', 'alt_of' => 20, 'section' => 'TEST-SEC-2', 'machine' => 'TEST-MAH-14', 'setup' => 15, 'qc' => self::qc(['Bend angle check'], [$cleanliness])],
                ['seq' => 40, 'name' => 'Deburr', 'section' => 'TEST-SEC-3', 'batch' => 'test-batch-1', 'setup' => 5, 'per_piece' => 0.5, 'qc' => self::qc([$dimensional], ['Edge deburr check', $safety])],
            ]],
            ['routing_number' => 'RTG-2026-000003', 'name' => 'CNC Panel Machining & Finishing', 'description' => 'Wood panel routed and chamfered on CNC (5-axis as alternative), then hand finished.', 'operations' => [
                ['seq' => 10, 'name' => 'CNC route & chamfer', 'section' => 'TEST-SEC-7', 'machine' => 'TEST-MAH-3', 'setup' => 15],
                ['seq' => 20, 'name' => 'CNC route & chamfer — 5-axis', 'alt_of' => 10, 'section' => 'TEST-SEC-7', 'machine' => 'TEST-MAH-13', 'setup' => 20],
                ['seq' => 30, 'name' => 'Hand finishing', 'section' => 'TEST-SEC-6', 'batch' => 'test-batch-6', 'setup' => 5, 'per_piece' => 1, 'qc' => self::qc(['Hand finish touch test', $visual])],
            ]],
            ['routing_number' => 'RTG-2026-000004', 'name' => 'Robotic Weld & Powder Coat', 'description' => 'Robotic welding of steel parts followed by electrostatic powder coating.', 'operations' => [
                ['seq' => 10, 'name' => 'Robotic welding', 'section' => 'TEST-SEC-8', 'machine' => 'TEST-MAH-4', 'setup' => 10, 'qc' => self::qc(['Weld inspection', 'Weld spatter & cleanup'], [$dimensional])],
                ['seq' => 20, 'name' => 'Powder coating', 'section' => 'TEST-SEC-8', 'machine' => 'TEST-MAH-6', 'setup' => 15, 'qc' => self::qc(['Powder coat inspection'])],
            ]],
            ['routing_number' => 'RTG-2026-000005', 'name' => 'Profile Cutting & Coating', 'description' => 'Profiles cut to length, pre-washed and powder coated.', 'operations' => [
                ['seq' => 10, 'name' => 'Cut to length', 'section' => 'TEST-SEC-1', 'machine' => 'TEST-MAH-5', 'setup' => 10, 'yield' => 98, 'qc' => self::qc([], ['Cut length check'])],
                ['seq' => 20, 'name' => 'Pre-wash & powder coat', 'section' => 'TEST-SEC-8', 'machine' => 'TEST-MAH-6', 'setup' => 15, 'qc' => self::qc(['Powder coat inspection'], [$cleanliness])],
            ]],
            ['routing_number' => 'RTG-2026-000006', 'name' => 'Frame Assembly & Priming', 'description' => 'Aluminium frame assembled with flange plates, then primed and cured.', 'operations' => [
                ['seq' => 10, 'name' => 'Frame assembly', 'section' => 'TEST-SEC-9', 'batch' => 'test-batch-9A', 'setup' => 10, 'per_piece' => 1, 'qc' => self::qc(['Assembly torque check', $dimensional])],
                ['seq' => 20, 'name' => 'Priming & curing', 'section' => 'TEST-SEC-9', 'machine' => 'TEST-MAH-15', 'setup' => 10, 'qc' => self::qc([], [$visual, $cleanliness])],
            ]],
            ['routing_number' => 'RTG-2026-000007', 'name' => 'Top Board Sealing', 'description' => 'Top board edges sealed, gasket fitted and threaded inserts set.', 'operations' => [
                ['seq' => 10, 'name' => 'Seal edges & fit inserts', 'section' => 'TEST-SEC-5', 'batch' => 'test-batch-5', 'setup' => 5, 'per_piece' => 1, 'qc' => self::qc(['Seal bead check', $visual], [$safety])],
            ]],
            ['routing_number' => 'RTG-2026-000008', 'name' => 'Final Assembly, Inspection & Packing', 'description' => 'Table assembled (day or morning crew), inspected and crated, then machine packed.', 'operations' => [
                ['seq' => 10, 'name' => 'Table assembly', 'section' => 'TEST-SEC-9', 'batch' => 'test-batch-9A', 'setup' => 10, 'per_piece' => 1, 'qc' => self::qc(['Assembly torque check'])],
                ['seq' => 20, 'name' => 'Table assembly — morning crew', 'alt_of' => 10, 'section' => 'TEST-SEC-9', 'batch' => 'test-batch-9B', 'setup' => 10, 'per_piece' => 1, 'qc' => self::qc(['Assembly torque check'], [$safety])],
                ['seq' => 30, 'name' => 'Final inspection & crating', 'section' => 'TEST-SEC-10', 'batch' => 'test-batch-10A', 'setup' => 5, 'per_piece' => 1, 'qc' => self::qc(['Final crating inspection', 'Packing count & seal'], [$dimensional])],
                ['seq' => 40, 'name' => 'Automated packing', 'section' => 'TEST-SEC-10', 'machine' => 'TEST-MAH-7', 'setup' => 5],
            ]],
            // SD-02: links v1 of "Flange hole check" (historical once v2 was approved) — the routing shows the newer-version
            // warning and new orders of test-semi-03 are blocked (QR-09 / QR-19)
            ['routing_number' => 'RTG-2026-000009', 'name' => 'Flange Drilling (QC version demo)', 'description' => 'Flange plate drilled and tapped. Its QC still links version 1 of the flange hole check on purpose.', 'operations' => [
                ['seq' => 10, 'name' => 'CNC drill & tap', 'section' => 'TEST-SEC-7', 'machine' => 'TEST-MAH-13', 'setup' => 15, 'qc' => [
                    ['plan' => 'Flange hole check', 'version' => 1, 'outdated' => true, 'mandatory' => true],
                    ['plan' => $dimensional, 'mandatory' => true],
                ]],
            ]],

            // ── Subcontract examples (Part C of the doc). 'external' = outsourced operation (SF-1): vendor code and the
            // three times in hours — dispatch (our floor → vendor), processing (the vendor's work), receipt (back).
            // What goes to the vendor is set in the BOM. An outsourced operation has no section, so its QC can only be
            // process plans.

            // two job works with different vendors in one routing: raw material sent to the laser cutter (first
            // operation), in-house deburr, then the deburred plates go to the plater (WIP job work, last operation)
            ['routing_number' => 'RTG-2026-000010', 'name' => 'Gusset Plate: Job-Work Laser Cutting & Zinc Plating', 'description' => 'CRCA sheet sent to the laser cutter, plates deburred in-house, then zinc plated by a second vendor.', 'operations' => [
                ['seq' => 10, 'name' => 'Laser cutting — job work', 'yield' => 98, 'external' => [
                    'vendor' => 'VEND-JW-LASER', 'dispatch_h' => 6, 'processing_h' => 48, 'receipt_h' => 6,
                ], 'qc' => self::qc([$subcontractReceipt], [$dimensional])],
                ['seq' => 20, 'name' => 'Deburr plates', 'section' => 'TEST-SEC-3', 'batch' => 'test-batch-1', 'setup' => 5, 'per_piece' => 0.5, 'qc' => self::qc(['Edge deburr check'])],
                ['seq' => 30, 'name' => 'Zinc plating — job work', 'external' => [
                    'vendor' => 'VEND-JW-ZINC', 'dispatch_h' => 4, 'processing_h' => 72, 'receipt_h' => 4,
                ], 'qc' => self::qc(['Zinc plating check — general', $subcontractReceipt])],
            ]],
            // fully outsourced: the vendor makes the rail from its own tube and powder — nothing is sent, nothing is
            // requested from the store; received on the shop floor (MES) only
            ['routing_number' => 'RTG-2026-000011', 'name' => 'Support Rail: Fully Outsourced (vendor material)', 'description' => 'The vendor makes and coats the rail from its own material; we receive and inspect it.', 'operations' => [
                ['seq' => 10, 'name' => 'Make & coat support rail — job work', 'external' => [
                    'vendor' => 'VEND-JW-COAT', 'dispatch_h' => 0, 'processing_h' => 96, 'receipt_h' => 24,
                ], 'qc' => self::qc([$visual])],
            ]],
            // in-house welding, the welded frame sent for hot-dip galvanising (WIP job work, QC off), then powder coated
            // in-house — or, as the alternative, at the coater with our powder sent along (WIP + company material)
            ['routing_number' => 'RTG-2026-000012', 'name' => 'Outdoor Frame: Weld, Job-Work Galvanising & Coating', 'description' => 'Frame welded in-house, galvanised by a vendor, then powder coated in-house or at a coating vendor.', 'operations' => [
                ['seq' => 10, 'name' => 'Frame welding', 'section' => 'TEST-SEC-8', 'machine' => 'TEST-MAH-4', 'setup' => 10, 'qc' => self::qc(['Weld inspection'])],
                ['seq' => 20, 'name' => 'Hot-dip galvanising — job work', 'external' => [
                    'vendor' => 'VEND-JW-GALVA', 'dispatch_h' => 24, 'processing_h' => 120, 'receipt_h' => 36,
                ]],
                ['seq' => 30, 'name' => 'Powder coating', 'section' => 'TEST-SEC-8', 'machine' => 'TEST-MAH-6', 'setup' => 15, 'qc' => self::qc(['Powder coat inspection'])],
                ['seq' => 40, 'name' => 'Powder coating — job work', 'alt_of' => 30, 'external' => [
                    'vendor' => 'VEND-JW-COAT', 'dispatch_h' => 8, 'processing_h' => 72, 'receipt_h' => 8,
                ], 'qc' => self::qc([$subcontractReceipt], [$visual])],
            ]],
        ];
    }

    /**
     * 17 BOM rows, children before parents. Materials: 'op' = routing operation sequence, quantity per 1 unit in the
     * material's own unit, 'scrap' %, 'alt_of' = the primary material on the same operation, 'child' = sub-BOM number,
     * 'vendor' = Vendor-supplied (outsourced operations only).
     * An alternative operation repeats the materials of its primary.
     *
     * @return array<int, array<string, mixed>>
     */
    public function boms(): array
    {
        return [
            ['bom_number' => 'BOM-TEST-SEMI-01', 'product' => 'test-semi-01', 'name' => 'Steel leg column', 'routing' => 'RTG-2026-000001', 'materials' => [
                ['op' => 10, 'material' => 'test-raw-01', 'qty' => 0.72],
                ['op' => 10, 'material' => 'test-raw-02', 'qty' => 0.72, 'alt_of' => 'test-raw-01', 'priority' => 1],
                ['op' => 10, 'material' => 'test-raw-11', 'qty' => 0.02],
                ['op' => 20, 'material' => 'test-raw-01', 'qty' => 0.72],
                ['op' => 20, 'material' => 'test-raw-11', 'qty' => 0.02],
            ]],
            ['bom_number' => 'BOM-TEST-SEMI-02', 'product' => 'test-semi-02', 'name' => 'Press-brake bracket', 'routing' => 'RTG-2026-000002', 'materials' => [
                ['op' => 10, 'material' => 'test-raw-14', 'qty' => 0.1], // 10 brackets per sheet
            ]],
            ['bom_number' => 'BOM-TEST-SEMI-03', 'product' => 'test-semi-03', 'name' => 'Machined flange plate', 'routing' => 'RTG-2026-000009', 'materials' => [
                ['op' => 10, 'material' => 'test-raw-16', 'qty' => 1],
                ['op' => 10, 'material' => 'test-raw-11', 'qty' => 0.01],
            ]],
            // SD-04: v1.0.0 is superseded by v1.1.0 (BomLifecycleService turns it inactive)
            ['bom_number' => 'BOM-TEST-SEMI-04', 'product' => 'test-semi-04', 'name' => 'Routed top panel', 'version' => '1.0.0', 'routing' => 'RTG-2026-000003', 'materials' => [
                ['op' => 10, 'material' => 'test-raw-15', 'qty' => 1],
                ['op' => 20, 'material' => 'test-raw-15', 'qty' => 1],
            ]],
            ['bom_number' => 'BOM-TEST-SEMI-04', 'product' => 'test-semi-04', 'name' => 'Routed top panel', 'version' => '1.1.0', 'revision_reason' => 'Edge seal added at hand finishing.', 'routing' => 'RTG-2026-000003', 'materials' => [
                ['op' => 10, 'material' => 'test-raw-15', 'qty' => 1],
                ['op' => 20, 'material' => 'test-raw-15', 'qty' => 1],
                ['op' => 30, 'material' => 'test-raw-13', 'qty' => 0.05],
            ]],
            ['bom_number' => 'BOM-TEST-SEMI-05', 'product' => 'test-semi-05', 'name' => 'Welded corner brace leg', 'routing' => 'RTG-2026-000004', 'materials' => [
                ['op' => 10, 'material' => 'test-semi-01', 'qty' => 1, 'child' => 'BOM-TEST-SEMI-01'],
                ['op' => 10, 'material' => 'test-semi-02', 'qty' => 1, 'child' => 'BOM-TEST-SEMI-02'],
                ['op' => 10, 'material' => 'test-raw-12', 'qty' => 0.05],
                ['op' => 20, 'material' => 'test-raw-07', 'qty' => 0.05],
            ]],
            ['bom_number' => 'BOM-TEST-SEMI-06', 'product' => 'test-semi-06', 'name' => 'Powder-coated stiffener', 'routing' => 'RTG-2026-000005', 'materials' => [
                ['op' => 10, 'material' => 'test-raw-01', 'qty' => 0.8],
                ['op' => 20, 'material' => 'test-raw-07', 'qty' => 0.1],
            ]],
            ['bom_number' => 'BOM-TEST-SEMI-07', 'product' => 'test-semi-07', 'name' => 'Coated aluminium profile (per metre)', 'routing' => 'RTG-2026-000005', 'materials' => [
                ['op' => 10, 'material' => 'test-raw-02', 'qty' => 1, 'scrap' => 2],
                ['op' => 20, 'material' => 'test-raw-25', 'qty' => 0.05],
                ['op' => 20, 'material' => 'test-raw-07', 'qty' => 0.08],
            ]],
            ['bom_number' => 'BOM-TEST-SEMIFINAL-01', 'product' => 'test-semifinal-01', 'name' => 'Welded base chassis', 'routing' => 'RTG-2026-000004', 'materials' => [
                ['op' => 10, 'material' => 'test-semi-05', 'qty' => 4, 'child' => 'BOM-TEST-SEMI-05'],
                ['op' => 10, 'material' => 'test-semi-06', 'qty' => 2, 'child' => 'BOM-TEST-SEMI-06'],
                ['op' => 10, 'material' => 'test-raw-12', 'qty' => 0.2],
                ['op' => 20, 'material' => 'test-raw-10', 'qty' => 0.5],
                ['op' => 20, 'material' => 'test-raw-07', 'qty' => 0.3],
            ]],
            ['bom_number' => 'BOM-TEST-SEMIFINAL-02', 'product' => 'test-semifinal-02', 'name' => 'Aluminium frame assembly', 'routing' => 'RTG-2026-000006', 'materials' => [
                ['op' => 10, 'material' => 'test-semi-07', 'qty' => 5.4, 'child' => 'BOM-TEST-SEMI-07'],
                ['op' => 10, 'material' => 'test-semi-03', 'qty' => 4, 'child' => 'BOM-TEST-SEMI-03'],
                ['op' => 20, 'material' => 'test-raw-08', 'qty' => 0.2],
                ['op' => 20, 'material' => 'test-raw-09', 'qty' => 0.05],
            ]],
            ['bom_number' => 'BOM-TEST-SEMIFINAL-03', 'product' => 'test-semifinal-03', 'name' => 'Sealed top board', 'routing' => 'RTG-2026-000007', 'materials' => [
                ['op' => 10, 'material' => 'test-semi-04', 'qty' => 1, 'child' => 'BOM-TEST-SEMI-04'], // resolves to v1.1.0
                ['op' => 10, 'material' => 'test-raw-13', 'qty' => 0.3],
                ['op' => 10, 'material' => 'test-raw-04', 'qty' => 5.4],
            ]],
            ['bom_number' => 'BOM-TEST-FINAL-01', 'product' => 'test-final-01', 'name' => 'Executive conference table 1800×900', 'routing' => 'RTG-2026-000008', 'materials' => [
                ['op' => 10, 'material' => 'test-semifinal-01', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-01'],
                ['op' => 10, 'material' => 'test-semifinal-03', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-03'],
                ['op' => 10, 'material' => 'test-raw-23', 'qty' => 1],
                ['op' => 10, 'material' => 'test-raw-20', 'qty' => 4],
                ['op' => 20, 'material' => 'test-semifinal-01', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-01'],
                ['op' => 20, 'material' => 'test-semifinal-03', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-03'],
                ['op' => 20, 'material' => 'test-raw-23', 'qty' => 1],
                ['op' => 20, 'material' => 'test-raw-20', 'qty' => 4],
                ['op' => 40, 'material' => 'test-raw-21', 'qty' => 1],
                ['op' => 40, 'material' => 'test-raw-22', 'qty' => 1],
            ]],
            ['bom_number' => 'BOM-TEST-FINAL-02', 'product' => 'test-final-02', 'name' => 'Premium conference table 2400×1000', 'routing' => 'RTG-2026-000008', 'materials' => [
                ['op' => 10, 'material' => 'test-semifinal-02', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-02'],
                ['op' => 10, 'material' => 'test-semifinal-03', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-03'],
                ['op' => 10, 'material' => 'test-raw-23', 'qty' => 2],
                ['op' => 10, 'material' => 'test-raw-24', 'qty' => 1],
                ['op' => 10, 'material' => 'test-raw-20', 'qty' => 6],
                ['op' => 20, 'material' => 'test-semifinal-02', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-02'],
                ['op' => 20, 'material' => 'test-semifinal-03', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-03'],
                ['op' => 20, 'material' => 'test-raw-23', 'qty' => 2],
                ['op' => 20, 'material' => 'test-raw-24', 'qty' => 1],
                ['op' => 20, 'material' => 'test-raw-20', 'qty' => 6],
                ['op' => 40, 'material' => 'test-raw-21', 'qty' => 1],
                ['op' => 40, 'material' => 'test-raw-22', 'qty' => 2],
            ]],

            // ── Subcontract examples (Part C of the doc, SF-2). A material row is always ours, sent to the vendor;
            // 'vendor_ops' = outsourced operation sequences with the "Vendor-supplied materials" tick (the vendor uses
            // its own materials — no rows, nothing requested, they come on the vendor's bill).
            ['bom_number' => 'BOM-TEST-SEMI-09', 'product' => 'test-semi-09', 'name' => 'Laser-cut zinc-plated gusset plate', 'routing' => 'RTG-2026-000010', 'vendor_ops' => [30], 'materials' => [
                ['op' => 10, 'material' => 'test-raw-14', 'qty' => 0.125],                   // 8 plates per sheet: requested, issued, sent to the laser cutter
                // op 30: the deburred plates go (Previous OP materials) and the plater also uses its own chemicals (tick)
            ]],
            ['bom_number' => 'BOM-TEST-SEMI-10', 'product' => 'test-semi-10', 'name' => 'Vendor-made support rail', 'bom_type' => 'subcontracting', 'routing' => 'RTG-2026-000011', 'vendor_ops' => [10], 'materials' => [
                // op 10: the vendor makes the rail from its own tube and powder — nothing of ours goes, no dispatch slip
            ]],
            ['bom_number' => 'BOM-TEST-SEMIFINAL-04', 'product' => 'test-semifinal-04', 'name' => 'Galvanised outdoor table frame', 'routing' => 'RTG-2026-000012', 'materials' => [
                ['op' => 10, 'material' => 'test-semi-01', 'qty' => 4, 'child' => 'BOM-TEST-SEMI-01'],
                ['op' => 10, 'material' => 'test-semi-09', 'qty' => 4, 'child' => 'BOM-TEST-SEMI-09'],
                ['op' => 10, 'material' => 'test-semi-10', 'qty' => 2, 'child' => 'BOM-TEST-SEMI-10'],
                ['op' => 10, 'material' => 'test-raw-12', 'qty' => 0.25],
                ['op' => 30, 'material' => 'test-raw-07', 'qty' => 0.4],
                ['op' => 40, 'material' => 'test-raw-07', 'qty' => 0.4],   // the coating job work: our powder goes with the frame
            ]],
            ['bom_number' => 'BOM-TEST-FINAL-03', 'product' => 'test-final-03', 'name' => 'Outdoor cafe table 1200×750', 'routing' => 'RTG-2026-000008', 'materials' => [
                ['op' => 10, 'material' => 'test-semifinal-04', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-04'],
                ['op' => 10, 'material' => 'test-semifinal-03', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-03'],
                ['op' => 10, 'material' => 'test-raw-23', 'qty' => 1],
                ['op' => 10, 'material' => 'test-raw-20', 'qty' => 4],
                ['op' => 20, 'material' => 'test-semifinal-04', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-04'],
                ['op' => 20, 'material' => 'test-semifinal-03', 'qty' => 1, 'child' => 'BOM-TEST-SEMIFINAL-03'],
                ['op' => 20, 'material' => 'test-raw-23', 'qty' => 1],
                ['op' => 20, 'material' => 'test-raw-20', 'qty' => 4],
                ['op' => 40, 'material' => 'test-raw-21', 'qty' => 1],
                ['op' => 40, 'material' => 'test-raw-22', 'qty' => 1],
            ]],
        ];
    }
}
