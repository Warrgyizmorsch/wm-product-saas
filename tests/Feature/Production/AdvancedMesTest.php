<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBatchGenealogy;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use App\Domains\Production\Models\ProductionOperatorAssignmentLog;
use App\Domains\Production\Models\ProductionOperatorSkill;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\CodeService;
use App\Domains\Production\Services\LotTraceabilityService;
use App\Domains\Production\Services\OperatorAssignmentService;
use App\Domains\Production\Services\SchedulingService;
use App\Domains\Production\Services\SerialNumberService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdvancedMesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $operator;
    private Product $product;
    private WorkCenter $workCenter;
    private Machine $machine;
    private SchedulingService $schedulingService;
    private OperatorAssignmentService $assignmentService;
    private BatchProductionService $batchService;
    private SerialNumberService $serialService;
    private LotTraceabilityService $traceService;
    private CodeService $codeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Test Advanced MES Tenant',
            'slug'   => 'test-adv-mes',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Admin Manager',
            'email'     => 'admin@testadvmes.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->operator = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Floor Operator',
            'email'     => 'operator@testadvmes.com',
            'password'  => bcrypt('password'),
            'role'      => 'operator',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Advanced MES Product',
            'sku'       => 'ADV-MES-PROD',
            'type'      => 'finished_good',
            'status'    => 'active',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'Main Assembly Center',
            'code'                  => 'WC-MAIN',
            'status'                => 'active',
            'daily_capacity_hours'  => 16.0,
            'efficiency_percentage' => 100.0,
        ]);

        $this->machine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'name'           => 'CNC Cutter M-99',
            'code'           => 'M-99',
            'status'         => 'active',
        ]);

        // Bind Services
        $this->schedulingService = $this->app->make(SchedulingService::class);
        $this->assignmentService = $this->app->make(OperatorAssignmentService::class);
        $this->batchService      = $this->app->make(BatchProductionService::class);
        $this->serialService     = $this->app->make(SerialNumberService::class);
        $this->traceService      = $this->app->make(LotTraceabilityService::class);
        $this->codeService       = $this->app->make(CodeService::class);

        // Authenticate admin in session
        $this->actingAs($this->admin);
        session(['tenant_id' => $this->tenant->id]);
    }

    /**
     * Test Parallel Operations Scheduling.
     */
    public function test_parallel_operations_scheduling(): void
    {
        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'ORD-PARALLEL-01',
            'product_id'       => $this->product->id,
            'quantity_ordered' => 10,
            'start_date'       => today(),
            'end_date'         => today()->addDays(5),
            'status'           => 'draft',
        ]);

        // Add 2 parallel operations and 1 sequential successor
        $op1 = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence'            => 10,
            'operation_number'    => 'OP-10',
            'name'                => 'Cutting',
            'work_center_id'      => $this->workCenter->id,
            'setup_time_planned'  => 30,
            'processing_time_planned' => 60,
            'status'              => 'waiting',
            'is_parallel'         => true,
            'parallel_group'      => 'GROUP-A',
        ]);

        $op2 = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence'            => 20,
            'operation_number'    => 'OP-20',
            'name'                => 'Drilling',
            'work_center_id'      => $this->workCenter->id,
            'setup_time_planned'  => 30,
            'processing_time_planned' => 60,
            'status'              => 'waiting',
            'is_parallel'         => true,
            'parallel_group'      => 'GROUP-A',
        ]);

        $op3 = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence'            => 30,
            'operation_number'    => 'OP-30',
            'name'                => 'Polishing',
            'work_center_id'      => $this->workCenter->id,
            'setup_time_planned'  => 10,
            'processing_time_planned' => 30,
            'status'              => 'waiting',
            'is_parallel'         => false,
        ]);

        $schedule = $this->schedulingService->generateForwardSchedule($order, Carbon::parse('2026-07-10 08:00:00'));

        $schedOps = $schedule->operations()->orderBy('sequence')->get();

        $schedOp1 = $schedOps->where('production_order_operation_id', $op1->id)->first();
        $schedOp2 = $schedOps->where('production_order_operation_id', $op2->id)->first();
        $schedOp3 = $schedOps->where('production_order_operation_id', $op3->id)->first();

        // Check simultaneous scheduling
        $this->assertEquals($schedOp1->planned_start->toDateTimeString(), $schedOp2->planned_start->toDateTimeString());

        // Check sequential successor op3 starts after parallel finishes
        $maxParallelFinish = max($schedOp1->planned_finish->timestamp, $schedOp2->planned_finish->timestamp);
        $this->assertGreaterThanOrEqual($maxParallelFinish, $schedOp3->planned_start->timestamp);
    }

    /**
     * Test Operator Skills qualification.
     */
    public function test_operator_skills_qualification_validation(): void
    {
        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'ORD-MOCK-1',
            'product_id'       => $this->product->id,
            'quantity_ordered' => 10,
            'start_date'       => today(),
            'end_date'         => today()->addDays(5),
            'status'           => 'draft',
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence'            => 10,
            'operation_number'    => 'OP-10',
            'name'                => 'Laser Welding Extra',
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'status'              => 'waiting',
        ]);

        // Attempt assignment without skills registered -> should fail
        $this->expectException(\LogicException::class);
        $this->assignmentService->assign($this->tenant->id, $op->id, $this->operator->id, $this->admin->id);
    }

    /**
     * Test valid operator assignment.
     */
    public function test_valid_operator_assignment_succeeds_and_logs(): void
    {
        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'ORD-MOCK-2',
            'product_id'       => $this->product->id,
            'quantity_ordered' => 10,
            'start_date'       => today(),
            'end_date'         => today()->addDays(5),
            'status'           => 'draft',
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence'            => 10,
            'operation_number'    => 'OP-10',
            'name'                => 'Welding',
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'status'              => 'waiting',
        ]);

        // Assign skills
        ProductionOperatorSkill::create([
            'tenant_id'      => $this->tenant->id,
            'user_id'        => $this->operator->id,
            'skill_code'     => 'WELD',
            'work_center_id' => $this->workCenter->id,
            'active'         => true,
        ]);

        $assignment = $this->assignmentService->assign($this->tenant->id, $op->id, $this->operator->id, $this->admin->id);

        $this->assertInstanceOf(ProductionOperatorAssignment::class, $assignment);
        $this->assertEquals('assigned', $assignment->status);

        // Verify audit trail entry
        $this->assertDatabaseHas('production_operator_assignment_logs', [
            'operator_assignment_id' => $assignment->id,
            'action'                 => 'assigned',
            'new_operator_id'        => $this->operator->id,
        ]);
    }

    /**
     * Test Batch split and merge operations.
     */
    public function test_batch_split_and_merge(): void
    {
        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'ORD-BATCH-X',
            'product_id'       => $this->product->id,
            'quantity_ordered' => 100,
            'start_date'       => today(),
            'end_date'         => today()->addDays(5),
            'status'           => 'in_progress',
        ]);

        $batch = $this->batchService->createBatch($this->tenant->id, $order->id, $this->product->id, 100.0);

        $this->assertDatabaseHas('production_batches', [
            'id'           => $batch->id,
            'batch_number' => $batch->batch_number,
        ]);

        // Split
        $splits = [
            ['planned_quantity' => 60.0, 'remarks' => 'Split Alpha'],
            ['planned_quantity' => 40.0, 'remarks' => 'Split Beta'],
        ];

        $children = $this->batchService->splitBatch($this->tenant->id, $batch->id, $splits);

        $this->assertCount(2, $children);

        // Verify genealogy links
        $this->assertDatabaseHas('production_batch_genealogies', [
            'parent_batch_id' => $batch->id,
            'child_batch_id'  => $children[0]->id,
            'type'            => 'split',
        ]);

        // Merge
        $merged = $this->batchService->mergeBatches(
            $this->tenant->id,
            [$children[0]->id, $children[1]->id],
            100.0,
            'Merged from children.'
        );

        $this->assertInstanceOf(ProductionBatch::class, $merged);
        $this->assertEquals('consumed', $children[0]->fresh()->status);
    }

    /**
     * Test Serial range generation.
     */
    public function test_serial_number_generation(): void
    {
        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'ORD-SERIAL-Y',
            'product_id'       => $this->product->id,
            'quantity_ordered' => 5,
            'start_date'       => today(),
            'end_date'         => today()->addDays(5),
            'status'           => 'in_progress',
        ]);

        $serials = $this->serialService->generateSerials($this->tenant->id, $order->id, $this->product->id, 5, 'SN-MOCK', 1);

        $this->assertCount(5, $serials);
        $this->assertEquals('SN-MOCK000001', $serials[0]->serial_number);
        $this->assertEquals('SN-MOCK000005', $serials[4]->serial_number);
    }

    /**
     * Test barcode scan resolution.
     */
    public function test_barcode_scan_event_resolves_and_logs(): void
    {
        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'ORD-SCAN-01',
            'product_id'       => $this->product->id,
            'quantity_ordered' => 5,
            'start_date'       => today(),
            'end_date'         => today()->addDays(5),
            'status'           => 'in_progress',
        ]);

        $this->codeService->generate($order, 'order');

        $this->assertNotNull($order->fresh()->barcode);

        $resolved = $this->codeService->resolveEntity($order->barcode, $this->tenant->id, $this->admin->id, 'device_tab_01');

        $this->assertInstanceOf(ProductionOrder::class, $resolved);
        $this->assertEquals($order->id, $resolved->id);

        // Verify scan logs
        $this->assertDatabaseHas('production_scan_logs', [
            'entity_type'       => 'order',
            'entity_id'         => $order->id,
            'scanned_by'        => $this->admin->id,
            'device_identifier' => 'device_tab_01',
        ]);
    }
}
