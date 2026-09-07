<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\PlanningExceptionService;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionPlanningExceptionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Uom $uom;
    private User $user;
    private Product $fgProduct;
    private Product $rawMaterial;
    private WorkCenter $workCenter;
    private Routing $routing;
    private ProductionBom $bom;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 00:00:00');

        $this->tenant = Tenant::create([
            'name' => 'Exception Tenant',
            'slug' => 'exception-tenant-' . uniqid(),
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
            'name' => 'Planning Exception User',
            'email' => 'exception-planner-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->fgProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'High Precision Pump',
            'sku' => 'FG-PUMP-' . uniqid(),
            'type' => 'finished_good',
            'planning_type' => 'manufacture',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Steel Plate',
            'sku' => 'RM-STEEL-' . uniqid(),
            'type' => 'raw_material',
            'planning_type' => 'buy',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Machining Center 1',
            'code' => 'WC-MC-1',
            'capacity_per_day' => 8.0,
            'efficiency_percentage' => 100.0,
            'status' => 'active',
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'ROUT-EXC-' . uniqid(),
            'name' => 'Pump Assembly Routing',
            'product_id' => $this->fgProduct->id,
            'version' => '1.0.0',
            'status' => 'approved',
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Machining Operation',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 30.0,
            'processing_time_minutes' => 60.0,
        ]);

        $this->bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-EXC-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'version' => '1.0.0',
            'effective_date' => '2026-01-01',
            'status' => 'approved',
        ]);
    }

    private function createOrder(int $qty = 100, string $status = ProductionOrder::STATUS_RELEASED, ?string $dueDate = '2026-09-05'): ProductionOrder
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-EXC-' . uniqid(),
            'product_id' => $this->fgProduct->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => $qty,
            'quantity_produced' => 0,
            'status' => $status,
            'start_date' => '2026-09-01',
            'end_date' => $dueDate,
        ]);

        $routingOp = $this->routing->operations->first();

        ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $routingOp?->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Machining Operation',
            'work_center_id' => $this->workCenter->id,
            'target_produced_qty' => $qty,
            'quantity_produced' => 0,
            'setup_time_planned' => 30.0,
            'processing_time_planned' => 60.0,
            'total_time_planned' => 90.0,
            'setup_time_actual' => 0.0,
            'processing_time_actual' => 0.0,
            'status' => 'ready',
        ]);

        return $order;
    }

    /**
     * Test 1 — Material Shortage Detection.
     */
    public function test_01_material_shortage_detection(): void
    {
        $order = $this->createOrder();

        \App\Domains\Production\Models\ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $this->rawMaterial->id,
            'uom_id' => $this->uom->id,
            'quantity_planned' => 100.0,
            'quantity_reserved' => 0.0,
            'quantity_issued' => 0.0,
            'status' => 'unreserved',
        ]);

        $service = app(PlanningExceptionService::class);
        $risk = $service->evaluateOrderRisk($order);

        $this->assertNotEmpty($risk);
        $this->assertContains(PlanningExceptionService::REC_REVIEW_MATERIAL_SHORTAGE, $risk['recommended_actions']);
    }

    /**
     * Test 2 — Capacity Overload Detection.
     */
    public function test_02_capacity_overload_detection(): void
    {
        $order = $this->createOrder();
        $op = $order->operations->first();
        $op->update(['total_time_planned' => 600.0]); // 10h workload vs 8h capacity = 125% utilization

        $service = app(PlanningExceptionService::class);
        $risk = $service->evaluateOrderRisk($order);

        $capEx = collect($risk['exceptions'])->firstWhere('type', PlanningExceptionService::TYPE_CAPACITY_OVERLOAD);
        $this->assertNotNull($capEx);
        $this->assertEquals(PlanningExceptionService::SEVERITY_HIGH, $capEx['severity']);
        $this->assertEquals(PlanningExceptionService::REC_RESCHEDULE_OPERATION, $capEx['recommended_action']);
    }

    /**
     * Test 3 — Machine Downtime Without Usable Alternate.
     */
    public function test_03_machine_downtime_without_usable_alternate(): void
    {
        $m1 = Machine::create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->workCenter->id, 'name' => 'Main Machine', 'code' => 'M1-' . uniqid(), 'status' => 'active']);
        $order = $this->createOrder();
        $op = $order->operations->first();
        $op->update(['machine_id' => $m1->id]);

        $downtime = ProductionMachineDowntime::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $m1->id,
            'category' => 'Breakdown',
            'reason' => 'Spindle shaft failure',
            'start_time' => now(),
            'status' => 'open',
            'created_by' => $this->user->id,
        ]);

        $service = app(PlanningExceptionService::class);
        $context = ['downtimes' => collect([$downtime])];
        $risk = $service->evaluateOrderRisk($order, $context);

        $machEx = collect($risk['exceptions'])->firstWhere('type', PlanningExceptionService::TYPE_MACHINE_UNAVAILABLE);
        $this->assertNotNull($machEx);
        $this->assertEquals(PlanningExceptionService::SEVERITY_CRITICAL, $machEx['severity']);
        $this->assertEquals(PlanningExceptionService::REC_ASSIGN_ALTERNATE_MACHINE, $machEx['recommended_action']);
    }

    /**
     * Test 4 — Operation Delay and Due-Date Risk.
     */
    public function test_04_operation_delay_and_due_date_risk(): void
    {
        // Order due date was yesterday (2026-08-31)
        $order = $this->createOrder(100, ProductionOrder::STATUS_RELEASED, '2026-08-31');

        $service = app(PlanningExceptionService::class);
        $risk = $service->evaluateOrderRisk($order);

        $dueDateEx = collect($risk['exceptions'])->firstWhere('type', PlanningExceptionService::TYPE_DUE_DATE_RISK);
        $this->assertNotNull($dueDateEx);
        $this->assertEquals(PlanningExceptionService::SEVERITY_CRITICAL, $dueDateEx['severity']);
        $this->assertEquals(PlanningExceptionService::SEVERITY_CRITICAL, $risk['overall_risk']);
    }

    /**
     * Test 5 — Pending ECO Impact Only When Specific Order Affected.
     */
    public function test_05_pending_eco_impact_only_when_affected(): void
    {
        $order = $this->createOrder();

        $eco = ProductionEco::create([
            'tenant_id' => $this->tenant->id,
            'eco_number' => 'ECO-TEST-' . uniqid(),
            'title' => 'Pump Housing Engineering Change',
            'change_type' => 'bom',
            'product_id' => $this->fgProduct->id,
            'status' => ProductionEco::STATUS_DRAFT,
        ]);

        $service = app(PlanningExceptionService::class);
        $context = ['ecos' => collect([$eco])];
        $risk = $service->evaluateOrderRisk($order, $context);

        $ecoEx = collect($risk['exceptions'])->firstWhere('type', PlanningExceptionService::TYPE_PENDING_ECO_IMPACT);
        $this->assertNotNull($ecoEx);
        $this->assertEquals(PlanningExceptionService::REC_REVIEW_PENDING_ECO, $ecoEx['recommended_action']);
    }

    /**
     * Test 6 — Subcontract Delay Risk.
     */
    public function test_06_subcontract_delay_risk(): void
    {
        $order = $this->createOrder();
        $op = $order->operations->first();
        $op->update([
            'is_external' => true,
            'subcontract_lead_time_days' => 2,
            'actual_start_time' => now()->subDays(5), // Overdue by 3 days
        ]);

        $service = app(PlanningExceptionService::class);
        $risk = $service->evaluateOrderRisk($order);

        $subEx = collect($risk['exceptions'])->firstWhere('type', PlanningExceptionService::TYPE_SUBCONTRACT_DELAY);
        $this->assertNotNull($subEx);
        $this->assertEquals(PlanningExceptionService::SEVERITY_HIGH, $subEx['severity']);
        $this->assertEquals(PlanningExceptionService::REC_REVIEW_SUBCONTRACT, $subEx['recommended_action']);
    }

    /**
     * Test 7 — Quality / Rework Risk Evaluation.
     */
    public function test_07_quality_rework_risk_evaluation(): void
    {
        $order = $this->createOrder();
        $op = $order->operations->first();

        ProductionOrderRework::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op->id,
            'quantity' => 10.0,
            'reason' => 'Surface finish defect',
            'status' => 'pending',
            'recorded_by' => $this->user->id,
            'recorded_at' => now(),
        ]);

        $service = app(PlanningExceptionService::class);
        $risk = $service->evaluateOrderRisk($order->fresh());

        $qualEx = collect($risk['exceptions'])->firstWhere('type', PlanningExceptionService::TYPE_QUALITY_REWORK_RISK);
        $this->assertNotNull($qualEx);
        $this->assertEquals(PlanningExceptionService::SEVERITY_HIGH, $qualEx['severity']);
        $this->assertEquals(PlanningExceptionService::REC_REVIEW_QUALITY, $qualEx['recommended_action']);
    }

    /**
     * Test 8 — Multiple Exceptions Preserve All Drivers and Unified Risk.
     */
    public function test_08_multiple_exceptions_preserve_all_drivers(): void
    {
        $order = $this->createOrder(100, ProductionOrder::STATUS_RELEASED, '2026-08-31'); // Overdue -> CRITICAL
        $op = $order->operations->first();
        $op->update(['total_time_planned' => 600.0]); // Capacity overload -> HIGH

        $service = app(PlanningExceptionService::class);
        $risk = $service->evaluateOrderRisk($order);

        $this->assertEquals(PlanningExceptionService::SEVERITY_CRITICAL, $risk['overall_risk']);
        $this->assertGreaterThanOrEqual(2, count($risk['risk_drivers']));
    }

    /**
     * Test 9 — Risk Ranking: CRITICAL > HIGH > MEDIUM > LOW > ON_TRACK.
     */
    public function test_09_risk_ranking(): void
    {
        $orderCritical = $this->createOrder(100, ProductionOrder::STATUS_RELEASED, '2026-08-31');
        $orderOnTrack = $this->createOrder(100, ProductionOrder::STATUS_RELEASED, '2026-09-10');

        $service = app(PlanningExceptionService::class);
        $res = $service->evaluateAllOrders($this->tenant->id);

        $this->assertGreaterThanOrEqual(2, count($res['orders']));
        $this->assertEquals(PlanningExceptionService::SEVERITY_CRITICAL, $res['orders'][0]['overall_risk']);
    }

    /**
     * Test 10 — Planner Recommendations Returned with Zero Mutation (Read-Only).
     */
    public function test_10_read_only_guarantee(): void
    {
        $order = $this->createOrder();
        $initialStatus = $order->status;

        $service = app(PlanningExceptionService::class);
        $service->evaluateAllOrders($this->tenant->id);

        $order->refresh();
        $this->assertEquals($initialStatus, $order->status, 'Evaluation must be 100% read-only');
    }

    /**
     * Test 11 — Tenant Isolation.
     */
    public function test_11_tenant_isolation(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant', 'slug' => 'other-' . uniqid(), 'status' => 'active']);
        $order = $this->createOrder();

        $service = app(PlanningExceptionService::class);
        $res = $service->evaluateAllOrders($otherTenant->id);

        $this->assertEquals(0, count($res['orders']), 'Tenant isolation must prevent cross-tenant exception leakage');
    }

    /**
     * Test 12 — Multi-Order Batch Evaluation (No N+1 Query Behavior).
     */
    public function test_12_multi_order_batch_evaluation_no_n_plus_one(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createOrder();
        }

        \DB::enableQueryLog();
        $service = app(PlanningExceptionService::class);
        $service->evaluateAllOrders($this->tenant->id);
        $queryCount = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertLessThanOrEqual(60, $queryCount);
    }

    /**
     * Test 13 — Order With No Exceptions Classified as ON_TRACK.
     */
    public function test_13_order_with_no_exceptions_classified_on_track(): void
    {
        $order = $this->createOrder(100, ProductionOrder::STATUS_COMPLETED, '2026-09-10');

        $service = app(PlanningExceptionService::class);
        $risk = $service->evaluateOrderRisk($order);

        $this->assertEquals(PlanningExceptionService::SEVERITY_ON_TRACK, $risk['overall_risk']);
    }

    /**
     * Test 14 — In-Progress Orders Not Incorrectly Classified as Underproduction.
     */
    public function test_14_in_progress_order_not_classified_as_underproduction(): void
    {
        $order = $this->createOrder(100, ProductionOrder::STATUS_IN_PROGRESS, '2026-09-10');
        $order->update(['quantity_produced' => 40.0]); // Partial completion

        $service = app(PlanningExceptionService::class);
        $risk = $service->evaluateOrderRisk($order);

        $underEx = collect($risk['exceptions'])->firstWhere('type', 'UNDER_PRODUCTION');
        $this->assertNull($underEx, 'In-progress order with partial completion must not be marked as underproduction');
    }

    /**
     * Test 15 — Unrelated Pending ECO Does Not Create False Positive.
     */
    public function test_15_unrelated_pending_eco_does_not_create_false_positive(): void
    {
        $otherProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Unrelated Valve',
            'sku' => 'VALVE-' . uniqid(),
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $order = $this->createOrder();

        $unrelatedEco = ProductionEco::create([
            'tenant_id' => $this->tenant->id,
            'eco_number' => 'ECO-OTHER-' . uniqid(),
            'title' => 'Unrelated Valve Revision',
            'change_type' => 'bom',
            'product_id' => $otherProduct->id, // Unrelated product
            'status' => ProductionEco::STATUS_DRAFT,
        ]);

        $service = app(PlanningExceptionService::class);
        $context = ['ecos' => collect([$unrelatedEco])];
        $risk = $service->evaluateOrderRisk($order, $context);

        $ecoEx = collect($risk['exceptions'])->firstWhere('type', PlanningExceptionService::TYPE_PENDING_ECO_IMPACT);
        $this->assertNull($ecoEx, 'Unrelated pending ECO must not trigger false positive exception for this order');
    }
}
