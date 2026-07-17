<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Services\ProductionWipService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionWipTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $tenantId;
    private Product $finishedGood;
    private Product $rawMaterial;
    private Uom $uom;
    private WorkCenter $workCenter;
    private Machine $machine;
    private ProductionBom $bom;
    private Routing $routing;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Tenant
        $tenant = Tenant::create([
            'name' => 'WIP Test Tenant',
            'slug' => 'wip-test',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $this->tenantId = $tenant->id;

        // Create User
        $this->user = User::create([
            'tenant_id' => $this->tenantId,
            'name' => 'WIP Admin',
            'email' => 'wip-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($this->user);
        $this->withHeaders(['X-Tenant' => 'wip-test']);

        // Setup base data
        $this->uom = Uom::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Units',
            'code' => 'PCS',
            'type' => 'reference',
        ]);

        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Test Finished Good',
            'sku' => 'FG-TEST',
            'type' => 'finished_good',
            'unit_cost' => 100.00,
            'status' => 'active',
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Test Raw Material',
            'sku' => 'RM-TEST',
            'type' => 'raw_material',
            'unit_cost' => 10.00,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Main Test Warehouse',
            'code' => 'WH-TEST',
            'type' => 'raw_material',
            'status' => 'active',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Assembly WC',
            'code' => 'WC-ASSY',
            'overhead_rate' => 60.00, // $60/hr = $1/min overhead rate
            'status' => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name' => 'Assembly Machine 1',
            'code' => 'MC-ASSY-01',
            'status' => 'active',
        ]);

        $this->bom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'bom_number' => 'BOM-FG-TEST',
            'product_id' => $this->finishedGood->id,
            'version' => '1.0',
            'status' => 'active',
            'base_quantity' => 1,
            'uom_id' => $this->uom->id,
            'effective_date' => now()->toDateString(),
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RT-FG-TEST',
            'name' => 'Assembly Routing',
            'product_id' => $this->finishedGood->id,
            'version' => '1.0',
            'status' => 'active',
        ]);

        // Create two routing operations (Seq 10 and Seq 20)
        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $this->routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Initial Prep',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'labor_cost_rate' => 2.0000,   // $2/min labor rate
            'machine_cost_rate' => 3.0000, // $3/min machine rate
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $this->routing->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Final Assembly',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'labor_cost_rate' => 4.0000,   // $4/min labor rate
            'machine_cost_rate' => 5.0000, // $5/min machine rate
        ]);
    }

    private function createOrder(): ProductionOrder
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantId,
            'order_number' => 'PO-WIP-TEST',
            'product_id' => $this->finishedGood->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 10.0000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        // Copy operations onto order (simulate order release setup)
        foreach ($this->routing->operations as $op) {
            ProductionOrderOperation::create([
                'tenant_id' => $this->tenantId,
                'production_order_id' => $order->id,
                'routing_operation_id' => $op->id,
                'sequence' => $op->sequence,
                'operation_number' => $op->operation_number,
                'name' => $op->name,
                'work_center_id' => $op->work_center_id,
                'machine_id' => $op->machine_id,
                'status' => 'waiting',
            ]);
        }

        return $order;
    }

    public function test_wip_initialized_on_release(): void
    {
        $order = $this->createOrder();

        // Release order using post route
        $response = $this->post(route('production.orders.release', $order->id));
        $response->assertStatus(302);

        $order->refresh();
        $this->assertTrue($order->isReleased());

        // Check WIP creation
        $wip = ProductionWip::where('production_order_id', $order->id)->first();
        $this->assertNotNull($wip);
        $this->assertEquals(10.0000, $wip->quantity);
        $this->assertEquals('active', $wip->status);
        $this->assertEquals(10, $wip->currentRoutingOperation->sequence);

        // Check transaction creation
        $tx = ProductionWipTransaction::where('wip_id', $wip->id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals('created', $tx->transaction_type);
        $this->assertEquals(10.0000, $tx->quantity);
    }

    public function test_wip_cost_accrual_and_movement(): void
    {
        $order = $this->createOrder();
        $this->post(route('production.orders.release', $order->id));
        
        $wip = ProductionWip::where('production_order_id', $order->id)->first();

        // Let's create a schedule and schedule operation to simulate shop floor MES triggers
        $schedule = ProductionSchedule::create([
            'tenant_id' => $this->tenantId,
            'production_order_id' => $order->id,
            'schedule_number' => 'SCHED-WIP',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'status' => 'scheduled',
        ]);

        $orderOp1 = $order->operations()->where('sequence', 10)->first();
        $schedOp = ProductionScheduleOperation::create([
            'tenant_id' => $this->tenantId,
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $orderOp1->id,
            'sequence' => 10,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'scheduled_setup_minutes' => 10,
            'scheduled_run_minutes' => 50,
            'planned_start' => now(),
            'planned_finish' => now()->addHour(),
            'status' => 'ready',
        ]);

        $wipService = app(ProductionWipService::class);

        // 1. Start Operation
        $wipService->startWipOperation($wip->id, $schedOp->id, $this->user->id);
        $wip->refresh();
        $this->assertEquals($schedOp->id, $wip->current_schedule_operation_id);

        // 2. Complete Operation
        // OP-010: setup 10 mins, run 50 mins = 60 active minutes.
        // OP-010 rates: labor = $2/min, machine = $3/min, WC overhead = $1/min.
        // Expected costs added:
        // Labor: 60 * 2 = $120
        // Machine: 60 * 3 = $180
        // Overhead: 60 * 1 = $60
        // Total cost added = $360.
        $wipService->completeWipOperation(
            $wip->id,
            $schedOp->id,
            10.0000, // good
            0.0000,  // rejected
            0.0000,  // scrap
            10.0000, // setup mins
            50.0000, // run mins
            'Operation 10 completed',
            $this->user->id
        );

        $wip->refresh();
        $this->assertEquals(120.00, $wip->labor_cost);
        $this->assertEquals(180.00, $wip->machine_cost);
        $this->assertEquals(60.00, $wip->overhead_cost);
        $this->assertEquals(360.00, $wip->total_value);

        // 3. Move/Transfer to Seq 20
        $orderOp2 = $order->operations()->where('sequence', 20)->first();
        $wipService->transferWip(
            $wip->id,
            $orderOp1->routing_operation_id,
            $orderOp2->routing_operation_id,
            10.0000,
            'Transfer to Assembly',
            $this->user->id
        );

        $wip->refresh();
        $this->assertEquals($orderOp2->routing_operation_id, $wip->current_routing_operation_id);
    }

    public function test_wip_manual_adjustment(): void
    {
        $order = $this->createOrder();
        $this->post(route('production.orders.release', $order->id));
        $wip = ProductionWip::where('production_order_id', $order->id)->first();

        $wipService = app(ProductionWipService::class);
        $wipService->adjustWip($wip->id, 8.0000, 'Damage during setup', $this->user->id);

        $wip->refresh();
        $this->assertEquals(8.0000, $wip->quantity);
        $this->assertEquals(8.0000, $wip->available_quantity);

        $tx = ProductionWipTransaction::where('wip_id', $wip->id)
            ->where('transaction_type', 'adjusted')
            ->first();
        $this->assertNotNull($tx);
        $this->assertEquals(8.0000, $tx->quantity);
        $this->assertStringContainsString('Damage during setup', $tx->remarks);
    }
}
