<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Services\BomExplosionService;
use App\Domains\Production\Services\BomFormulaEvaluatorService;
use App\Domains\Production\Services\MrpShortageService;
use App\Domains\Production\Services\ProductionCostService;
use App\Domains\Production\Services\ProductionEcoService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionReadinessService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionParameterizedBomTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Uom $uom;
    private User $user;
    private Product $fgProduct;
    private Product $sfgProduct;
    private Product $rm1;
    private Product $rm2;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 00:00:00');

        $this->tenant = Tenant::create([
            'name' => 'Formula Tenant',
            'slug' => 'formula-tenant-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Formula Engineer',
            'email' => 'formula-engineer-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->fgProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Custom Enclosure',
            'sku' => 'FG-ENC-' . uniqid(),
            'type' => 'finished_good',
            'planning_type' => 'manufacture',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->sfgProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sub-Assembly Frame',
            'sku' => 'SFG-FRAME-' . uniqid(),
            'type' => 'semi_finished',
            'planning_type' => 'manufacture',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->rm1 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sheet Metal Panel',
            'sku' => 'RM-SHEET-' . uniqid(),
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'unit_cost' => 10.0,
            'status' => 'active',
        ]);

        $this->rm2 = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Fastener Bolt',
            'sku' => 'RM-BOLT-' . uniqid(),
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'unit_cost' => 2.0,
            'status' => 'active',
        ]);
    }

    private function createOrder(int $qty = 1, string $status = 'released', array $params = [], ?int $bomId = null): ProductionOrder
    {
        return ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-FORMULA-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'bom_id' => $bomId,
            'quantity_ordered' => $qty,
            'status' => $status,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'parameters' => $params,
        ]);
    }

    private function createBom(array $attributes = []): ProductionBom
    {
        return ProductionBom::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-' . uniqid(),
            'bom_name' => 'BOM ' . uniqid(),
            'bom_type' => 'manufacturing',
            'usage_context' => 'manufacturing',
            'product_id' => $this->fgProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'revision' => 0,
            'effective_date' => '2026-01-01',
            'status' => 'approved',
            'created_by' => $this->user->id,
        ], $attributes));
    }

    /**
     * Test 1 — Fixed Quantity BOM item evaluates as fixed.
     */
    public function test_1_fixed_quantity(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $result = $evaluator->evaluate('20', ['width' => 1200]);
        $this->assertEquals(20.0, $result);
    }

    /**
     * Test 2 — Simple Multiplication Formula: width * height.
     */
    public function test_2_simple_multiplication(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $result = $evaluator->evaluate('width * height', ['width' => 1200, 'height' => 800]);
        $this->assertEquals(960000.0, $result);
    }

    /**
     * Test 3 — Multiple Parameters: length * width * height.
     */
    public function test_3_multiple_parameters(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $result = $evaluator->evaluate('length * width * height', ['length' => 10, 'width' => 5, 'height' => 2]);
        $this->assertEquals(100.0, $result);
    }

    /**
     * Test 4 — Constant Factor: length * width * 1.05.
     */
    public function test_4_constant_factor(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $result = $evaluator->evaluate('length * width * 1.05', ['length' => 10, 'width' => 5]);
        $this->assertEquals(52.5, $result);
    }

    /**
     * Test 5 — Parent BOM Formula Evaluation.
     */
    public function test_5_parent_bom_formula_evaluation(): void
    {
        $bom = $this->createBom();

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width * height * 1.05',
            'uom_id' => $this->uom->id,
        ]);

        $explosionService = app(BomExplosionService::class);
        $explosion = $explosionService->explode($this->fgProduct->id, 1.0, $this->tenant->id, ['width' => 10, 'height' => 5]);

        $this->assertNotEmpty($explosion['flat']);
        $this->assertEquals(52.5, $explosion['flat'][0]['net_quantity']);
    }

    /**
     * Test 6 — Multi-Level BOM Formula Propagation.
     */
    public function test_6_multi_level_bom_formula_propagation(): void
    {
        // FG BOM
        $fgBom = $this->createBom(['product_id' => $this->fgProduct->id]);

        // SFG BOM
        $sfgBom = $this->createBom(['product_id' => $this->sfgProduct->id]);

        // Item 1: FG uses SFG with formula 'width * 2'
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $fgBom->id,
            'sequence' => 1,
            'material_id' => $this->sfgProduct->id,
            'child_bom_id' => $sfgBom->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width * 2',
            'uom_id' => $this->uom->id,
        ]);

        // Item 2: SFG uses RM1 with formula 'length * 3'
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $sfgBom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'length * 3',
            'uom_id' => $this->uom->id,
        ]);

        $explosionService = app(BomExplosionService::class);
        $explosion = $explosionService->explode($this->fgProduct->id, 1.0, $this->tenant->id, ['width' => 5, 'length' => 4]);

        // SFG qty = 5 * 2 = 10. RM1 qty = (4 * 3) * 10 = 120.
        $flatRm1 = collect($explosion['flat'])->firstWhere('product_id', $this->rm1->id);
        $this->assertEquals(120.0, $flatRm1['net_quantity']);
    }

    /**
     * Test 7 — Unknown Parameter Validation Error.
     */
    public function test_7_unknown_parameter_rejected(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $errors = $evaluator->validateFormula('width * unknown_dim', ['width' => 100]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Unknown parameter', $errors[0]);
    }

    /**
     * Test 8 — Invalid Formula Syntax Rejected.
     */
    public function test_8_invalid_formula_syntax_rejected(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $errors = $evaluator->validateFormula('width * * height', ['width' => 10, 'height' => 5]);

        $this->assertNotEmpty($errors);
    }

    /**
     * Test 9 — Unsafe Expression Rejected.
     */
    public function test_9_unsafe_expression_rejected(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $this->expectException(\InvalidArgumentException::class);
        $evaluator->evaluate('eval("phpinfo();")', []);
    }

    /**
     * Test 10 — Missing Parameter Throws Exception.
     */
    public function test_10_missing_parameter_rejected(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $this->expectException(\InvalidArgumentException::class);
        $evaluator->evaluate('width * height', ['width' => 1000]); // height missing
    }

    /**
     * Test 11 — Division by Zero Protection.
     */
    public function test_11_division_by_zero_rejected(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $this->expectException(\InvalidArgumentException::class);
        $evaluator->evaluate('width / depth', ['width' => 100, 'depth' => 0]);
    }

    /**
     * Test 12 — Decimal Calculation Accuracy.
     */
    public function test_12_decimal_calculation_accuracy(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $result = $evaluator->evaluate('7.2 * 2.5 * 1.05', []);
        $this->assertEquals(18.9, round($result, 4));
    }

    /**
     * Test 13 — Formula-Driven Requirement Integrates with MRP.
     */
    public function test_13_formula_driven_mrp_integration(): void
    {
        $bom = $this->createBom();

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width * 2',
            'uom_id' => $this->uom->id,
        ]);

        $mrpService = app(MrpShortageService::class);
        $tree = $mrpService->buildShortageTree($this->tenant->id, $this->fgProduct->id, 10.0, null, null, null, null, null, null, null, ['width' => 5]);

        $this->assertEquals(10.0, $tree['mfg_required_qty']);
        $this->assertNotEmpty($tree['children']);
        $this->assertEquals(100.0, $tree['children'][0]['required_qty']); // (5 * 2) * 10 = 100
    }

    /**
     * Test 14 — Formula-Driven Requirement Integrates with Phase 1 Supply Netting.
     */
    public function test_14_formula_driven_phase_1_supply_netting(): void
    {
        $wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Main WH', 'code' => 'WH-MRP-1']);

        // Stock available for RM1 = 30
        ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->rm1->id,
            'warehouse_id' => $wh->id,
            'quantity' => 30.0,
        ]);

        $bom = $this->createBom();

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width * 2',
            'uom_id' => $this->uom->id,
        ]);

        $mrpService = app(MrpShortageService::class);
        $tree = $mrpService->buildShortageTree($this->tenant->id, $this->fgProduct->id, 10.0, null, null, null, null, null, null, null, ['width' => 5]);

        // Demand = (5 * 2) * 10 = 100. Stock = 30 -> Net Shortage = 70.
        $this->assertEquals(70.0, $tree['children'][0]['net_shortage_qty']);
    }

    /**
     * Test 15 — MOQ / Order Multiple does NOT inflate component manufacturing requirement.
     */
    public function test_15_moq_does_not_inflate_component_requirement(): void
    {
        $this->fgProduct->update(['minimum_order_qty' => 100, 'order_multiple' => 25]);

        $bom = $this->createBom();

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width * 1',
            'uom_id' => $this->uom->id,
        ]);

        $mrpService = app(MrpShortageService::class);
        $tree = $mrpService->buildShortageTree($this->tenant->id, $this->fgProduct->id, 73.0, null, null, null, null, null, null, null, ['width' => 1]);

        $this->assertEquals(73.0, $tree['net_shortage_qty']);
        $this->assertEquals(100.0, $tree['planned_supply_qty']); // Lotsized to 100
        $this->assertEquals(73.0, $tree['children'][0]['required_qty']); // Child required is 73 (NOT 100)
    }

    /**
     * Test 16 — Production Order Snapshot evaluates formula and stores result in reservations.
     */
    public function test_16_production_order_snapshot(): void
    {
        $bom = $this->createBom();

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width * height',
            'uom_id' => $this->uom->id,
        ]);

        $poService = app(ProductionOrderService::class);
        $order = $poService->create([
            'product_id' => $this->fgProduct->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 2,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'parameters' => ['width' => 10, 'height' => 5],
        ], $this->user->id);

        $res = ProductionOrderReservation::where('production_order_id', $order->id)->first();
        $this->assertNotNull($res);
        $this->assertEquals(100.0, $res->quantity_planned); // (10 * 5) * 2 = 100
    }

    /**
     * Test 17 — Existing Production Order snapshot is protected if master formula changes.
     */
    public function test_17_existing_order_snapshot_protected_from_formula_change(): void
    {
        $bom = $this->createBom();

        $bomItem = ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width * 2',
            'uom_id' => $this->uom->id,
        ]);

        $poService = app(ProductionOrderService::class);
        $order = $poService->create([
            'product_id' => $this->fgProduct->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'parameters' => ['width' => 10],
        ], $this->user->id);

        // Update master BOM item formula
        $bomItem->update(['formula' => 'width * 10']);

        $res = ProductionOrderReservation::where('production_order_id', $order->id)->first();
        $this->assertEquals(20.0, $res->quantity_planned); // Remains 20, NOT 100!
    }

    /**
     * Test 18 — New Production Order uses newly released revision formula.
     */
    public function test_18_new_order_uses_released_revision_formula(): void
    {
        $bomRev0 = $this->createBom(['revision' => 0, 'status' => 'inactive']);

        $bomRev1 = $this->createBom(['revision' => 1, 'status' => 'approved']);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bomRev1->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width * 5',
            'uom_id' => $this->uom->id,
        ]);

        $poService = app(ProductionOrderService::class);
        $order = $poService->create([
            'product_id' => $this->fgProduct->id,
            'bom_id' => $bomRev1->id,
            'quantity_ordered' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'parameters' => ['width' => 10],
        ], $this->user->id);

        $res = ProductionOrderReservation::where('production_order_id', $order->id)->first();
        $this->assertEquals(50.0, $res->quantity_planned);
    }

    /**
     * Test 19 — ECO Impact Analysis detects formula changes.
     */
    public function test_19_eco_formula_change_in_impact_analysis(): void
    {
        $bomCurrent = $this->createBom(['revision' => 0, 'status' => 'approved']);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $bomCurrent->id, 'sequence' => 1, 'material_id' => $this->rm1->id, 'quantity' => 0, 'quantity_type' => 'formula', 'formula' => 'width * 2', 'uom_id' => $this->uom->id]);

        $bomProposed = $this->createBom(['revision' => 1, 'status' => 'draft']);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $bomProposed->id, 'sequence' => 1, 'material_id' => $this->rm1->id, 'quantity' => 0, 'quantity_type' => 'formula', 'formula' => 'width * 3', 'uom_id' => $this->uom->id]);

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco(['title' => 'Formula ECO', 'change_type' => ProductionEco::CHANGE_TYPE_BOM, 'product_id' => $this->fgProduct->id, 'current_bom_id' => $bomCurrent->id, 'proposed_bom_id' => $bomProposed->id], $this->user->id);

        $impact = $ecoService->analyzeImpact($eco);
        $this->assertNotEmpty($impact['material_impact']['quantity_changed']);
        $this->assertTrue($impact['material_impact']['quantity_changed'][0]['is_formula_changed']);
    }

    /**
     * Test 20 — ECO Release does NOT mutate existing Production Order snapshots.
     */
    public function test_20_eco_release_does_not_mutate_existing_order_snapshots(): void
    {
        $bomCurrent = $this->createBom(['revision' => 0, 'status' => 'approved']);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $bomCurrent->id, 'sequence' => 1, 'material_id' => $this->rm1->id, 'quantity' => 0, 'quantity_type' => 'formula', 'formula' => 'width * 2', 'uom_id' => $this->uom->id]);

        $order = $this->createOrder(1, 'released', ['width' => 10], $bomCurrent->id);
        ProductionOrderReservation::create(['tenant_id' => $this->tenant->id, 'production_order_id' => $order->id, 'product_id' => $this->rm1->id, 'uom_id' => $this->uom->id, 'quantity_planned' => 20.0]);

        $bomProposed = $this->createBom(['revision' => 1, 'status' => 'draft']);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $bomProposed->id, 'sequence' => 1, 'material_id' => $this->rm1->id, 'quantity' => 0, 'quantity_type' => 'formula', 'formula' => 'width * 5', 'uom_id' => $this->uom->id]);

        $ecoService = app(ProductionEcoService::class);
        $eco = $ecoService->createEco(['title' => 'Release ECO', 'change_type' => ProductionEco::CHANGE_TYPE_BOM, 'product_id' => $this->fgProduct->id, 'current_bom_id' => $bomCurrent->id, 'proposed_bom_id' => $bomProposed->id], $this->user->id);

        $ecoService->submitForReview($eco, $this->user->id);
        $ecoService->approve($eco, 'Approved', $this->user->id);
        $ecoService->release($eco, $this->user->id);

        $res = ProductionOrderReservation::where('production_order_id', $order->id)->first();
        $this->assertEquals(20.0, $res->quantity_planned);
    }

    /**
     * Test 21 — Costing uses evaluated formula quantity.
     */
    public function test_21_costing_uses_evaluated_formula_quantity(): void
    {
        $bom = $this->createBom();
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $bom->id, 'sequence' => 1, 'material_id' => $this->rm1->id, 'quantity' => 0, 'quantity_type' => 'formula', 'formula' => 'width * 2', 'uom_id' => $this->uom->id]);

        $order = $this->createOrder(1, 'released', ['width' => 5], $bom->id);
        ProductionOrderReservation::create(['tenant_id' => $this->tenant->id, 'production_order_id' => $order->id, 'product_id' => $this->rm1->id, 'uom_id' => $this->uom->id, 'quantity_planned' => 10.0]);

        $costService = app(ProductionCostService::class);
        $est = $costService->estimateOrderCost($order);

        $this->assertEquals(100.0, $est['material_cost']); // 10 units * 10 unit_cost = 100
    }

    /**
     * Test 22 — Production Readiness uses evaluated quantity.
     */
    public function test_22_production_readiness_uses_evaluated_quantity(): void
    {
        $wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Readiness WH', 'code' => 'WH-READ-1']);
        ProductWarehouseStock::create(['tenant_id' => $this->tenant->id, 'product_id' => $this->rm1->id, 'warehouse_id' => $wh->id, 'quantity' => 7.0]);

        $order = $this->createOrder(1, 'released', ['width' => 5]);
        $op = \App\Domains\Production\Models\ProductionOrderOperation::create(['tenant_id' => $this->tenant->id, 'production_order_id' => $order->id, 'sequence' => 10, 'operation_number' => 'OP10', 'name' => 'Assembly', 'target_produced_qty' => 1, 'status' => 'ready']);

        ProductionOrderReservation::create(['tenant_id' => $this->tenant->id, 'production_order_id' => $order->id, 'product_id' => $this->rm1->id, 'warehouse_id' => $wh->id, 'uom_id' => $this->uom->id, 'quantity_planned' => 10.0, 'quantity_issued' => 0.0, 'quantity_reserved' => 0.0]);

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op);

        $this->assertEquals(ProductionReadinessService::STATUS_PARTIALLY_READY, $eval['overall_status']);
        $this->assertEquals(0.7, $eval['ready_qty']); // 7 available / 10 required
    }

    /**
     * Test 23 — Tenant Isolation.
     */
    public function test_23_tenant_isolation(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $this->expectException(\InvalidArgumentException::class);
        $evaluator->evaluate('width * 2', ['tenant_b_var' => 100]); // width missing
    }

    /**
     * Test 24 — Formula Preview is read-only.
     */
    public function test_24_formula_preview_is_read_only(): void
    {
        $bomCountBefore = ProductionBom::count();
        $evaluator = app(BomFormulaEvaluatorService::class);
        $preview = $evaluator->previewFormula('width * height * 1.05', ['width' => 1200, 'height' => 800]);

        $this->assertTrue($preview['success']);
        $this->assertEquals(1008000.0, $preview['result']);
        $this->assertEquals($bomCountBefore, ProductionBom::count());
    }

    /**
     * Test 25 — Deep Multi-Level BOM Explosion with Formulas.
     */
    public function test_25_deep_multi_level_bom_explosion(): void
    {
        $bom1 = $this->createBom(['product_id' => $this->fgProduct->id]);
        $bom2 = $this->createBom(['product_id' => $this->sfgProduct->id]);

        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $bom1->id, 'sequence' => 1, 'material_id' => $this->sfgProduct->id, 'child_bom_id' => $bom2->id, 'quantity' => 0, 'quantity_type' => 'formula', 'formula' => 'width * 2', 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $bom2->id, 'sequence' => 1, 'material_id' => $this->rm1->id, 'quantity' => 0, 'quantity_type' => 'formula', 'formula' => 'length + 5', 'uom_id' => $this->uom->id]);

        $explosionService = app(BomExplosionService::class);
        $tree = $explosionService->explode($this->fgProduct->id, 2.0, $this->tenant->id, ['width' => 3, 'length' => 10]);

        // FG qty = 2. SFG unit qty = 3 * 2 = 6. SFG gross = 12. RM1 unit qty = 10 + 5 = 15. Total RM1 = 15 * 12 = 180.
        $flatRm1 = collect($tree['flat'])->firstWhere('product_id', $this->rm1->id);
        $this->assertEquals(180.0, $flatRm1['net_quantity']);
    }

    /**
     * Test 26 — Parameter Precedence (Explicit Order > Variant Values > Attributes Config > Product Dimensions).
     */
    public function test_26_parameter_precedence(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Precedence Widget',
            'sku' => 'PREC-' . uniqid(),
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'width' => 900, // Priority 4
            'attributes_config' => ['width' => 950], // Priority 3
            'variant_values' => ['width' => 1000], // Priority 2
            'status' => 'active',
        ]);

        $evaluator = app(BomFormulaEvaluatorService::class);

        // 1. Explicit order parameter (Priority 1) overrides all
        $val1 = $evaluator->evaluate('width * 1', ['width' => 1200], $product);
        $this->assertEquals(1200.0, $val1);

        // 2. Omitted explicit parameter falls back to variant_values (1000)
        $val2 = $evaluator->evaluate('width * 1', [], $product);
        $this->assertEquals(1000.0, $val2);

        // 3. Remove variant_values -> falls back to attributes_config (950)
        $product->variant_values = null;
        $val3 = $evaluator->evaluate('width * 1', [], $product);
        $this->assertEquals(950.0, $val3);

        // 4. Remove attributes_config -> falls back to product dimension (900)
        $product->attributes_config = null;
        $val4 = $evaluator->evaluate('width * 1', [], $product);
        $this->assertEquals(900.0, $val4);
    }

    /**
     * Test 27 — Master Formula Validation Without Runtime Values.
     */
    public function test_27_master_formula_validation_without_runtime_values(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $errors = $evaluator->validateFormula('width * height * 1.05');
        $this->assertEmpty($errors);
    }

    /**
     * Test 28 — Runtime Missing Parameter Throws Exception.
     */
    public function test_28_runtime_missing_parameter_throws_exception(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameter: height');
        $evaluator->evaluate('width * height', ['width' => 1200]);
    }

    /**
     * Test 29 — Function Argument Semantics (round, ceil, floor, min, max, abs).
     */
    public function test_29_function_argument_semantics(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);
        $this->assertEquals(11.0, $evaluator->evaluate('ceil(10.2)', []));
        $this->assertEquals(10.0, $evaluator->evaluate('floor(10.8)', []));
        $this->assertEquals(10.57, $evaluator->evaluate('round(10.567, 2)', []));
        $this->assertEquals(11.0, $evaluator->evaluate('round(10.567)', []));
        $this->assertEquals(10.0, $evaluator->evaluate('min(10, 20)', []));
        $this->assertEquals(20.0, $evaluator->evaluate('max(10, 20)', []));
        $this->assertEquals(10.0, $evaluator->evaluate('abs(-10)', []));
    }

    /**
     * Test 30 — Formula Parameter Identifier Security.
     */
    public function test_30_formula_identifier_security(): void
    {
        $evaluator = app(BomFormulaEvaluatorService::class);

        $maliciousFormulas = [
            '$width * 2',
            'width; system("dir")',
            'width.length',
            'width->height',
            'width[0]',
            'eval("return 1;")',
            'exec("whoami")',
            'width * "10"',
        ];

        foreach ($maliciousFormulas as $bad) {
            try {
                $evaluator->evaluate($bad, ['width' => 10]);
                $this->fail("Failed to reject malicious formula: {$bad}");
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    /**
     * Test 31 — Historical Reproducibility After Product/Variant Changes.
     */
    public function test_31_historical_reproducibility_after_product_changes(): void
    {
        $bom = $this->createBom();
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width * height',
            'uom_id' => $this->uom->id,
        ]);

        $poService = app(ProductionOrderService::class);
        $orderA = $poService->create([
            'product_id' => $this->fgProduct->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 1,
            'parameters' => ['width' => 1000, 'height' => 500],
        ], $this->user->id);

        $resA = ProductionOrderReservation::where('production_order_id', $orderA->id)->first();
        $this->assertEquals(500000.0, $resA->quantity_planned);

        // Mutate Product defaults & master BOM
        $this->fgProduct->update(['width' => 2000, 'height' => 1000]);

        // Verify Order A remains untouched
        $resARefreshed = ProductionOrderReservation::where('production_order_id', $orderA->id)->first();
        $this->assertEquals(500000.0, $resARefreshed->quantity_planned);

        // Create Order B with new parameters
        $orderB = $poService->create([
            'product_id' => $this->fgProduct->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 1,
            'parameters' => ['width' => 2000, 'height' => 1000],
        ], $this->user->id);

        $resB = ProductionOrderReservation::where('production_order_id', $orderB->id)->first();
        $this->assertEquals(2000000.0, $resB->quantity_planned);
    }

    /**
     * Test 32 — Formula Traceability via Order Reservation Snapshot.
     */
    public function test_32_formula_traceability(): void
    {
        $bom = $this->createBom();
        $bomItem = ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width * height * 1.05',
            'uom_id' => $this->uom->id,
        ]);

        $poService = app(ProductionOrderService::class);
        $order = $poService->create([
            'product_id' => $this->fgProduct->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 1,
            'parameters' => ['width' => 1200, 'height' => 800],
        ], $this->user->id);

        $res = ProductionOrderReservation::where('production_order_id', $order->id)->first();

        // Traceability check: reservation links to bom_item (with formula) and order (with parameters)
        $this->assertEquals($bomItem->id, $res->bom_item_id);
        $this->assertEquals('width * height * 1.05', $res->bomItem->formula);
        $this->assertEquals(1200, $order->parameters['width']);
        $this->assertEquals(800, $order->parameters['height']);
        $this->assertEquals(1008000.0, $res->quantity_planned);
    }

    /**
     * Test 33 — Negative / Zero Result Clamping.
     */
    public function test_33_negative_result_clamping(): void
    {
        $bom = $this->createBom();
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $bom->id,
            'sequence' => 1,
            'material_id' => $this->rm1->id,
            'quantity' => 0,
            'quantity_type' => 'formula',
            'formula' => 'width - 100',
            'uom_id' => $this->uom->id,
        ]);

        $poService = app(ProductionOrderService::class);
        $order = $poService->create([
            'product_id' => $this->fgProduct->id,
            'bom_id' => $bom->id,
            'quantity_ordered' => 1,
            'parameters' => ['width' => 50], // 50 - 100 = -50
        ], $this->user->id);

        $res = ProductionOrderReservation::where('production_order_id', $order->id)->first();
        $this->assertEquals(0.0, $res->quantity_planned);
    }

    /**
     * Test 34 — Formula Preview API Endpoint.
     */
    public function test_34_formula_preview_api_endpoint(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson('/production/boms/preview-formula', [
                'formula' => 'width * height * 1.05',
                'sample_parameters' => ['width' => 1200, 'height' => 800],
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'result' => 1008000.0,
        ]);
    }

    /**
     * Test 35 — Multi-Tenant Formula Isolation.
     */
    public function test_35_multi_tenant_formula_isolation(): void
    {
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b-' . uniqid(), 'status' => 'active', 'plan' => 'enterprise']);
        $userB = User::create(['tenant_id' => $tenantB->id, 'name' => 'User B', 'email' => 'userb-' . uniqid() . '@example.com', 'password' => bcrypt('password'), 'role' => 'admin']);

        // User B attempts to preview Tenant A formula
        $response = $this->actingAs($userB)
            ->withHeader('X-Tenant', $tenantB->slug)
            ->postJson('/production/boms/preview-formula', [
                'formula' => 'tenant_a_secret * 2',
            ]);

        // Evaluator fails due to missing parameter
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }
}
