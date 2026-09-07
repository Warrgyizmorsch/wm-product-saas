<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\RoutingOperationAlternateMachine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use App\Models\User;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    private \App\Models\Tenant $tenant;
    private Uom $uom;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 00:00:00');

        $this->tenant = \App\Models\Tenant::create([
            'name' => 'Readiness Tenant',
            'slug' => 'readiness-tenant-' . uniqid(),
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
            'name' => 'Readiness User',
            'email' => 'readiness-user-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    private function createOrder(int $qty = 100): ProductionOrder
    {
        $fg = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Product',
            'sku' => 'FG-READINESS-' . uniqid(),
            'type' => 'finished_good',
            'planning_type' => 'manufacture',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        return ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-READINESS-' . uniqid(),
            'product_id' => $fg->id,
            'quantity_ordered' => $qty,
            'status' => 'released',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
        ]);
    }

    private function createOp(ProductionOrder $order, int $seq = 10, string $name = 'Operation', ?int $prevOpId = null): ProductionOrderOperation
    {
        return ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => $seq,
            'operation_number' => "OP{$seq}",
            'name' => $name,
            'previous_operation_id' => $prevOpId,
            'target_produced_qty' => $order->quantity_ordered,
            'quantity_produced' => 0,
            'status' => 'ready',
        ]);
    }

    /**
     * Test 1 — Fully Ready: All prerequisites satisfied -> READY.
     */
    public function test_1_fully_ready_operation(): void
    {
        $order = $this->createOrder(100);
        $op = $this->createOp($order, 10, 'Cutting');

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op);

        $this->assertEquals(ProductionReadinessService::STATUS_READY, $eval['overall_status']);
        $this->assertEquals(100.0, $eval['ready_qty']);
        $this->assertEquals(0.0, $eval['blocked_qty']);
        $this->assertEmpty($eval['blocking_reasons']);
    }

    /**
     * Test 2 — Partial Material: Required = 100, Available = 70 -> PARTIALLY_READY.
     */
    public function test_2_partial_material_readiness(): void
    {
        $order = $this->createOrder(100);
        $op = $this->createOp($order, 10, 'Assembly');

        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Component Raw Material',
            'sku' => 'RM-PARTIAL',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        $wh = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Store',
            'code' => 'WH-STORE-1',
        ]);

        ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $rm->id,
            'warehouse_id' => $wh->id,
            'quantity' => 70.0,
            'reserved_qty' => 0.0,
        ]);

        ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $rm->id,
            'warehouse_id' => $wh->id,
            'uom_id' => $this->uom->id,
            'quantity_planned' => 100.0,
            'quantity_issued' => 0.0,
            'quantity_reserved' => 0.0,
        ]);

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op);

        $this->assertEquals(ProductionReadinessService::STATUS_PARTIALLY_READY, $eval['overall_status']);
        $this->assertEquals(70.0, $eval['ready_qty']);
        $this->assertEquals(30.0, $eval['blocked_qty']);
    }

    /**
     * Test 3 — Full Material Shortage -> BLOCKED, MATERIAL_SHORTAGE.
     */
    public function test_3_full_material_shortage_blocks_operation(): void
    {
        $order = $this->createOrder(100);
        $op = $this->createOp($order, 10, 'Molding');

        $rm = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plastic Resin',
            'sku' => 'RM-MISSING',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        ProductionOrderReservation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id' => $rm->id,
            'uom_id' => $this->uom->id,
            'quantity_planned' => 100.0,
            'quantity_issued' => 0.0,
            'quantity_reserved' => 0.0,
        ]);

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op);

        $this->assertEquals(ProductionReadinessService::STATUS_BLOCKED, $eval['overall_status']);
        $this->assertEquals(0.0, $eval['ready_qty']);
        $this->assertNotEmpty($eval['blocking_reasons']);
        $this->assertEquals(ProductionReadinessService::REASON_MATERIAL_SHORTAGE, $eval['blocking_reasons'][0]['code']);
    }

    /**
     * Test 4 — Predecessor Incomplete -> BLOCKED, PREDECESSOR_INCOMPLETE.
     */
    public function test_4_predecessor_incomplete_blocks_successor(): void
    {
        $order = $this->createOrder(100);
        $op1 = $this->createOp($order, 10, 'Cut');
        $op2 = $this->createOp($order, 20, 'Weld', $op1->id);

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op2);

        $this->assertEquals(ProductionReadinessService::STATUS_BLOCKED, $eval['overall_status']);
        $this->assertEquals(ProductionReadinessService::REASON_PREDECESSOR_INCOMPLETE, $eval['blocking_reasons'][0]['code']);
    }

    /**
     * Test 5 — Partial WIP Transferred -> PARTIALLY_READY.
     */
    public function test_5_partial_wip_transfer_allows_partial_readiness(): void
    {
        $order = $this->createOrder(100);
        $op1 = $this->createOp($order, 10, 'Cut');
        $op1->update(['quantity_produced' => 60, 'quantity_transferred_out' => 60]);

        $op2 = $this->createOp($order, 20, 'Weld', $op1->id);

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op2);

        $this->assertEquals(ProductionReadinessService::STATUS_PARTIALLY_READY, $eval['overall_status']);
        $this->assertEquals(60.0, $eval['ready_qty']);
        $this->assertEquals(40.0, $eval['blocked_qty']);
    }

    /**
     * Test 6 — Machine Under Maintenance (No Alternate) -> BLOCKED.
     */
    public function test_6_machine_under_maintenance_blocks_operation(): void
    {
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'CNC Milling Line',
            'code' => 'WC-CNC-' . uniqid(),
            'status' => 'active',
        ]);

        $machine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name' => 'CNC Router #1',
            'code' => 'MCH-1',
            'status' => 'under_maintenance',
        ]);

        $order = $this->createOrder(100);
        $op = $this->createOp($order, 10, 'Milling');
        $op->update(['work_center_id' => $wc->id, 'machine_id' => $machine->id]);

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op);

        $this->assertEquals(ProductionReadinessService::STATUS_BLOCKED, $eval['overall_status']);
        $this->assertEquals(ProductionReadinessService::REASON_MACHINE_UNDER_MAINTENANCE, $eval['blocking_reasons'][0]['code']);
    }

    /**
     * Test 7 — Alternate Machine Available -> READY with warning.
     */
    public function test_7_alternate_machine_available_prevents_hard_block(): void
    {
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Press Line',
            'code' => 'WC-PRESS-' . uniqid(),
            'status' => 'active',
        ]);

        $primaryMachine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name' => 'Primary Press',
            'code' => 'MCH-P-' . uniqid(),
            'status' => 'under_maintenance',
        ]);

        $altMachine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $wc->id,
            'name' => 'Backup Press',
            'code' => 'MCH-B-' . uniqid(),
            'status' => 'active',
        ]);

        $order = $this->createOrder(100);
        $op = $this->createOp($order, 10, 'Stamping');
        $op->update(['work_center_id' => $wc->id, 'machine_id' => $primaryMachine->id]);

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op);

        $this->assertEquals(ProductionReadinessService::STATUS_READY, $eval['overall_status']);
        $this->assertTrue($eval['machine']['alternate_available']);
        $this->assertNotEmpty($eval['warnings']);
    }

    /**
     * Test 8 — Subcontract WIP Pending -> BLOCKED, SUBCONTRACT_WIP_PENDING.
     */
    public function test_8_subcontract_wip_pending_blocks_external_op(): void
    {
        $order = $this->createOrder(100);
        $op = $this->createOp($order, 10, 'Heat Treatment (Outsourced)');
        $op->update(['is_external' => true, 'status' => 'sent_to_vendor']);

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op);

        $this->assertEquals(ProductionReadinessService::STATUS_BLOCKED, $eval['overall_status']);
        $this->assertEquals(ProductionReadinessService::REASON_SUBCONTRACT_WIP_PENDING, $eval['blocking_reasons'][0]['code']);
    }

    /**
     * Test 9 — QC Pending -> BLOCKED, QC_PENDING.
     */
    public function test_9_qc_pending_blocks_operation_when_qc_required(): void
    {
        $order = $this->createOrder(100);
        $routing = \App\Domains\Production\Models\Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $order->product_id,
            'routing_number' => 'RT-' . uniqid(),
            'name' => 'Grinding Routing',
            'status' => 'active',
        ]);
        $routingOp = \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'operation_number' => 'OP10',
            'name' => 'Precision Grinding',
            'sequence' => 10,
            'quality_required' => true,
        ]);

        $op = $this->createOp($order, 10, 'Precision Grinding');
        $op->update(['routing_operation_id' => $routingOp->id]);

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op);

        $this->assertEquals(ProductionReadinessService::STATUS_BLOCKED, $eval['overall_status']);
        $this->assertEquals(ProductionReadinessService::REASON_QC_PENDING, $eval['blocking_reasons'][0]['code']);
    }

    /**
     * Test 10 — QC Failed -> BLOCKED, QC_FAILED.
     */
    public function test_10_qc_failed_blocks_operation(): void
    {
        $order = $this->createOrder(100);
        $routing = \App\Domains\Production\Models\Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $order->product_id,
            'routing_number' => 'RT-' . uniqid(),
            'name' => 'Grinding Routing',
            'status' => 'active',
        ]);
        $routingOp = \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'operation_number' => 'OP10',
            'name' => 'Precision Grinding',
            'sequence' => 10,
            'quality_required' => true,
        ]);

        $op = $this->createOp($order, 10, 'Precision Grinding');
        $op->update(['routing_operation_id' => $routingOp->id]);

        $qp = \App\Domains\Production\Models\ProductionQualityPlan::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Default Quality Plan',
            'type' => 'in_process',
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        ProductionQualityInspection::create([
            'tenant_id' => $this->tenant->id,
            'quality_plan_id' => $qp->id,
            'stage' => 'in_process',
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op->id,
            'status' => 'rejected',
            'result' => 'failed',
        ]);

        $readinessService = app(ProductionReadinessService::class);
        $eval = $readinessService->evaluateOperationReadiness($op);

        $this->assertEquals(ProductionReadinessService::STATUS_BLOCKED, $eval['overall_status']);
        $this->assertEquals(ProductionReadinessService::REASON_QC_FAILED, $eval['blocking_reasons'][0]['code']);
    }

    /**
     * Test 11 — Multi-Operation Production Order Summary.
     */
    public function test_11_multi_operation_order_summary(): void
    {
        $order = $this->createOrder(100);
        $op1 = $this->createOp($order, 10, 'Cutting'); // READY
        $op1->update(['quantity_produced' => 50, 'quantity_transferred_out' => 50]);

        $op2 = $this->createOp($order, 20, 'Bending', $op1->id); // PARTIALLY READY (50/100)

        $op3 = $this->createOp($order, 30, 'Assembly', $op2->id); // BLOCKED (0/100)

        $readinessService = app(ProductionReadinessService::class);
        $orderEval = $readinessService->evaluateOrderReadiness($order);

        $this->assertEquals(ProductionReadinessService::STATUS_PARTIALLY_READY, $orderEval['overall_status']);
        $this->assertEquals(3, $orderEval['total_operations']);
        $this->assertEquals(1, $orderEval['ready_operations_count']);
        $this->assertEquals(1, $orderEval['partial_operations_count']);
        $this->assertEquals(1, $orderEval['blocked_operations_count']);
    }

    /**
     * Test 12 — Tenant Isolation: Tenant A readiness queries never see Tenant B data.
     */
    public function test_12_tenant_isolation_in_readiness(): void
    {
        $orderA = $this->createOrder(100);
        $opA = $this->createOp($orderA, 10, 'Tenant A Op');

        $tenantB = \App\Models\Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-readiness-' . uniqid(),
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        // Tenant B machine under maintenance
        $wcB = WorkCenter::create(['tenant_id' => $tenantB->id, 'name' => 'WC B', 'code' => 'WC-B-' . uniqid(), 'status' => 'active']);
        $mchB = Machine::create(['tenant_id' => $tenantB->id, 'work_center_id' => $wcB->id, 'name' => 'Mch B', 'code' => 'MCH-B-' . uniqid(), 'status' => 'under_maintenance']);

        $readinessService = app(ProductionReadinessService::class);
        $evalA = $readinessService->evaluateOperationReadiness($opA);

        $this->assertEquals(ProductionReadinessService::STATUS_READY, $evalA['overall_status']);
    }

    /**
     * Test 13 — Side-Effect Safety: Readiness evaluation is 100% read-only.
     */
    public function test_13_readiness_evaluation_is_read_only_and_has_no_side_effects(): void
    {
        $order = $this->createOrder(100);
        $op = $this->createOp($order, 10, 'Non-mutating Op');

        $initialOpCount = ProductionOrderOperation::count();
        $initialResvCount = ProductionOrderReservation::count();

        $readinessService = app(ProductionReadinessService::class);
        $readinessService->evaluateOperationReadiness($op);
        $readinessService->evaluateOrderReadiness($order);

        $this->assertEquals($initialOpCount, ProductionOrderOperation::count());
        $this->assertEquals($initialResvCount, ProductionOrderReservation::count());
    }
}
