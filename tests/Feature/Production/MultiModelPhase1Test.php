<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BomExplosionService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MultiModelPhase1Test extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $tenantId = 1;
    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::factory()->create([
            'id'   => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->user = User::factory()->create(['tenant_id' => $this->tenantId]);
        $this->actingAs($this->user);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Piece',
            'code' => 'PCS',
            'status' => 'active',
        ]);
    }

    public function test_product_default_production_model_assignment(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Hybrid Pump',
            'sku' => 'PUMP-HYB-01',
            'type' => 'finished_good',
            'planning_type' => 'manufacture',
            'default_production_model' => Product::MODEL_HYBRID,
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->assertEquals('hybrid', $product->default_production_model);
        $this->assertEquals(Product::MODEL_HYBRID, $product->default_production_model);
    }

    public function test_production_order_snapshots_production_model_from_product(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Subcontract Assembly',
            'sku' => 'SUB-ASSY-01',
            'type' => 'finished_good',
            'planning_type' => 'manufacture',
            'default_production_model' => Product::MODEL_SUBCONTRACT_COMPANY_MATERIAL,
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'bom_number' => 'BOM-SUB-01',
            'bom_name' => 'Subcontract BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $product->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => 1,
            'status' => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RT-SUB-01',
            'name' => 'Subcontract Routing',
            'product_id' => $product->id,
            'status' => 'active',
        ]);

        $service = app(ProductionOrderService::class);
        $order = $service->createDirect([
            'product_id' => $product->id,
            'quantity_ordered' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ], $this->tenantId, $this->user->id);

        $this->assertEquals('subcontract_company_material', $order->production_model);
        $this->assertTrue($order->isSubcontractCompanyMaterial());
        $this->assertFalse($order->isPureManufacturing());
    }

    public function test_engineering_bom_cannot_be_selected_for_production_orders(): void
    {
        $product = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Prototype Engine',
            'sku' => 'ENG-PROTO-01',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $ebom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'bom_number' => 'EBOM-ENG-01',
            'bom_name' => 'Engineering Prototype BOM',
            'bom_type' => 'engineering',
            'product_id' => $product->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => 1,
            'status' => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RT-ENG-01',
            'name' => 'Engine Routing',
            'product_id' => $product->id,
            'status' => 'active',
        ]);

        $service = app(ProductionOrderService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Engineering BOMs cannot be selected');

        $service->createDirect([
            'product_id' => $product->id,
            'bom_id' => $ebom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 5,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ], $this->tenantId, $this->user->id);
    }

    public function test_phantom_bom_blow_through_in_explosion(): void
    {
        // 1. Root Product: Finished Bicycle
        $bike = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Pro Bicycle',
            'sku' => 'BIKE-01',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        // 2. Intermediate Component: Wheel Assembly (PHANTOM BOM)
        $wheelAssembly = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Wheel Kit (Phantom)',
            'sku' => 'KIT-WHEEL-PHANTOM',
            'type' => 'semi_finished',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        // 3. Raw Materials: Rim & Tire
        $rim = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Aluminum Rim',
            'sku' => 'RAW-RIM-01',
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $tire = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Rubber Tire',
            'sku' => 'RAW-TIRE-01',
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        // Phantom BOM for Wheel Kit
        $phantomBom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'bom_number' => 'BOM-PHANTOM-WHEEL',
            'bom_name' => 'Wheel Phantom BOM',
            'bom_type' => 'phantom',
            'product_id' => $wheelAssembly->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => 1,
            'status' => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantId,
            'bom_id' => $phantomBom->id,
            'material_id' => $rim->id,
            'quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantId,
            'bom_id' => $phantomBom->id,
            'material_id' => $tire->id,
            'quantity' => 1.0,
            'uom_id' => $this->uom->id,
        ]);

        // Main Bike BOM (uses 2 Wheel Assemblies)
        $bikeBom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'bom_number' => 'BOM-BIKE-01',
            'bom_name' => 'Bike Main BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $bike->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => 1,
            'status' => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantId,
            'bom_id' => $bikeBom->id,
            'material_id' => $wheelAssembly->id,
            'child_bom_id' => $phantomBom->id,
            'quantity' => 2.0,
            'uom_id' => $this->uom->id,
        ]);

        // Run Explosion
        $explosionService = app(BomExplosionService::class);
        $result = $explosionService->explode($bike->id, 10.0, $this->tenantId);

        $flatReqs = collect($result['flat']);

        // Assert Phantom item itself is NOT in flat requirements
        $this->assertNull($flatReqs->firstWhere('product_id', $wheelAssembly->id));

        // Assert child components Rim & Tire are present with blown-through quantities (2 * 10 = 20 PCS)
        $rimReq = $flatReqs->firstWhere('product_id', $rim->id);
        $tireReq = $flatReqs->firstWhere('product_id', $tire->id);

        $this->assertNotNull($rimReq);
        $this->assertNotNull($tireReq);
        $this->assertEquals(20.0, $rimReq['net_quantity']);
        $this->assertEquals(20.0, $tireReq['net_quantity']);
    }

    public function test_purchase_order_and_grn_production_references(): void
    {
        $vendor = Vendor::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Heat Treat Co',
            'code' => 'V-HEAT-01',
            'status' => 'active',
        ]);

        $subcontractWh = Warehouse::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Heat Treat Co Location',
            'code' => 'WH-V-HEAT',
            'type' => Warehouse::TYPE_SUBCONTRACTOR,
            'vendor_id' => $vendor->id,
            'status' => 'active',
        ]);

        $serviceProduct = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Heat Treatment Fee',
            'sku' => 'SVC-HEAT-TREAT',
            'type' => 'component',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $fg = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Shaft Hardened',
            'sku' => 'SHAFT-01',
            'type' => 'finished_good',
            'default_production_model' => Product::MODEL_HYBRID,
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $bom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'bom_number' => 'BOM-SHAFT-01',
            'bom_name' => 'Shaft BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $fg->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => 1,
            'status' => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        $routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'routing_number' => 'RT-SHAFT-01',
            'name' => 'Shaft Hybrid Routing',
            'product_id' => $fg->id,
            'status' => 'active',
        ]);

        $wc = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'code' => 'WC-CUT-01',
            'name' => 'Cutting Station',
            'status' => 'active',
            'capacity_per_hour' => 10,
        ]);

        $op1 = RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'work_center_id' => $wc->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Cutting',
            'operation_type' => 'manufacturing',
            'is_external' => false,
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 5,
        ]);

        $op2 = RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $routing->id,
            'work_center_id' => $wc->id,
            'sequence' => 20,
            'operation_number' => 'OP20',
            'name' => 'Heat Treatment (Subcontract)',
            'operation_type' => 'outsourcing',
            'is_external' => true,
            'vendor_id' => $vendor->id,
            'subcontract_lead_time_days' => 4,
            'subcontract_cost_per_unit' => 12.50,
            'subcontract_service_product_id' => $serviceProduct->id,
            'setup_time_minutes' => 0,
            'processing_time_minutes' => 0,
        ]);

        $service = app(ProductionOrderService::class);
        $order = $service->createDirect([
            'product_id' => $fg->id,
            'bom_id' => $bom->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 100,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
        ], $this->tenantId, $this->user->id);

        $op2Order = $order->operations->firstWhere('sequence', 20);
        $this->assertNotNull($op2Order);
        $this->assertTrue($op2Order->isOutsourced());
        $this->assertEquals(12.50, $op2Order->subcontract_cost_per_unit);

        // Create PO linked to OP2
        $po = PurchaseOrder::create([
            'tenant_id' => $this->tenantId,
            'purchase_order_number' => 'PO-SUB-1001',
            'vendor_id' => $vendor->id,
            'is_subcontract' => true,
            'production_order_id' => $order->id,
            'date' => now()->toDateString(),
            'status' => 'Approved',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $serviceProduct->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op2Order->id,
            'quantity' => 100,
            'rate' => 12.50,
            'amount' => 1250.00,
            'total_amount' => 1250.00,
        ]);

        // Create GRN linked to PO Item & Production Order
        $grn = GoodsReceiptNote::create([
            'tenant_id' => $this->tenantId,
            'grn_number' => 'GRN-SUB-1001',
            'purchase_order_id' => $po->id,
            'production_order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'warehouse_id' => $subcontractWh->id,
            'received_date' => now()->toDateString(),
            'status' => 'Approved',
        ]);

        $grnItem = GoodsReceiptNoteItem::create([
            'tenant_id' => $this->tenantId,
            'goods_receipt_note_id' => $grn->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $serviceProduct->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $op2Order->id,
            'ordered_qty' => 100,
            'received_qty' => 100,
            'accepted_qty' => 100,
            'unit_rate' => 12.50,
            'total_amount' => 1250.00,
        ]);

        $this->assertEquals($order->id, $po->production_order_id);
        $this->assertEquals($op2Order->id, $poItem->production_order_operation_id);
        $this->assertEquals($order->id, $grn->production_order_id);
        $this->assertEquals($op2Order->id, $grnItem->production_order_operation_id);
    }
}
