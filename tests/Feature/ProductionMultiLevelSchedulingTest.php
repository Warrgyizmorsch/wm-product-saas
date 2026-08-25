<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderOperationDependency;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionMultiLevelSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private WorkCenter $workCenter1;
    private WorkCenter $workCenter2;
    private ProductionOrderService $orderService;
    private SchedulingService $schedulingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'MultiLevel Sched Tenant', 'slug' => 'ml-sched-tenant']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);

        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS', 'unit_type' => 'unit']);

        $this->workCenter1 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Machining Center 1',
            'code' => 'WC-M1',
            'status' => 'active',
            'daily_capacity_hours' => 8.0,
            'efficiency_percentage' => 100.0,
        ]);
        $this->workCenter2 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Center 2',
            'code' => 'WC-A2',
            'status' => 'active',
            'daily_capacity_hours' => 8.0,
            'efficiency_percentage' => 100.0,
        ]);

        $this->orderService = $this->app->make(ProductionOrderService::class);
        $this->schedulingService = $this->app->make(SchedulingService::class);
    }

    public function test_forward_scheduling_respects_cross_assembly_predecessor_dependency(): void
    {
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Engine Complete', 'sku' => 'ENG-100']);
        $sfg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Piston Subassembly', 'sku' => 'PISTON-SUB', 'type' => 'semi_finished']);
        $rm = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Raw Cast Iron', 'sku' => 'CAST-IRON', 'type' => 'raw_material']);

        // FG BOM & Routing
        $fgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'bom_number' => 'BOM-ENG', 'bom_name' => 'Engine BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $fgBom->id, 'material_id' => $sfg->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        $fgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'name' => 'Engine Routing', 'code' => 'RT-ENG', 'status' => 'active']);
        $fgOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $fgRouting->id, 'work_center_id' => $this->workCenter1->id, 'sequence' => 10, 'operation_number' => 'FG-10', 'name' => 'Block Prep', 'setup_time_minutes' => 10, 'processing_time_minutes' => 20]);
        $fgOp20 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $fgRouting->id, 'work_center_id' => $this->workCenter2->id, 'sequence' => 20, 'operation_number' => 'FG-20', 'name' => 'Piston Install', 'setup_time_minutes' => 15, 'processing_time_minutes' => 30]);

        RoutingOperationMaterial::create(['tenant_id' => $this->tenant->id, 'routing_operation_id' => $fgOp20->id, 'material_id' => $sfg->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        // SFG BOM & Routing
        $sfgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'bom_number' => 'BOM-PISTON', 'bom_name' => 'Piston BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $sfgBom->id, 'material_id' => $rm->id, 'quantity' => 4.0, 'uom_id' => $this->uom->id]);

        $sfgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'name' => 'Piston Routing', 'code' => 'RT-PISTON', 'status' => 'active']);
        $sfgOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $sfgRouting->id, 'work_center_id' => $this->workCenter1->id, 'sequence' => 10, 'operation_number' => 'SFG-10', 'name' => 'Machining', 'setup_time_minutes' => 20, 'processing_time_minutes' => 40]);

        $order = $this->orderService->createDirect([
            'product_id' => $fg->id,
            'quantity_ordered' => 1,
            'start_date' => now()->startOfDay()->toDateTimeString(),
            'end_date' => now()->addDays(2)->toDateTimeString(),
        ], $this->tenant->id);

        $startDate = Carbon::parse(now()->startOfDay()->toDateTimeString());
        $schedule = $this->schedulingService->generateForwardSchedule($order, $startDate);

        $this->assertNotNull($schedule);
        $schedOps = $schedule->operations;
        $this->assertCount(3, $schedOps);

        $sfgSchedOp = $schedOps->firstWhere('orderOperation.operation_number', 'SFG-10');
        $fgSchedOp20 = $schedOps->firstWhere('orderOperation.operation_number', 'FG-20');

        $this->assertNotNull($sfgSchedOp);
        $this->assertNotNull($fgSchedOp20);

        // SFG-10 finish MUST be <= FG-20 start
        $this->assertTrue(
            Carbon::parse($sfgSchedOp->planned_finish)->lte(Carbon::parse($fgSchedOp20->planned_start)),
            "SFG final operation finish time ({$sfgSchedOp->planned_finish}) must be <= consuming FG OP20 start time ({$fgSchedOp20->planned_start})."
        );
    }

    public function test_circular_dependency_throws_logic_exception(): void
    {
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Circular FG', 'sku' => 'CIRC-FG']);

        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'PO-CIRC',
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'status' => 'draft',
            'start_date' => now(),
            'end_date' => now()->addDays(2),
            'due_date' => now()->addDays(2),
        ]);

        $op1 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Op 1',
            'work_center_id' => $this->workCenter1->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
        ]);

        $op2 = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Op 2',
            'work_center_id' => $this->workCenter2->id,
            'status' => ProductionOrderOperation::STATUS_WAITING,
            'previous_operation_id' => $op1->id,
        ]);

        // Create cycle: OP1 depends on OP2
        ProductionOrderOperationDependency::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $op1->id,
            'predecessor_operation_id' => $op2->id,
            'dependency_type' => 'cross_assembly',
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->schedulingService->generateForwardSchedule($order, Carbon::parse(now()->toDateTimeString()));
    }
}
