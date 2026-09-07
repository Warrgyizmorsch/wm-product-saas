<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionVarianceAnalysisService;
use App\Domains\Production\Services\RoutingRecommendationService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionRoutingVarianceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Uom $uom;
    private User $user;
    private Product $fgProduct;
    private WorkCenter $workCenter;
    private Routing $routing;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 00:00:00');

        $this->tenant = Tenant::create([
            'name' => 'Variance Tenant',
            'slug' => 'variance-tenant-' . uniqid(),
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
            'name' => 'Variance Planner',
            'email' => 'variance-planner-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->fgProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Precision Assembly',
            'sku' => 'FG-PREC-' . uniqid(),
            'type' => 'finished_good',
            'planning_type' => 'manufacture',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly WC 1',
            'code' => 'WC-ASSY-1',
            'capacity_per_day' => 8.0,
            'cost_per_hour' => 50.0,
            'status' => 'active',
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'ROUT-' . uniqid(),
            'name' => 'Standard Assembly Routing',
            'product_id' => $this->fgProduct->id,
            'version' => '1.0.0',
            'status' => 'approved',
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Assembly Operation',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 30.0,
            'processing_time_minutes' => 60.0,
            'labor_cost_rate' => 20.0,
            'machine_cost_rate' => 30.0,
        ]);
    }

    private function createTestOrder(int $qty = 100, string $status = ProductionOrder::STATUS_COMPLETED): ProductionOrder
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-VAR-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => $qty,
            'quantity_produced' => $qty,
            'status' => $status,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
        ]);

        $routingOp = $this->routing->operations->first();

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $routingOp?->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Assembly Operation',
            'work_center_id' => $this->workCenter->id,
            'target_produced_qty' => $qty,
            'quantity_produced' => $qty,
            'setup_time_planned' => 30.0,
            'processing_time_planned' => 60.0,
            'total_time_planned' => 90.0,
            'setup_time_actual' => 30.0,
            'processing_time_actual' => 60.0,
            'status' => 'completed',
        ]);

        return $order;
    }

    /**
     * Test 1 — Planned vs Actual Quantity Variance.
     */
    public function test_1_planned_vs_actual_quantity_variance(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $order->update(['quantity_produced' => 95, 'quantity_scrapped' => 3, 'quantity_rejected' => 2]);

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertEquals(100.0, $analysis['planned_quantity']);
        $this->assertEquals(95.0, $analysis['actual_completed_quantity']);
        $this->assertEquals(-5.0, $analysis['quantity_variance']);
        $this->assertEquals(95.0, $analysis['yield_percentage']);
        $this->assertEquals(3.0, $analysis['scrap_quantity']);
        $this->assertEquals(2.0, $analysis['rejected_quantity']);
        $this->assertEquals(ProductionVarianceAnalysisService::QTY_UNDER_PRODUCTION, $analysis['quantity_classification']);
    }

    /**
     * Test 2 — Planned vs Actual Operation Setup & Processing Time.
     */
    public function test_2_planned_vs_actual_operation_time(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update(['setup_time_actual' => 40.0, 'processing_time_actual' => 80.0]); // Total 120 vs planned 90

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertEquals(90.0, $analysis['planned_total_time']);
        $this->assertEquals(120.0, $analysis['actual_total_time']);
        $this->assertEquals(30.0, $analysis['total_time_variance']);
        $this->assertEquals(33.33, $analysis['time_variance_percentage']);
        $this->assertEquals(ProductionVarianceAnalysisService::TIME_SIGNIFICANT_VARIANCE, $analysis['time_classification']);
    }

    /**
     * Test 3 — Time Variance Classification: ON_TARGET (<= 5%).
     */
    public function test_3_time_variance_classification_on_target(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update(['setup_time_actual' => 30.0, 'processing_time_actual' => 63.0]); // 93 min vs 90 min = +3.33%

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertEquals(ProductionVarianceAnalysisService::TIME_ON_TARGET, $analysis['time_classification']);
    }

    /**
     * Test 4 — Time Variance Classification: MINOR_VARIANCE (5% - 15%).
     */
    public function test_4_time_variance_classification_minor(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update(['setup_time_actual' => 30.0, 'processing_time_actual' => 70.0]); // 100 min vs 90 min = +11.11%

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertEquals(ProductionVarianceAnalysisService::TIME_MINOR_VARIANCE, $analysis['time_classification']);
    }

    /**
     * Test 5 — Time Variance Classification: SIGNIFICANT_VARIANCE (> 15%).
     */
    public function test_5_time_variance_classification_significant(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update(['setup_time_actual' => 35.0, 'processing_time_actual' => 85.0]); // 120 min vs 90 min = +33.33%

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertEquals(ProductionVarianceAnalysisService::TIME_SIGNIFICANT_VARIANCE, $analysis['time_classification']);
    }

    /**
     * Test 6 — Quantity Variance Classification (OVER_PRODUCTION, UNDER_PRODUCTION, ON_TARGET).
     */
    public function test_6_quantity_variance_classification(): void
    {
        $service = app(ProductionVarianceAnalysisService::class);

        // Over Production
        $order1 = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $order1->update(['quantity_produced' => 110]);
        $an1 = $service->analyzeProductionOrder($order1);
        $this->assertEquals(ProductionVarianceAnalysisService::QTY_OVER_PRODUCTION, $an1['quantity_classification']);

        // On Target
        $order2 = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $order2->update(['quantity_produced' => 100]);
        $an2 = $service->analyzeProductionOrder($order2);
        $this->assertEquals(ProductionVarianceAnalysisService::QTY_ON_TARGET, $an2['quantity_classification']);
    }

    /**
     * Test 7 — In-Progress Order is NOT Classified as UNDER_PRODUCTION.
     */
    public function test_7_in_progress_order_not_classified_as_under_production(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_IN_PROGRESS);
        $order->update(['quantity_produced' => 40]); // Partial progress

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertFalse($analysis['is_completed']);
        $this->assertEquals(ProductionVarianceAnalysisService::STATUS_IN_PROGRESS, $analysis['quantity_classification']);
    }

    /**
     * Test 8 — Scrap Quantity Impact in Execution Classification.
     */
    public function test_8_scrap_impact(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update(['quantity_scrapped' => 5.0]);

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertEquals(5.0, $analysis['scrap_quantity']);
        $this->assertEquals(ProductionVarianceAnalysisService::EXEC_SCRAP, $analysis['execution_classification']);
    }

    /**
     * Test 9 — Rework Quantity Impact in Execution Classification.
     */
    public function test_9_rework_impact(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update(['quantity_claimed' => 105.0, 'quantity_produced' => 100.0]); // 5 rework claimed

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertEquals(5.0, $analysis['rework_quantity']);
        $this->assertEquals(ProductionVarianceAnalysisService::EXEC_REWORK, $analysis['execution_classification']);
    }

    /**
     * Test 10 — Rejection Quantity Impact in Execution Classification.
     */
    public function test_10_rejection_impact(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update(['quantity_rejected' => 2.0]);

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertEquals(2.0, $analysis['rejected_quantity']);
        $this->assertEquals(ProductionVarianceAnalysisService::EXEC_REJECTION, $analysis['execution_classification']);
    }

    /**
     * Test 11 — Multiple Operation Aggregation.
     */
    public function test_11_multiple_operation_aggregation(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Packaging Operation',
            'work_center_id' => $this->workCenter->id,
            'target_produced_qty' => 100,
            'quantity_produced' => 100,
            'setup_time_planned' => 15.0,
            'processing_time_planned' => 30.0,
            'total_time_planned' => 45.0,
            'setup_time_actual' => 15.0,
            'processing_time_actual' => 45.0, // +15 min
            'status' => 'completed',
        ]);

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertCount(2, $analysis['operations']);
        $this->assertEquals(135.0, $analysis['planned_total_time']); // 90 + 45
        $this->assertEquals(150.0, $analysis['actual_total_time']);  // 90 + 60
    }

    /**
     * Test 12 — Single Order Variance does NOT create a Recurring Recommendation.
     */
    public function test_12_single_order_variance_no_recommendation(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update(['processing_time_actual' => 120.0]); // Large single variance

        $recService = app(RoutingRecommendationService::class);
        $recommendations = $recService->generateRecommendations($this->tenant->id);

        $this->assertEmpty($recommendations); // Requires sample size >= 3
    }

    /**
     * Test 13 — Recurring Variance Across Orders Creates Evidence-Based Recommendation.
     */
    public function test_13_recurring_variance_creates_recommendation(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
            $op = $order->operations->first();
            $op->update(['setup_time_actual' => 30.0, 'processing_time_actual' => 90.0]); // 120 vs 90 = +33.33%
        }

        $recService = app(RoutingRecommendationService::class);
        $recommendations = $recService->generateRecommendations($this->tenant->id);

        $this->assertNotEmpty($recommendations);
        $this->assertEquals(RoutingRecommendationService::TYPE_REVIEW_RUN_TIME, $recommendations[0]['type']);
        $this->assertEquals(3, $recommendations[0]['sample_size']);
        $this->assertEquals(33.33, $recommendations[0]['average_variance_percentage']);
    }

    /**
     * Test 14 — Recommendation Evidence String Correctness.
     */
    public function test_14_recommendation_evidence_string(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
            $op = $order->operations->first();
            $op->update(['setup_time_actual' => 30.0, 'processing_time_actual' => 90.0]);
        }

        $recService = app(RoutingRecommendationService::class);
        $recommendations = $recService->generateRecommendations($this->tenant->id);

        $this->assertNotEmpty($recommendations);
        $this->assertStringContainsString('Exceeded standard runtime in 4 of 4 orders', $recommendations[0]['evidence']);
        $this->assertStringContainsString('avg variance: +33.33%', $recommendations[0]['evidence']);
    }

    /**
     * Test 15 — Historical Routing Snapshot Protection.
     */
    public function test_15_historical_routing_snapshot_protection(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update(['setup_time_actual' => 30.0, 'processing_time_actual' => 60.0]); // 90 vs 90 = ON_TARGET

        // Update Master Routing standard to 180 minutes
        $routingOp = RoutingOperation::where('routing_id', $this->routing->id)->first();
        $routingOp->update(['processing_time_minutes' => 180.0]);

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        // Order analysis must still use Order Operation snapshot (90 minutes)
        $this->assertEquals(90.0, $analysis['planned_total_time']);
        $this->assertEquals(0.0, $analysis['total_time_variance']);
        $this->assertEquals(ProductionVarianceAnalysisService::TIME_ON_TARGET, $analysis['time_classification']);
    }

    /**
     * Test 16 — Master Routing Change Isolation.
     */
    public function test_16_master_routing_change_isolation(): void
    {
        $orderA = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);

        // Create new routing revision
        $routingRev2 = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'ROUT-REV2-' . uniqid(),
            'name' => 'Updated Assembly Routing Rev 2',
            'product_id' => $this->fgProduct->id,
            'version' => '2.0.0',
            'status' => 'approved',
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routingRev2->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Assembly Operation Rev 2',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 45.0,
            'processing_time_minutes' => 90.0,
            'labor_cost_rate' => 20.0,
            'machine_cost_rate' => 30.0,
        ]);

        $service = app(ProductionVarianceAnalysisService::class);
        $anA = $service->analyzeProductionOrder($orderA);

        $this->assertEquals(90.0, $anA['planned_total_time']);
    }

    /**
     * Test 17 — Draft ECO Creation from Recommendation.
     */
    public function test_17_draft_eco_creation_from_recommendation(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
            $op = $order->operations->first();
            $op->update(['setup_time_actual' => 30.0, 'processing_time_actual' => 90.0]);
        }

        $recService = app(RoutingRecommendationService::class);
        $recommendations = $recService->generateRecommendations($this->tenant->id);

        $eco = $recService->createDraftEcoFromRecommendation($recommendations[0], $this->user->id);

        $this->assertInstanceOf(ProductionEco::class, $eco);
        $this->assertEquals(ProductionEco::STATUS_DRAFT, $eco->status);
        $this->assertEquals($this->fgProduct->id, $eco->product_id);
        $this->assertStringContainsString('Review Standard Run Time', $eco->title);
    }

    /**
     * Test 18 — Draft ECO Creation Does NOT Modify Master Routing Data.
     */
    public function test_18_draft_eco_does_not_modify_master_data(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
            $op = $order->operations->first();
            $op->update(['setup_time_actual' => 30.0, 'processing_time_actual' => 90.0]);
        }

        $originalOpMinutes = RoutingOperation::where('routing_id', $this->routing->id)->first()->processing_time_minutes;

        $recService = app(RoutingRecommendationService::class);
        $recommendations = $recService->generateRecommendations($this->tenant->id);

        $eco = $recService->createDraftEcoFromRecommendation($recommendations[0], $this->user->id);

        $refreshedOpMinutes = RoutingOperation::where('routing_id', $this->routing->id)->first()->processing_time_minutes;
        $this->assertEquals($originalOpMinutes, $refreshedOpMinutes);
    }

    /**
     * Test 19 — Tenant Isolation.
     */
    public function test_19_tenant_isolation(): void
    {
        $tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b-' . uniqid(), 'status' => 'active', 'plan' => 'enterprise']);
        $service = app(ProductionVarianceAnalysisService::class);

        $recsB = app(RoutingRecommendationService::class)->generateRecommendations($tenantB->id);
        $this->assertEmpty($recsB);
    }

    /**
     * Test 20 — Read-Only Guarantee.
     */
    public function test_20_read_only_guarantee(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);

        $orderCountBefore = ProductionOrder::count();
        $opCountBefore = ProductionOrderOperation::count();
        $ecoCountBefore = ProductionEco::count();

        $service = app(ProductionVarianceAnalysisService::class);
        $service->analyzeProductionOrder($order);
        $service->analyzeRecurringRoutingVariances($this->tenant->id);

        $this->assertEquals($orderCountBefore, ProductionOrder::count());
        $this->assertEquals($opCountBefore, ProductionOrderOperation::count());
        $this->assertEquals($ecoCountBefore, ProductionEco::count());
    }

    /**
     * Test 21 — Multi-Level BOM Compatibility.
     */
    public function test_21_multi_level_bom_compatibility(): void
    {
        $sfg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sub Assembly',
            'sku' => 'SFG-' . uniqid(),
            'type' => 'semi_finished',
            'planning_type' => 'manufacture',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);

        // Intermediate operation for SFG
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 5,
            'operation_number' => 'OP05',
            'name' => 'SFG Sub-Assembly',
            'work_center_id' => $this->workCenter->id,
            'source_product_id' => $sfg->id,
            'bom_level' => 2,
            'target_produced_qty' => 100,
            'quantity_produced' => 100,
            'setup_time_planned' => 10.0,
            'processing_time_planned' => 20.0,
            'total_time_planned' => 30.0,
            'setup_time_actual' => 10.0,
            'processing_time_actual' => 20.0,
            'status' => 'completed',
        ]);

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertCount(2, $analysis['operations']);
        $op05 = collect($analysis['operations'])->firstWhere('operation_number', 'OP05');
        $this->assertNotNull($op05);
        $this->assertEquals(ProductionVarianceAnalysisService::TIME_ON_TARGET, $op05['time_classification']);
    }

    /**
     * Test 22 — WIP / Batch Compatibility.
     */
    public function test_22_wip_batch_compatibility(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();

        // Simulate batch timestamps
        $op->update([
            'actual_start_time' => '2026-09-01 08:00:00',
            'actual_end_time'   => '2026-09-01 09:30:00', // 90 min
            'setup_time_actual' => 0.0,
            'processing_time_actual' => 0.0,
        ]);

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertEquals(90.0, $analysis['actual_total_time']);
        $this->assertEquals(0.0, $analysis['total_time_variance']);
    }

    /**
     * Test 23 — Subcontract Operation Compatibility.
     */
    public function test_23_subcontract_compatibility(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 15,
            'operation_number' => 'OP15_SUB',
            'name' => 'External Anodizing Subcontract',
            'is_external' => true,
            'subcontract_lead_time_days' => 3,
            'target_produced_qty' => 100,
            'quantity_produced' => 100,
            'setup_time_planned' => 0.0,
            'processing_time_planned' => 0.0,
            'total_time_planned' => 0.0,
            'setup_time_actual' => 0.0,
            'processing_time_actual' => 0.0,
            'status' => 'completed',
        ]);

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $subOp = collect($analysis['operations'])->firstWhere('operation_number', 'OP15_SUB');
        $this->assertTrue($subOp['is_external']);
        $this->assertEquals(0.0, $subOp['total_time_variance']);
        $this->assertEquals(ProductionVarianceAnalysisService::TIME_ON_TARGET, $subOp['time_classification']);
    }

    /**
     * Test 24 — No Double Counting of Transferred Quantities.
     */
    public function test_24_no_double_counting_transferred_quantities(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update(['quantity_transferred_out' => 50.0, 'quantity_produced' => 100.0]);

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order);

        $this->assertEquals(100.0, $analysis['actual_completed_quantity']);
    }

    /**
     * Test 25 — Performance & Eager Loading Verification (No Avoidable N+1 Queries).
     */
    public function test_25_performance_and_eager_loading(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);

        // Add additional operations to verify query count is constant regardless of operation count (No N+1)
        for ($i = 2; $i <= 5; $i++) {
            ProductionOrderOperation::create([
                'tenant_id' => $this->tenant->id,
                'production_order_id' => $order->id,
                'routing_operation_id' => $this->routing->operations->first()?->id,
                'sequence' => $i * 10,
                'operation_number' => 'OP' . ($i * 10),
                'name' => "Operation {$i}",
                'work_center_id' => $this->workCenter->id,
                'target_produced_qty' => 100,
                'quantity_produced' => 100,
                'setup_time_planned' => 30.0,
                'processing_time_planned' => 60.0,
                'total_time_planned' => 90.0,
                'setup_time_actual' => 30.0,
                'processing_time_actual' => 60.0,
                'status' => 'completed',
            ]);
        }

        \DB::enableQueryLog();
        $service = app(ProductionVarianceAnalysisService::class);
        $service->analyzeProductionOrder($order->id);
        $queryCount = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        // Architectural contract: No avoidable N+1 queries (constant <= 6 queries even with 5+ operations)
        $this->assertLessThanOrEqual(6, $queryCount);
    }

    /**
     * Test 26 — Authorization and Route Access.
     */
    public function test_26_authorization_and_route_access(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson("/production/orders/{$order->id}/variance");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test 27 — Machine Assignment Recommendation Logic.
     */
    public function test_27_machine_assignment_recommendation(): void
    {
        $m1 = \App\Domains\Production\Models\Machine::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->workCenter->id, 'name' => 'Primary Machine', 'code' => 'M1-' . uniqid(), 'status' => 'active']);
        $m2 = \App\Domains\Production\Models\Machine::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->workCenter->id, 'name' => 'Alternate Machine', 'code' => 'M2-' . uniqid(), 'status' => 'active']);

        for ($i = 0; $i < 3; $i++) {
            $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
            $op = $order->operations->first();
            $op->update([
                'machine_id' => $m1->id,
                'machine_used_id' => $m2->id, // Alternate machine
                'setup_time_actual' => 30.0,
                'processing_time_actual' => 80.0, // Variance +22%
            ]);
        }

        $recService = app(RoutingRecommendationService::class);
        $recommendations = $recService->generateRecommendations($this->tenant->id);

        $altRec = collect($recommendations)->firstWhere('type', RoutingRecommendationService::TYPE_REVIEW_MACHINE_ASSIGNMENT);
        $this->assertNotNull($altRec);
        $this->assertStringContainsString('alternate machine', $altRec['evidence']);
    }

    /**
     * Test 28 — Scrap Factor Recommendation Logic.
     */
    public function test_28_scrap_factor_recommendation(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
            $op = $order->operations->first();
            $op->update(['quantity_scrapped' => 4.0]);
        }

        $recService = app(RoutingRecommendationService::class);
        $recommendations = $recService->generateRecommendations($this->tenant->id);

        $scrapRec = collect($recommendations)->firstWhere('type', RoutingRecommendationService::TYPE_REVIEW_SCRAP_FACTOR);
        $this->assertNotNull($scrapRec);
        $this->assertStringContainsString('Scrap recorded in 3 of 3 recent orders', $scrapRec['evidence']);
    }

    /**
     * Test 29 — Authoritative Rework Source Consumption.
     */
    public function test_29_authoritative_rework_source_consumption(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();

        // Record explicit ProductionOrderRework model entry
        \App\Domains\Production\Models\ProductionOrderRework::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op->id,
            'quantity' => 5.0,
            'reason' => 'Dimensional adjustment required',
            'status' => 'pending',
            'recorded_by' => $this->user->id,
            'recorded_at' => now(),
        ]);

        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order->id);

        $this->assertEquals(5.0, $analysis['rework_quantity']);
        $opAnalysis = collect($analysis['operations'])->firstWhere('operation_id', $op->id);
        $this->assertEquals(5.0, $opAnalysis['rework_quantity']);
        $this->assertEquals(ProductionVarianceAnalysisService::EXEC_REWORK, $opAnalysis['execution_classification']);
    }

    /**
     * Test 30 — One-Off Alternate Machine Does Not Generate Recommendation.
     */
    public function test_30_one_off_alternate_machine_does_not_generate_recommendation(): void
    {
        $m1 = \App\Domains\Production\Models\Machine::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->workCenter->id, 'name' => 'Primary Machine', 'code' => 'M1-' . uniqid(), 'status' => 'active']);
        $m2 = \App\Domains\Production\Models\Machine::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->workCenter->id, 'name' => 'Alternate Machine', 'code' => 'M2-' . uniqid(), 'status' => 'active']);

        // Only 1 order ran on alternate machine (sample size = 1)
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update([
            'machine_id' => $m1->id,
            'machine_used_id' => $m2->id,
            'processing_time_actual' => 90.0,
        ]);

        $recService = app(RoutingRecommendationService::class);
        $recommendations = $recService->generateRecommendations($this->tenant->id);

        $altRec = collect($recommendations)->firstWhere('type', RoutingRecommendationService::TYPE_REVIEW_MACHINE_ASSIGNMENT);
        $this->assertNull($altRec, 'One-off alternate machine usage with sample size < 3 must not trigger machine recommendation');
    }

    /**
     * Test 31 — Draft ECO Creation Preserves Master Routing & Analysis is Read-Only.
     */
    public function test_31_draft_eco_creation_preserves_master_routing_and_is_read_only(): void
    {
        $order = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
        $op = $order->operations->first();
        $op->update([
            'processing_time_actual' => 120.0,
        ]);

        $routingOp = RoutingOperation::find($op->routing_operation_id);
        $initialStandardRuntime = (float) $routingOp->processing_time_minutes;

        // 1. Analyze (Read-Only)
        $service = app(ProductionVarianceAnalysisService::class);
        $analysis = $service->analyzeProductionOrder($order->id);

        // Verify routing master data is untouched by analysis
        $routingOp->refresh();
        $this->assertEquals($initialStandardRuntime, (float) $routingOp->processing_time_minutes);

        // 2. Generate Recommendation
        $recService = app(RoutingRecommendationService::class);

        // Create 3 orders to meet sample size threshold
        for ($i = 0; $i < 2; $i++) {
            $o = $this->createTestOrder(100, ProductionOrder::STATUS_COMPLETED);
            $o->operations->first()->update(['processing_time_actual' => 120.0]);
        }

        $recs = $recService->generateRecommendations($this->tenant->id);
        $runtimeRec = collect($recs)->firstWhere('type', RoutingRecommendationService::TYPE_REVIEW_RUN_TIME);
        $this->assertNotNull($runtimeRec);

        // 3. Create Draft ECO
        $eco = $recService->createDraftEcoFromRecommendation($runtimeRec, $this->user->id);

        // 4. Assert ECO is Draft and Active Routing Standard Runtime is UNCHANGED
        $this->assertInstanceOf(ProductionEco::class, $eco);
        $this->assertEquals(ProductionEco::STATUS_DRAFT, $eco->status);

        $routingOp->refresh();
        $this->assertEquals($initialStandardRuntime, (float) $routingOp->processing_time_minutes, 'Draft ECO creation must NOT mutate master routing standards.');
    }
}
