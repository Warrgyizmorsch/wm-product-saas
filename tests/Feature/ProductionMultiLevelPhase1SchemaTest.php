<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderOperationDependency;
use App\Domains\Production\Models\Routing;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionMultiLevelPhase1SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_level_fields_exist_on_production_order_operations(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);
        $product = Product::create(['tenant_id' => $tenant->id, 'name' => 'FG Product', 'sku' => 'FG-001']);
        $bom = ProductionBom::create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'bom_number' => 'BOM-001', 'bom_name' => 'FG BOM', 'status' => 'approved', 'effective_date' => now()]);
        $routing = Routing::create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'name' => 'FG Routing', 'code' => 'R-001', 'status' => 'active']);

        $order = ProductionOrder::create([
            'tenant_id' => $tenant->id,
            'order_number' => 'PO-TEST-001',
            'product_id' => $product->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10,
            'start_date' => now(),
            'end_date' => now()->addDays(2),
            'status' => 'draft',
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id' => $tenant->id,
            'production_order_id' => $order->id,
            'source_product_id' => $product->id,
            'source_bom_id' => $bom->id,
            'source_routing_id' => $routing->id,
            'bom_level' => 2,
            'target_produced_qty' => 14.5,
            'is_intermediate' => true,
            'quantity_claimed' => 4.0,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'SFG Machining',
            'status' => 'ready',
        ]);

        $this->assertDatabaseHas('production_order_operations', [
            'id' => $op->id,
            'source_product_id' => $product->id,
            'source_bom_id' => $bom->id,
            'source_routing_id' => $routing->id,
            'bom_level' => 2,
            'target_produced_qty' => 14.5,
            'is_intermediate' => true,
            'quantity_claimed' => 4.0,
        ]);
    }

    public function test_cross_assembly_operation_dependencies_pivot(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant 2', 'slug' => 'test-tenant-2']);
        $product = Product::create(['tenant_id' => $tenant->id, 'name' => 'FG Product 2', 'sku' => 'FG-002']);

        $order = ProductionOrder::create([
            'tenant_id' => $tenant->id,
            'order_number' => 'PO-TEST-002',
            'product_id' => $product->id,
            'quantity_ordered' => 5,
            'start_date' => now(),
            'end_date' => now()->addDays(2),
            'status' => 'draft',
        ]);

        $sfgOp = ProductionOrderOperation::create([
            'tenant_id' => $tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 10,
            'operation_number' => 'SFG-OP10',
            'name' => 'SFG Final Weld',
            'status' => 'completed',
            'is_intermediate' => true,
        ]);

        $fgOp = ProductionOrderOperation::create([
            'tenant_id' => $tenant->id,
            'production_order_id' => $order->id,
            'sequence' => 20,
            'operation_number' => 'FG-OP10',
            'name' => 'FG Assembly',
            'status' => 'waiting',
            'is_intermediate' => false,
        ]);

        $dep = ProductionOrderOperationDependency::create([
            'tenant_id' => $tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $fgOp->id,
            'predecessor_operation_id' => $sfgOp->id,
            'dependency_type' => 'cross_assembly',
        ]);

        $this->assertDatabaseHas('production_order_operation_dependencies', [
            'id' => $dep->id,
            'operation_id' => $fgOp->id,
            'predecessor_operation_id' => $sfgOp->id,
        ]);

        $this->assertTrue($fgOp->predecessorDependencies->contains($sfgOp->id));
        $this->assertTrue($sfgOp->successorDependencies->contains($fgOp->id));
    }
}
