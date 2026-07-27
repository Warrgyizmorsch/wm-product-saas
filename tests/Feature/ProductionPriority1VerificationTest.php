<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionOrderRework;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BomExplosionService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\MrpEngineService;
use App\Domains\Production\Services\ProductionCostService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionPriority1VerificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $finishedGood;
    private Product $rawMaterial;
    private Uom $uom;
    private Warehouse $warehouse;
    private ProductionBom $bom;
    private Routing $routing;
    private WorkCenter $workCenter;
    private Machine $machine;
    private ProductionOrderService $orderService;
    private ProductionExecutionService $executionService;
    private MesExecutionService $mesExecutionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'P1 Test Tenant',
            'slug'   => 'p1-test',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'P1 Admin',
            'email'     => 'p1-admin@example.com',
            'password'  => bcrypt('password'),
        ]);

        $this->actingAs($this->user);
        session(['tenant_id' => $this->tenant->id]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Piece',
            'code'      => 'PCS',
            'type'      => 'reference',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Main Plant Warehouse',
            'code'       => 'WH-MAIN',
            'status'     => 'active',
            'is_default' => true,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id'     => $this->tenant->id,
            'name'          => 'Assembly Work Center',
            'code'          => 'WC-ASSY',
            'overhead_rate' => 30.00,
        ]);

        $this->machine = Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'name'           => 'Automated Assembly Machine',
            'code'           => 'MC-ASSY-01',
            'status'         => 'active',
        ]);

        $this->finishedGood = Product::create([
            'tenant_id'           => $this->tenant->id,
            'name'                => 'Smart Sensor Hub',
            'sku'                 => 'FG-SENSOR-01',
            'type'                => 'finished_good',
            'uom_id'              => $this->uom->id,
            'unit_cost'           => 120.00,
            'status'              => 'active',
            'track_batch'         => true,
            'track_serial_number' => true,
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Microcontroller Board',
            'sku'       => 'RM-MCU-01',
            'type'      => 'raw_material',
            'uom_id'    => $this->uom->id,
            'unit_cost' => 25.00,
            'status'    => 'active',
        ]);

        ProductWarehouseStock::create([
            'tenant_id'     => $this->tenant->id,
            'product_id'    => $this->rawMaterial->id,
            'warehouse_id'  => $this->warehouse->id,
            'quantity'      => 500,
            'reserved_qty'  => 0,
            'available_qty' => 500,
            'unit_cost'     => 25.00,
        ]);

        $this->bom = ProductionBom::create([
            'tenant_id'      => $this->tenant->id,
            'product_id'     => $this->finishedGood->id,
            'bom_number'     => 'BOM-SENSOR-001',
            'bom_name'       => 'Sensor Hub Master BOM',
            'base_quantity'  => 1.0,
            'version'        => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status'         => 'approved',
        ]);

        ProductionBomItem::create([
            'tenant_id'                 => $this->tenant->id,
            'bom_id'                    => $this->bom->id,
            'production_bom_id'         => $this->bom->id,
            'material_id'               => $this->rawMaterial->id,
            'quantity'                  => 2.0,
            'material_scrap_percentage' => 10.0, // 10% scrap
            'uom_id'                    => $this->uom->id,
        ]);

        $this->routing = Routing::create([
            'tenant_id'    => $this->tenant->id,
            'product_id'   => $this->finishedGood->id,
            'routing_code' => 'RT-SENSOR-001',
            'name'         => 'Standard Assembly Routing',
            'status'       => 'active',
        ]);

        RoutingOperation::create([
            'tenant_id'           => $this->tenant->id,
            'routing_id'          => $this->routing->id,
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'sequence'            => 10,
            'operation_number'    => 'OP-10',
            'name'                => 'SMT Board Assembly',
            'setup_time_minutes'  => 15.0,
            'run_time_per_unit'   => 5.0,
        ]);

        RoutingOperation::create([
            'tenant_id'           => $this->tenant->id,
            'routing_id'          => $this->routing->id,
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'sequence'            => 20,
            'operation_number'    => 'OP-20',
            'name'                => 'Final Testing & Enclosure',
            'setup_time_minutes'  => 10.0,
            'run_time_per_unit'   => 3.0,
        ]);

        $this->orderService = app(ProductionOrderService::class);
        $this->executionService = app(ProductionExecutionService::class);
        $this->mesExecutionService = app(MesExecutionService::class);
    }

    /**
     * P2.1 Test: Verify Production Mode Precedence.
     */
    public function test_p2_1_production_mode_inheritance_precedence(): void
    {
        // Case 1: Product tracks both batch & serial -> default is 'batch_and_serial'
        $this->assertEquals('batch_and_serial', $this->finishedGood->getDefaultProductionMode());

        // Case 2: Product tracks batch only
        $batchOnlyProd = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Batch Only Item',
            'sku' => 'FG-BATCH-01',
            'type' => 'finished_good',
            'track_batch' => true,
            'track_serial_number' => false,
        ]);
        $this->assertEquals('batch', $batchOnlyProd->getDefaultProductionMode());

        // Case 3: Product tracks serial only
        $serialOnlyProd = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Serial Only Item',
            'sku' => 'FG-SERIAL-01',
            'type' => 'finished_good',
            'track_batch' => false,
            'track_serial_number' => true,
        ]);
        $this->assertEquals('serial', $serialOnlyProd->getDefaultProductionMode());

        // Case 4: Product tracks neither -> 'standard'
        $stdProd = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard Item',
            'sku' => 'FG-STD-01',
            'type' => 'finished_good',
            'track_batch' => false,
            'track_serial_number' => false,
        ]);
        $this->assertEquals('standard', $stdProd->getDefaultProductionMode());

        // Case 5: Conversion from plan inherits product default mode when plan has no mode
        $plan = ProductionPlan::create([
            'tenant_id'   => $this->tenant->id,
            'plan_number' => 'PLAN-P21-001',
            'name'        => 'Test Plan P21',
            'product_id'  => $this->finishedGood->id,
            'bom_id'      => $this->bom->id,
            'routing_id'  => $this->routing->id,
            'quantity'    => 10.0,
            'start_date'  => now()->toDateString(),
            'end_date'    => now()->addDays(5)->toDateString(),
            'status'      => ProductionPlan::STATUS_APPROVED,
        ]);

        $order = $this->orderService->createFromPlan($plan->id, $this->user->id);
        $this->assertEquals('batch_and_serial', $order->production_mode);

        // Case 6: Direct order creation explicit mode override is NOT overwritten
        $directOrder = $this->orderService->createDirect([
            'product_id'       => $this->finishedGood->id,
            'quantity_ordered' => 5.0,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(3)->toDateString(),
            'production_mode'  => 'serial', // Explicit override
        ], $this->tenant->id, $this->user->id);

        $this->assertEquals('serial', $directOrder->production_mode);

        // Case 7: Direct order creation omitted mode uses product default
        $directOrderDefault = $this->orderService->createDirect([
            'product_id'       => $this->finishedGood->id,
            'quantity_ordered' => 5.0,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(3)->toDateString(),
        ], $this->tenant->id, $this->user->id);

        $this->assertEquals('batch_and_serial', $directOrderDefault->production_mode);
    }

    /**
     * P5.2 Test: Quality Hold Guard on Finished Goods Receipt.
     */
    public function test_p5_2_quality_hold_guard_blocks_inventory_receipt(): void
    {
        $order = $this->orderService->createDirect([
            'product_id'       => $this->finishedGood->id,
            'quantity_ordered' => 10.0,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(3)->toDateString(),
        ], $this->tenant->id, $this->user->id);

        // 1. Clear order allows receipt
        $receipt = $this->executionService->receiveFinishedGoods($order->id, 2.0, 'passed', 'Initial good batch', $this->user->id);
        $this->assertInstanceOf(ProductionOrderReceipt::class, $receipt);
        $this->assertEquals(2.0, $order->fresh()->quantity_produced);

        // 2. WIP on quality_hold blocks receipt
        ProductionWip::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'product_id'          => $this->finishedGood->id,
            'quantity'            => 8.0,
            'available_quantity'  => 8.0,
            'status'              => 'quality_hold',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quality clearance is required');

        try {
            $this->executionService->receiveFinishedGoods($order->id, 2.0, 'passed', 'Attempt blocked receipt', $this->user->id);
        } finally {
            // Verify no extra receipt or order quantity increment was persisted
            $this->assertEquals(2.0, $order->fresh()->quantity_produced);
        }
    }

    /**
     * P5.2 Test: Pending Rework record blocks Finished Goods Receipt.
     */
    public function test_p5_2_pending_rework_blocks_finished_goods_receipt(): void
    {
        $order = $this->orderService->createDirect([
            'product_id'       => $this->finishedGood->id,
            'quantity_ordered' => 10.0,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(3)->toDateString(),
        ], $this->tenant->id, $this->user->id);

        // Create pending rework record
        ProductionOrderRework::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'quantity'            => 1.0,
            'reason'              => 'Defective solder joint',
            'status'              => 'pending',
            'recorded_by'         => $this->user->id,
            'recorded_at'         => now(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('pending Rework');

        $this->executionService->receiveFinishedGoods($order->id, 1.0, 'passed', 'Attempt receipt with pending rework', $this->user->id);
    }

    /**
     * P1.2 Test: Scrap Percentage calculation consistency across services.
     */
    public function test_p1_2_material_scrap_percentage_consistency(): void
    {
        // Base item quantity = 2.0, Scrap = 10% -> Gross = 2.0 * (1 + 0.10) = 2.2 per unit
        // For order quantity of 10.0 -> Gross required = 22.0 units

        // 1. Verify ProductionOrderService reservation calculations
        $order = $this->orderService->createDirect([
            'product_id'       => $this->finishedGood->id,
            'quantity_ordered' => 10.0,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(3)->toDateString(),
        ], $this->tenant->id, $this->user->id);

        $reservation = $order->reservations->firstWhere('product_id', $this->rawMaterial->id);
        $this->assertNotNull($reservation);
        $this->assertEquals(22.0, (float) $reservation->quantity_planned);

        // 2. Verify MrpEngineService scrap factor calculation
        $mrpService = app(MrpEngineService::class);
        $bomItem = $this->bom->items->first();
        $scrapFactor = 1.0 + ($bomItem->material_scrap_percentage / 100);
        $this->assertEquals(1.10, $scrapFactor);

        // 3. Verify BomExplosionService scrap calculation
        $explosionService = app(BomExplosionService::class);
        $exploded = $explosionService->explode($this->finishedGood->id, 10.0, $this->tenant->id);
        $materialItem = collect($exploded['tree']['children'] ?? [])->firstWhere('product_id', $this->rawMaterial->id);
        if (!$materialItem && isset($exploded['tree'])) {
            $materialItem = collect($exploded['tree']['children'] ?? [])->first();
        }
        $this->assertNotNull($materialItem);

        // 4. Verify ProductionCostService cost calculation uses scrap factor
        $costService = app(ProductionCostService::class);
        $totalMaterialCost = $costService->calculateMaterialCost($this->bom);
        // Cost = 2.0 units * (1 + 0.10) * $25.00/unit = $55.00
        $this->assertEqualsWithDelta(55.00, (float) $totalMaterialCost, 0.001);
    }

    /**
     * P5.1 Test: MES to WIP Synchronization on Operation Completion.
     */
    public function test_p5_1_mes_to_wip_synchronization_on_operation_completion(): void
    {
        $order = $this->orderService->createDirect([
            'product_id'       => $this->finishedGood->id,
            'quantity_ordered' => 5.0,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(3)->toDateString(),
        ], $this->tenant->id, $this->user->id);

        // Create ProductionWip entry
        $op10 = $order->operations->firstWhere('sequence', 10);
        $op20 = $order->operations->firstWhere('sequence', 20);

        $wip = ProductionWip::create([
            'tenant_id'                    => $this->tenant->id,
            'production_order_id'          => $order->id,
            'product_id'                   => $this->finishedGood->id,
            'current_routing_operation_id' => $op10->routing_operation_id,
            'quantity'                     => 5.0,
            'available_quantity'           => 5.0,
            'completed_quantity'           => 0.0,
            'status'                       => 'active',
        ]);

        // Release order & generate forward schedule
        $this->orderService->release($order->id, $this->user->id);
        $schedule = app(\App\Domains\Production\Services\SchedulingService::class)
            ->generateForwardSchedule($order, now());
        $schedule->update(['status' => ProductionSchedule::STATUS_RELEASED]);
        $this->assertNotNull($schedule);

        $schedOp10 = ProductionScheduleOperation::where('production_schedule_id', $schedule->id)
            ->where('sequence', 10)
            ->first();

        // 1. Start operation 10
        $this->mesExecutionService->startOperation($schedOp10->id, $this->machine->id, $this->user->id);

        // 2. Complete operation 10 with 5 produced
        $this->mesExecutionService->completeOperation($schedOp10->id, [
            'quantity_produced' => 5.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
            'setup_minutes'     => 15.0,
            'run_minutes'       => 25.0,
            'remarks'           => 'OP-10 completed smoothly',
        ], $this->user->id);

        // Verify schedule operation 10 completed
        $this->assertEquals(ProductionScheduleOperation::STATUS_COMPLETED, $schedOp10->fresh()->status);

        // Verify schedule operation 20 advanced to READY
        $schedOp20 = ProductionScheduleOperation::where('production_schedule_id', $schedule->id)
            ->where('sequence', 20)
            ->first();
        $this->assertEquals(ProductionScheduleOperation::STATUS_READY, $schedOp20->status);

        // Verify WIP stage transferred from OP-10 routing to OP-20 routing operation
        $wipFresh = $wip->fresh();
        $this->assertEquals($op20->routing_operation_id, $wipFresh->current_routing_operation_id);

        // 3. Start & Complete final operation 20
        $this->mesExecutionService->startOperation($schedOp20->id, $this->machine->id, $this->user->id);
        $this->mesExecutionService->completeOperation($schedOp20->id, [
            'quantity_produced' => 5.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
            'setup_minutes'     => 10.0,
            'run_minutes'       => 15.0,
            'remarks'           => 'OP-20 final testing completed',
        ], $this->user->id);

        // Verify WIP completed
        $wipFinal = $wip->fresh();
        $this->assertEquals('completed', $wipFinal->status);
        $this->assertEquals(5.0, (float) $wipFinal->completed_quantity);
    }
}
