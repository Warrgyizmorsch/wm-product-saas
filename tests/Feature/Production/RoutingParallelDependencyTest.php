<?php

namespace Tests\Feature\Production;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderOperationDependency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutingParallelDependencyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $product;
    private WorkCenter $workCenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Parallel Routing Tenant',
            'slug' => 'parallel-routing',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Production Manager',
            'email' => 'manager@parallel-test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Turbine Assembly',
            'sku' => 'FG-TURBINE-01',
            'type' => 'finished_good',
            'status' => 'active',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Work Center',
            'code' => 'WC-ASSY-01',
            'status' => 'active',
        ]);
    }

    public function test_routing_creation_with_parallel_and_arbitrary_predecessors(): void
    {
        $payload = [
            'name' => 'Turbine Multi-Branch Routing',
            'product_id' => $this->product->id,
            'version' => '1.0.0',
            'effective_from' => '2026-06-30',
            'is_default' => '1',
            'operations' => [
                [
                    'sequence' => 10,
                    'name' => 'Base Machining',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 10,
                    'processing_time_minutes' => 20,
                    'is_parallel' => 0,
                    'predecessor_sequence' => 0, // Auto
                ],
                [
                    'sequence' => 20,
                    'name' => 'Independent Sub-assembly',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 15,
                    'is_parallel' => 0,
                    'predecessor_sequence' => -1, // None (Independent)
                ],
                [
                    'sequence' => 30,
                    'name' => 'Parallel Wire Harnessing',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 10,
                    'is_parallel' => 1,
                    'parallel_group' => 'ELEC_BRANCH',
                    'predecessor_sequence' => 0,
                ],
                [
                    'sequence' => 40,
                    'name' => 'Final QA (Blocks on Base Machining)',
                    'operation_type' => 'manufacturing',
                    'work_center_id' => $this->workCenter->id,
                    'setup_time_minutes' => 5,
                    'processing_time_minutes' => 15,
                    'is_parallel' => 0,
                    'predecessor_sequence' => 10, // Explicitly blocked by Seq 10
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'parallel-routing')
            ->post(route('production.routing.store'), $payload);

        $response->assertRedirect();

        $routing = Routing::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($routing);
        $this->assertCount(4, $routing->operations);

        $op10 = $routing->operations()->where('sequence', 10)->first();
        $op20 = $routing->operations()->where('sequence', 20)->first();
        $op30 = $routing->operations()->where('sequence', 30)->first();
        $op40 = $routing->operations()->where('sequence', 40)->first();

        // Op 10: first op, previous_operation_id is null
        $this->assertNull($op10->previous_operation_id);
        $this->assertFalse((bool)$op10->is_parallel);

        // Op 20: independent, previous_operation_id is null
        $this->assertNull($op20->previous_operation_id);
        $this->assertFalse((bool)$op20->is_parallel);

        // Op 30: parallel, previous_operation_id is null, parallel_group is ELEC_BRANCH
        $this->assertNull($op30->previous_operation_id);
        $this->assertTrue((bool)$op30->is_parallel);
        $this->assertEquals('ELEC_BRANCH', $op30->parallel_group);

        // Op 40: arbitrary predecessor is Op 10
        $this->assertNotNull($op40->previous_operation_id);
        $this->assertEquals($op10->id, $op40->previous_operation_id);
    }

    public function test_production_order_generation_propagates_parallel_and_predecessor(): void
    {
        // 1. Create active routing
        $routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'routing_number' => 'RT-TURB-01',
            'name' => 'Turbine Route',
            'version' => '1.0.0',
            'status' => 'active',
            'effective_from' => '2026-01-01',
            'is_default' => true,
        ]);

        $op1 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'operation_number' => 'OP-010',
            'sequence' => 10,
            'name' => 'Prep',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 30,
            'is_parallel' => false,
            'previous_operation_id' => null,
        ]);

        $op2 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'operation_number' => 'OP-020',
            'sequence' => 20,
            'name' => 'Independent Wiring',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 5,
            'processing_time_minutes' => 20,
            'is_parallel' => false,
            'previous_operation_id' => null, // Independent
        ]);

        $op3 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $routing->id,
            'operation_number' => 'OP-030',
            'sequence' => 30,
            'name' => 'Final QA',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 5,
            'processing_time_minutes' => 15,
            'is_parallel' => false,
            'previous_operation_id' => $op1->id, // Explicit predecessor is op1
        ]);

        $uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'type' => 'reference',
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'routing_id' => $routing->id,
            'bom_number' => 'BOM-TURB-01',
            'bom_name' => 'Turbine BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1.0,
            'base_uom_id' => $uom->id,
            'version' => '1.0.0',
            'status' => 'approved',
            'effective_date' => date('Y-m-d'),
        ]);

        // 2. Create Direct Order
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'parallel-routing')
            ->post(route('production.orders.store'), [
                'product_id' => $this->product->id,
                'quantity_ordered' => 2.0,
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+3 days')),
            ]);

        $order = ProductionOrder::where('tenant_id', $this->tenant->id)->latest('id')->first();
        $this->assertNotNull($order);

        $orderOps = $order->operations()->orderBy('sequence')->get();
        $this->assertCount(3, $orderOps);

        $orderOp1 = $orderOps->firstWhere('sequence', 10);
        $orderOp2 = $orderOps->firstWhere('sequence', 20);
        $orderOp3 = $orderOps->firstWhere('sequence', 30);

        // Op 1 has no predecessor
        $this->assertNull($orderOp1->previous_operation_id);

        // Op 2 is independent -> previous_operation_id is null
        $this->assertNull($orderOp2->previous_operation_id);

        // Op 3 has previous_operation_id pointing to OrderOp1!
        $this->assertEquals($orderOp1->id, $orderOp3->previous_operation_id);

        // Verify ProductionOrderOperationDependency created for Op 3 -> Op 1
        $dependency = ProductionOrderOperationDependency::where('operation_id', $orderOp3->id)->first();
        $this->assertNotNull($dependency);
        $this->assertEquals($orderOp1->id, $dependency->predecessor_operation_id);
        $this->assertEquals('FS', $dependency->dependency_type);
    }
}
