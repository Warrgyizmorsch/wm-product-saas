<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionOrderService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionMultiLevelSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Uom $uom;
    private WorkCenter $workCenter;
    private ProductionOrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'MultiLevel Snapshot Tenant', 'slug' => 'ml-snap-tenant']);
        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS', 'unit_type' => 'unit']);
        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Machining Center',
            'code' => 'WC-MACH',
            'status' => 'active',
            'daily_capacity_hours' => 8.0,
            'efficiency_percentage' => 100.0,
        ]);
        $this->orderService = $this->app->make(ProductionOrderService::class);
    }

    public function test_single_level_order_creates_only_fg_operations(): void
    {
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Simple FG', 'sku' => 'SIMPLE-FG']);
        $rm = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Raw Steel', 'sku' => 'RAW-STEEL', 'type' => 'raw_material']);

        $fgBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'bom_number' => 'BOM-SIMPLE',
            'bom_name' => 'Simple BOM',
            'status' => 'approved',
            'effective_date' => now(),
            'base_quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $fgBom->id,
            'material_id' => $rm->id,
            'quantity' => 5.0,
            'uom_id' => $this->uom->id,
        ]);

        $fgRouting = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'name' => 'Simple Routing',
            'code' => 'RT-SIMPLE',
            'status' => 'active',
        ]);
        RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $fgRouting->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'FG Assembly',
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 20,
        ]);

        $order = $this->orderService->createDirect([
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenant->id);

        $ops = $order->operations;
        $this->assertCount(1, $ops);
        $this->assertFalse((bool) $ops->first()->is_intermediate);
        $this->assertEquals(1, $ops->first()->bom_level);
    }

    public function test_multi_level_bom_snapshots_sfg_routing_and_cross_assembly_dependency(): void
    {
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Heavy Crusher', 'sku' => 'CRUSHER-100']);
        $sfg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Crusher Frame', 'sku' => 'FRAME-100', 'type' => 'semi_finished']);
        $rm = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Plate', 'sku' => 'STEEL-PLATE', 'type' => 'raw_material']);

        // FG BOM & Routing
        $fgBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'bom_number' => 'BOM-CRUSHER',
            'bom_name' => 'Crusher BOM',
            'status' => 'approved',
            'effective_date' => now(),
            'base_quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $fgBom->id,
            'material_id' => $sfg->id,
            'quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);

        $fgRouting = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'name' => 'Crusher FG Routing',
            'code' => 'RT-CRUSHER',
            'status' => 'active',
        ]);
        $fgOp10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $fgRouting->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 10,
            'operation_number' => 'FG-OP10',
            'name' => 'Preparation',
            'setup_time_minutes' => 5,
            'processing_time_minutes' => 10,
        ]);
        $fgOp20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $fgRouting->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 20,
            'operation_number' => 'FG-OP20',
            'name' => 'Main Assembly',
            'setup_time_minutes' => 15,
            'processing_time_minutes' => 30,
        ]);

        // Specific Operation-Material Mapping: Frame is consumed at FG-OP20!
        RoutingOperationMaterial::create([
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $fgOp20->id,
            'material_id' => $sfg->id,
            'quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);

        // SFG BOM & Routing
        $sfgBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $sfg->id,
            'bom_number' => 'BOM-FRAME',
            'bom_name' => 'Frame BOM',
            'status' => 'approved',
            'effective_date' => now(),
            'base_quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $sfgBom->id,
            'material_id' => $rm->id,
            'quantity' => 2.0,
            'uom_id' => $this->uom->id,
        ]);

        $sfgRouting = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $sfg->id,
            'name' => 'Frame SFG Routing',
            'code' => 'RT-FRAME',
            'status' => 'active',
        ]);
        $sfgOp10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $sfgRouting->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 10,
            'operation_number' => 'SFG-OP10',
            'name' => 'Cutting',
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 15,
        ]);
        $sfgOp20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $sfgRouting->id,
            'work_center_id' => $this->workCenter->id,
            'sequence' => 20,
            'operation_number' => 'SFG-OP20',
            'name' => 'Welding',
            'setup_time_minutes' => 20,
            'processing_time_minutes' => 25,
        ]);

        $order = $this->orderService->createDirect([
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ], $this->tenant->id);

        $ops = $order->operations;
        // Total 4 operations (2 FG + 2 SFG)
        $this->assertCount(4, $ops);

        $sfgOps = $ops->where('is_intermediate', true);
        $this->assertCount(2, $sfgOps);
        $this->assertEquals(2, $sfgOps->first()->bom_level);

        $fgOps = $ops->where('is_intermediate', false);
        $this->assertCount(2, $fgOps);

        // Verify specific consuming parent operation mapping (FG-OP20)
        $sfgFinalOp = $ops->where('operation_number', 'SFG-OP20')->first();
        $fgConsumingOp = $ops->where('operation_number', 'FG-OP20')->first();

        $this->assertTrue($fgConsumingOp->predecessorDependencies->contains($sfgFinalOp->id));
    }

    public function test_partial_warehouse_stock_reduces_sfg_target_manufacturing_qty(): void
    {
        $fg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Bearing Machine', 'sku' => 'MACH-BEARING']);
        $sfg = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Bearing Assembly', 'sku' => 'BEARING-ASSY', 'type' => 'semi_finished']);

        $warehouse = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Main Warehouse', 'code' => 'WH-MAIN', 'status' => 'active']);
        // Reserve 6 units in warehouse stock for SFG
        ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $sfg->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10.0,
            'reserved_qty' => 6.0,
        ]);

        // FG BOM & Routing (Requires 20 Bearings for 10 Machines)
        $fgBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $fg->id,
            'bom_number' => 'BOM-BEARING-MACH',
            'bom_name' => 'Bearing Machine BOM',
            'status' => 'approved',
            'effective_date' => now(),
            'base_quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);
        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $fgBom->id,
            'material_id' => $sfg->id,
            'quantity' => 2.0, // 10 FG -> 20 SFG
            'uom_id' => $this->uom->id,
        ]);

        $fgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $fg->id, 'name' => 'FG Routing', 'code' => 'R-FG', 'status' => 'active']);
        RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $fgRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'FG-10', 'name' => 'Assembly', 'setup_time_minutes' => 5, 'processing_time_minutes' => 10]);

        // SFG BOM & Routing
        $sfgBom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'bom_number' => 'BOM-BEARING-SUB', 'bom_name' => 'Bearing Sub BOM', 'status' => 'approved', 'effective_date' => now(), 'base_quantity' => 1.0, 'uom_id' => $this->uom->id]);
        $rm = Product::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Rod', 'sku' => 'STEEL-ROD', 'type' => 'raw_material']);
        ProductionBomItem::create(['tenant_id' => $this->tenant->id, 'bom_id' => $sfgBom->id, 'material_id' => $rm->id, 'quantity' => 1.0, 'uom_id' => $this->uom->id]);
        $sfgRouting = Routing::create(['tenant_id' => $this->tenant->id, 'product_id' => $sfg->id, 'name' => 'SFG Routing', 'code' => 'R-SFG', 'status' => 'active']);
        RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $sfgRouting->id, 'work_center_id' => $this->workCenter->id, 'sequence' => 10, 'operation_number' => 'SFG-10', 'name' => 'Bearing Machining', 'setup_time_minutes' => 10, 'processing_time_minutes' => 5]);

        $order = $this->orderService->createDirect([
            'product_id' => $fg->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenant->id);

        $sfgOp = $order->operations->where('is_intermediate', true)->first();
        $this->assertNotNull($sfgOp);
        // Required = 20, Warehouse = 6 => Manufacturing Target = 14
        $this->assertEquals(14.0, (float) $sfgOp->target_produced_qty);
    }
}
