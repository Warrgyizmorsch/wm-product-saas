<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionRouting;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOperationProgressLoggingFixTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Product $product;
    protected WorkCenter $workCenter;
    protected Routing $routing;
    protected RoutingOperation $rOp10;
    protected RoutingOperation $rOp20;
    protected RoutingOperation $rOp30;
    protected ProductionExecutionService $executionService;
    protected MesExecutionService $mesService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Product',
            'code' => 'PROD-001',
            'sku' => 'SKU-001',
            'unit_of_measure' => 'PCS',
            'is_active' => true,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Workcenter',
            'code' => 'WC-01',
            'capacity_per_day' => 100,
            'cost_per_hour' => 50,
            'is_active' => true,
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'name' => 'Main Routing',
            'code' => 'ROUTING-01',
            'is_active' => true,
        ]);

        $this->rOp10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Cutting',
            'work_center_id' => $this->workCenter->id,
        ]);

        $this->rOp20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-20',
            'name' => 'Brazing',
            'work_center_id' => $this->workCenter->id,
        ]);

        $this->rOp30 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 30,
            'operation_number' => 'OP-30',
            'name' => 'Inspection',
            'work_center_id' => $this->workCenter->id,
        ]);

        $this->executionService = app(ProductionExecutionService::class);
        $this->mesService = app(MesExecutionService::class);
    }

    public function test_logging_progress_on_successor_operation_when_no_batch_exists_succeeds(): void
    {
        // 1. Create order with 3 operations: op10 (seq 10), op20 (seq 20), op30 (seq 30)
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-TEST-001',
            'product_id' => $this->product->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 100.0,
            'quantity_produced' => 0.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp10->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Cutting',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
            'target_produced_qty' => 100.0,
        ]);

        $op20 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp20->id,
            'sequence' => 20,
            'previous_operation_id' => $op10->id,
            'operation_number' => 'OP-20',
            'name' => 'Brazing',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
            'target_produced_qty' => 100.0,
            'quantity_transferred_in' => 50.0,
        ]);

        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp30->id,
            'sequence' => 30,
            'previous_operation_id' => $op20->id,
            'operation_number' => 'OP-30',
            'name' => 'Inspection',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
            'target_produced_qty' => 100.0,
            'quantity_transferred_in' => 50.0,
        ]);

        // Verify 0 production batches currently exist for order
        $this->assertEquals(0, \App\Domains\Production\Models\ProductionBatch::where('production_order_id', $order->id)->count());

        // 2. Log progress on op30 from Production Order operations tab (calls executionService->logProgress)
        $log = $this->executionService->logProgress(
            $op30->id,
            10.0, // produced
            0.0,  // rejected
            0.0,  // scrapped
            0.0,  // setup
            15.0, // run
            'Progress logged at op30',
            null,
            $this->user->id
        );

        $this->assertNotNull($log);
        $this->assertEquals(10.0, $log->quantity_produced);

        $op30->refresh();
        $this->assertEquals(10.0, $op30->quantity_produced);
    }

    public function test_mes_operator_partial_progress_logging_on_successor_op_succeeds(): void
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-TEST-002',
            'product_id' => $this->product->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 50.0,
            'quantity_produced' => 0.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'schedule_number' => 'SCH-TEST-002',
            'status' => 'in_progress',
        ]);

        $op10 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp10->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'First Op',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_COMPLETED,
            'target_produced_qty' => 50.0,
            'quantity_produced' => 50.0,
            'quantity_transferred_out' => 50.0,
        ]);

        $op30 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $this->rOp30->id,
            'sequence' => 30,
            'previous_operation_id' => $op10->id,
            'operation_number' => 'OP-30',
            'name' => 'CAB Furnace Continuous Brazing',
            'work_center_id' => $this->workCenter->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
            'target_produced_qty' => 50.0,
            'quantity_transferred_in' => 50.0,
        ]);

        $schedOp = ProductionScheduleOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op30->id,
            'sequence' => 30,
            'planned_start' => now()->subHours(2),
            'planned_finish' => now()->addHours(2),
            'status' => ProductionScheduleOperation::STATUS_RUNNING,
            'actual_start' => now()->subHour(),
        ]);

        // Log partial progress via MES Execution Service
        $this->mesService->logPartialProgress($schedOp->id, [
            'quantity_produced' => 5.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
            'remarks' => 'Logging progress from operator operations screen',
        ], $this->user->id);

        $op30->refresh();
        $this->assertEquals(5.0, $op30->quantity_produced);
    }
}
