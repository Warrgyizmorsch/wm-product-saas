<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionOrderCompletionValidator;
use App\Domains\Production\Services\ProductionOrderService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductionMultiLevelExecutionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private WorkCenter $workCenter;
    private ProductionOrderService $orderService;
    private MesExecutionService $mesService;
    private ProductionExecutionService $executionService;
    private ProductionOrderCompletionValidator $completionValidator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'MultiLevel Exec Tenant', 'slug' => 'ml-exec-tenant']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);

        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS', 'unit_type' => 'unit']);
        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main Center',
            'code' => 'WC-MAIN',
            'status' => 'active',
            'daily_capacity_hours' => 8.0,
            'efficiency_percentage' => 100.0,
        ]);

        $this->orderService = $this->app->make(ProductionOrderService::class);
        $this->mesService = $this->app->make(MesExecutionService::class);
        $this->executionService = $this->app->make(ProductionExecutionService::class);
        $this->completionValidator = $this->app->make(ProductionOrderCompletionValidator::class);
    }

    public function test_operation_readiness_and_batch_claiming(): void
    {
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Gearbox Complete', 'sku' => 'GB-100']);
        $sfg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Gear Shaft', 'sku' => 'SHAFT-100', 'type' => 'semi_finished']);
        $rm = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Bar', 'sku' => 'STEEL-BAR', 'type' => 'raw_material']);

        // FG BOM & Routing (Requires 2 Shafts per Gearbox)
        $fgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'bom_number' => 'BOM-GB', 'bom_name' => 'GB BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $fgBom->id, 'material_id' => $sfg->id, 'quantity' => 2.0, 'uom_id' => $this->uom->id]);

        $fgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'name' => 'GB Routing', 'code' => 'RT-GB', 'status' => 'active']);
        $fgOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $fgRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'FG-10', 'name' => 'Housing Assembly', 'setup_time_minutes' => 10, 'processing_time_minutes' => 20]);

        RoutingOperationMaterial::create(['tenant_id' => $this->tenant->id, 'routing_operation_id' => $fgOp10->id, 'material_id' => $sfg->id, 'quantity' => 2.0, 'uom_id' => $this->uom->id]);

        // SFG BOM & Routing
        $sfgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'bom_number' => 'BOM-SHAFT', 'bom_name' => 'Shaft BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $sfgBom->id, 'material_id' => $rm->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        $sfgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'name' => 'Shaft Routing', 'code' => 'RT-SHAFT', 'status' => 'active']);
        $sfgOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $sfgRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'SFG-10', 'name' => 'Shaft Machining', 'setup_time_minutes' => 15, 'processing_time_minutes' => 30]);

        $order = $this->orderService->createDirect([
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenant->id);

        $sfgOp = $order->operations->firstWhere('operation_number', 'SFG-10');
        $fgOp = $order->operations->firstWhere('operation_number', 'FG-10');

        // Log progress on SFG operation: produced 10 Shafts
        $this->executionService->logProgress(
            $sfgOp->id,
            10.0, // produced
            0.0,  // rejected
            0.0,  // scrapped
            15.0,
            30.0,
            'Machined 10 shafts',
            null,
            $this->user->id
        );

        // 10 Shafts produced / 2 per Gearbox = Max 5 Gearboxes executable
        $readiness = $this->mesService->calculateOperationReadiness($fgOp->fresh());
        $this->assertEquals(5.0, $readiness['executable_qty']);
        $this->assertEquals(5.0, $readiness['remaining_executable_qty']);

        // Claiming 4 units succeeds
        $claimed = $this->mesService->claimBatchToExecute($fgOp->id, 4.0);
        $this->assertEquals(4.0, $claimed);

        // Remaining executable is now 1 unit
        $readiness2 = $this->mesService->calculateOperationReadiness($fgOp->fresh());
        $this->assertEquals(1.0, $readiness2['remaining_executable_qty']);

        // Claiming 2 units fails due to capacity constraint
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot claim 2 units');
        $this->mesService->claimBatchToExecute($fgOp->id, 2.0);
    }

    public function test_logging_parent_progress_records_sfg_consumed_wip_transaction_with_zero_cost_added(): void
    {
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Pump Complete', 'sku' => 'PUMP-100']);
        $sfg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Impeller Sub', 'sku' => 'IMP-100', 'type' => 'semi_finished']);
        $rm = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Brass Plate', 'sku' => 'BRASS-PLATE', 'type' => 'raw_material']);

        // FG BOM & Routing
        $fgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'bom_number' => 'BOM-PUMP', 'bom_name' => 'Pump BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $fgBom->id, 'material_id' => $sfg->id, 'quantity' => 3.0, 'uom_id' => $this->uom->id]);

        $fgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'name' => 'Pump Routing', 'code' => 'RT-PUMP', 'status' => 'active']);
        $fgOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $fgRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'FG-10', 'name' => 'Final Assembly', 'setup_time_minutes' => 10, 'processing_time_minutes' => 20]);

        RoutingOperationMaterial::create(['tenant_id' => $this->tenant->id, 'routing_operation_id' => $fgOp10->id, 'material_id' => $sfg->id, 'quantity' => 3.0, 'uom_id' => $this->uom->id]);

        // SFG BOM & Routing
        $sfgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'bom_number' => 'BOM-IMP', 'bom_name' => 'Impeller BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $sfgBom->id, 'material_id' => $rm->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);

        $sfgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'name' => 'Impeller Routing', 'code' => 'RT-IMP', 'status' => 'active']);
        $sfgOp10 = RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $sfgRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'SFG-10', 'name' => 'Impeller Casting', 'setup_time_minutes' => 10, 'processing_time_minutes' => 20]);

        $order = $this->orderService->createDirect([
            'product_id' => $fg->id,
            'quantity_ordered' => 5,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenant->id);

        $sfgOp = $order->operations->firstWhere('operation_number', 'SFG-10');
        $fgOp = $order->operations->firstWhere('operation_number', 'FG-10');

        // Log progress on SFG operation: produced 15 Impellers
        $this->executionService->logProgress($sfgOp->id, 15.0, 0.0, 0.0, 10.0, 20.0, 'Produced impellers', null, $this->user->id);

        // Log progress on FG operation: produced 2 Pumps (requires 6 Impellers)
        $this->executionService->logProgress($fgOp->id, 2.0, 0.0, 0.0, 10.0, 20.0, 'Assembled pumps', null, $this->user->id);

        // Verify SFG operation quantity_consumed is 6.0
        $this->assertEquals(6.0, (float) $sfgOp->fresh()->quantity_consumed);

        // Verify WIP transaction created for sfg_consumed with cost_added = 0.0000
        $tx = ProductionWipTransaction::where('tenant_id', $this->tenant->id)
            ->where('transaction_type', 'sfg_consumed')
            ->first();

        $this->assertNotNull($tx);
        $this->assertEquals(6.0, (float) $tx->quantity);
        $this->assertEquals(0.0000, (float) $tx->cost_added);
    }
}
