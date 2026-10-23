<?php

namespace Tests\Feature\Production;

use App\Core\Tenant\TenantContext;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderSpare;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionRequisitionSlipItem;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MaintenanceSpareService;
use App\Domains\Sales\Services\MaterialRequestService;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceMaterialRequestIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Product $spareProduct1;
    private Product $spareProduct2;
    private Product $rawMaterialProduct;
    private Warehouse $warehouse;
    private ProductionMaintenanceWorkOrder $workOrder;
    private int $uomId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelBack();
        Carbon::setTestNow(null);
        app(TenantContext::class)->clear();

        $this->tenant = Tenant::create([
            'name'   => 'Test MWO Requisition Tenant',
            'slug'   => 'test-mwo-req-' . uniqid(),
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Store Maintenance Admin',
            'email'     => 'store_maint_' . uniqid() . '@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->actingAs($this->user);
        app(TenantContext::class)->setTenant($this->tenant);
        app(Tenancy::class)->setTenant($this->tenant);
        app()->instance('tenant', $this->tenant);

        $this->workCenter = WorkCenter::create([
            'tenant_id'             => $this->tenant->id,
            'name'                  => 'Main Milling Center',
            'code'                  => 'WC-MILL-01',
            'cost_per_hour'          => 60.00,
            'capacity_per_hour'      => 8.00,
            'efficiency_percentage'  => 100.00,
            'status'                => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'          => $this->tenant->id,
            'work_center_id'     => $this->workCenter->id,
            'name'               => '5-Axis CNC Router',
            'code'               => 'MCH-CNC-05',
            'status'             => Machine::STATUS_ACTIVE,
            'current_state'      => 'Active',
            'maintenance_status' => 'none',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Central Store',
            'code'       => 'WH-CENTRAL-01',
            'is_default' => true,
        ]);

        $this->uomId = \Illuminate\Support\Facades\DB::table('uoms')->insertGetId([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'Piece',
            'code'       => 'PCS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->spareProduct1 = Product::create([
            'tenant_id'                   => $this->tenant->id,
            'uom_id'                      => $this->uomId,
            'name'                        => 'Linear Guide Rail Carriage',
            'sku'                         => 'SPR-LGR-101',
            'cost_price'                  => 75.00,
            'inventory_valuation_method' => 'FIFO',
        ]);

        $this->spareProduct2 = Product::create([
            'tenant_id'                   => $this->tenant->id,
            'uom_id'                      => $this->uomId,
            'name'                        => 'High Pressure Hydraulic Seal',
            'sku'                         => 'SPR-SEAL-202',
            'cost_price'                  => 30.00,
            'inventory_valuation_method' => 'FIFO',
        ]);

        $this->rawMaterialProduct = Product::create([
            'tenant_id'                   => $this->tenant->id,
            'uom_id'                      => $this->uomId,
            'name'                        => 'Aluminum Billet 6061',
            'sku'                         => 'RAW-ALU-6061',
            'cost_price'                  => 50.00,
            'inventory_valuation_method' => 'FIFO',
        ]);

        // Seed stock for spares and raw materials
        StockService::recordInflow(
            $this->tenant->id,
            $this->spareProduct1->id,
            $this->warehouse->id,
            10.00,
            75.00,
            'OpeningStock',
            1
        );

        StockService::recordInflow(
            $this->tenant->id,
            $this->spareProduct2->id,
            $this->warehouse->id,
            20.00,
            30.00,
            'OpeningStock',
            2
        );

        StockService::recordInflow(
            $this->tenant->id,
            $this->rawMaterialProduct->id,
            $this->warehouse->id,
            50.00,
            50.00,
            'OpeningStock',
            3
        );

        $this->workOrder = ProductionMaintenanceWorkOrder::create([
            'tenant_id'           => $this->tenant->id,
            'work_order_number'   => 'MWO-2026-00001',
            'machine_id'          => $this->machine->id,
            'type'                => 'breakdown',
            'priority'            => 'high',
            'status'              => 'in_progress',
            'problem_description' => 'Carriage play and hydraulic seal leak detected',
            'mechanic_cost'       => 150.00,
            'additional_cost'     => 25.00,
            'spare_parts_cost'    => 0.00,
            'total_cost'          => 175.00,
        ]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        Carbon::setTestNow(null);
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_store_material_request_slip_when_spare_is_requested_on_mwo(): void
    {
        $spareService = app(MaintenanceSpareService::class);

        $spare = $spareService->addSpareRequest(
            $this->workOrder->id,
            $this->tenant->id,
            $this->spareProduct1->id,
            null, // Warehouse is not selected during maintenance request; managed by store
            2.0
        );

        $this->assertInstanceOf(ProductionMaintenanceWorkOrderSpare::class, $spare);
        $this->assertNull($spare->warehouse_id);
        $this->assertNotNull($spare->production_requisition_slip_id);
        $this->assertNotNull($spare->production_requisition_slip_item_id);

        $slip = ProductionRequisitionSlip::find($spare->production_requisition_slip_id);
        $this->assertNotNull($slip);
        $this->assertEquals(ProductionRequisitionSlip::SOURCE_TYPE_MAINTENANCE_WORK_ORDER, $slip->source_type);
        $this->assertEquals($this->workOrder->id, $slip->maintenance_work_order_id);
        $this->assertNull($slip->production_order_id);
        $this->assertTrue($slip->isMaintenance());

        $item = ProductionRequisitionSlipItem::find($spare->production_requisition_slip_item_id);
        $this->assertNotNull($item);
        $this->assertNull($item->warehouse_id);
        $this->assertEquals($this->spareProduct1->id, $item->product_id);
        $this->assertEquals(2.0, (float)$item->quantity_planned);
        $this->assertEquals(0.0, (float)$item->quantity_issued);

        // Verify relationships
        $this->assertEquals($slip->id, $spare->requisitionSlip->id);
        $this->assertEquals($item->id, $spare->requisitionSlipItem->id);
        $this->assertTrue($this->workOrder->requisitionSlips->contains($slip));
    }

    /** @test */
    public function it_appends_subsequent_spare_requests_to_the_open_maintenance_slip(): void
    {
        $spareService = app(MaintenanceSpareService::class);

        $spare1 = $spareService->addSpareRequest(
            $this->workOrder->id,
            $this->tenant->id,
            $this->spareProduct1->id,
            $this->warehouse->id,
            1.0
        );

        $spare2 = $spareService->addSpareRequest(
            $this->workOrder->id,
            $this->tenant->id,
            $this->spareProduct2->id,
            $this->warehouse->id,
            3.0
        );

        // Both should reuse the same slip
        $this->assertEquals($spare1->production_requisition_slip_id, $spare2->production_requisition_slip_id);

        $slip = ProductionRequisitionSlip::find($spare1->production_requisition_slip_id);
        $this->assertCount(2, $slip->items);
        $this->assertEquals(1, ProductionRequisitionSlip::where('maintenance_work_order_id', $this->workOrder->id)->count());
    }

    /** @test */
    public function it_reserves_stock_for_maintenance_without_creating_production_order_reservations(): void
    {
        $spareService = app(MaintenanceSpareService::class);
        $mrService = app(MaterialRequestService::class);

        $spare = $spareService->addSpareRequest(
            $this->workOrder->id,
            $this->tenant->id,
            $this->spareProduct1->id,
            $this->warehouse->id,
            2.0
        );

        $reserved = $mrService->reserve(
            $this->tenant->id,
            $spare->production_requisition_slip_item_id,
            2.0,
            $this->warehouse->id
        );

        $this->assertEquals(2.0, $reserved);

        // Verify NO ProductionOrderReservation records were created
        $this->assertEquals(0, ProductionOrderReservation::count());

        // Verify slip item reserved quantity is updated
        $item = ProductionRequisitionSlipItem::find($spare->production_requisition_slip_item_id);
        $this->assertEquals(2.0, (float)$item->quantity_reserved);
    }

    /** @test */
    public function it_issues_stock_from_store_syncs_spare_valuation_and_recalculates_mwo_cost(): void
    {
        $spareService = app(MaintenanceSpareService::class);
        $mrService = app(MaterialRequestService::class);

        $spare = $spareService->addSpareRequest(
            $this->workOrder->id,
            $this->tenant->id,
            $this->spareProduct1->id,
            null, // No warehouse selected on maintenance request
            2.0
        );

        $this->assertNull($spare->warehouse_id);

        // Issue 2.0 units through Store Material Request
        $issued = $mrService->issue(
            $this->tenant->id,
            $spare->production_requisition_slip_item_id,
            2.0,
            $this->warehouse->id,
            'Issued directly to maintenance team'
        );

        $this->assertEquals(2.0, $issued);

        // Verify NO ProductionOrderIssue records were created
        $this->assertEquals(0, ProductionOrderIssue::count());

        // Verify stock deducted
        $stockLeft = StockService::getAvailableStock($this->spareProduct1->id, $this->warehouse->id);
        $this->assertEquals(8.0, $stockLeft); // 10 opening - 2 issued

        // Verify sync to ProductionMaintenanceWorkOrderSpare
        $spare->refresh();
        $this->assertEquals($this->warehouse->id, $spare->warehouse_id);
        $this->assertEquals(2.0, (float)$spare->issued_qty);
        $this->assertEquals(75.0, (float)$spare->unit_cost);
        $this->assertEquals(150.0, (float)$spare->total_cost);
        $this->assertNotNull($spare->stock_transaction_id);

        // Verify recalculation of Maintenance Work Order total cost
        $this->workOrder->refresh();
        $this->assertEquals(150.0, (float)$this->workOrder->spare_parts_cost);
        // mechanic_cost (150) + additional_cost (25) + spare_parts_cost (150) = 325
        $this->assertEquals(325.0, (float)$this->workOrder->total_cost);

        // Verify slip status is fully issued / completed
        $slip = ProductionRequisitionSlip::find($spare->production_requisition_slip_id);
        $this->assertEquals('Fully Issued', $slip->status);
    }

    /** @test */
    public function it_supports_partial_store_issues_and_updates_costs_incrementally(): void
    {
        $spareService = app(MaintenanceSpareService::class);
        $mrService = app(MaterialRequestService::class);

        $spare = $spareService->addSpareRequest(
            $this->workOrder->id,
            $this->tenant->id,
            $this->spareProduct2->id, // unit cost 30.00
            $this->warehouse->id,
            4.0
        );

        // Issue 1st partial batch (1 unit)
        $mrService->issue(
            $this->tenant->id,
            $spare->production_requisition_slip_item_id,
            1.0,
            $this->warehouse->id,
            'Partial issue 1 of 4'
        );

        $spare->refresh();
        $this->assertEquals(1.0, (float)$spare->issued_qty);
        $this->assertEquals(30.0, (float)$spare->total_cost);

        $this->workOrder->refresh();
        $this->assertEquals(30.0, (float)$this->workOrder->spare_parts_cost);
        $this->assertEquals(205.0, (float)$this->workOrder->total_cost); // 150 + 25 + 30

        $slip = ProductionRequisitionSlip::find($spare->production_requisition_slip_id);
        $this->assertEquals('Partially Issued', $slip->status);

        // Issue 2nd batch (remaining 3 units)
        $mrService->issue(
            $this->tenant->id,
            $spare->production_requisition_slip_item_id,
            3.0,
            $this->warehouse->id,
            'Remaining 3 units issued'
        );

        $spare->refresh();
        $this->assertEquals(4.0, (float)$spare->issued_qty);
        $this->assertEquals(120.0, (float)$spare->total_cost);

        $this->workOrder->refresh();
        $this->assertEquals(120.0, (float)$this->workOrder->spare_parts_cost);
        $this->assertEquals(295.0, (float)$this->workOrder->total_cost); // 150 + 25 + 120

        $slip->refresh();
        $this->assertEquals('Fully Issued', $slip->status);
    }

    /** @test */
    public function it_preserves_backward_compatibility_for_production_order_material_requests(): void
    {
        $mrService = app(MaterialRequestService::class);

        $prodOrder = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'MO-2026-TEST-01',
            'product_id'       => $this->rawMaterialProduct->id,
            'quantity_ordered' => 5.0,
            'status'           => 'released',
            'start_date'       => now()->toDateString(),
            'due_date'         => now()->addDays(5)->toDateString(),
            'end_date'         => now()->addDays(5)->toDateString(),
        ]);

        $slip = ProductionRequisitionSlip::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $prodOrder->id,
            'source_type'         => ProductionRequisitionSlip::SOURCE_TYPE_PRODUCTION_ORDER,
            'requisition_number'  => 'MR-PROD-0001',
            'requisition_date'    => now()->toDateString(),
            'status'              => 'pending',
        ]);

        $item = ProductionRequisitionSlipItem::create([
            'tenant_id'                      => $this->tenant->id,
            'production_requisition_slip_id' => $slip->id,
            'product_id'                     => $this->rawMaterialProduct->id,
            'warehouse_id'                   => $this->warehouse->id,
            'quantity_planned'               => 5.0,
            'quantity_reserved'              => 0.0,
            'quantity_issued'                => 0.0,
            'uom_id'                         => $this->uomId,
        ]);

        // 1. Reserve for Production Order
        $reserved = $mrService->reserve($this->tenant->id, $item->id, 5.0, $this->warehouse->id);
        $this->assertEquals(5.0, $reserved);

        // Production Order flow MUST create ProductionOrderReservation
        $this->assertDatabaseHas('production_order_reservations', [
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $prodOrder->id,
            'product_id'          => $this->rawMaterialProduct->id,
            'quantity_reserved'   => 5.0,
        ]);

        // 2. Issue for Production Order
        $issued = $mrService->issue($this->tenant->id, $item->id, 5.0, $this->warehouse->id, 'Issued to MO');
        $this->assertEquals(5.0, $issued);

        // Production Order flow MUST create ProductionOrderIssue
        $this->assertDatabaseHas('production_order_issues', [
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $prodOrder->id,
            'product_id'          => $this->rawMaterialProduct->id,
            'quantity_issued'     => 5.0,
        ]);
    }

    /** @test */
    public function it_submits_spare_part_request_via_http_endpoint_without_warehouse_id(): void
    {
        $response = $this->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('production.maintenance.work-orders.add-spare', $this->workOrder->id), [
                'product_id'    => $this->spareProduct1->id,
                'requested_qty' => 1.0,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_maintenance_work_order_spares', [
            'maintenance_work_order_id' => $this->workOrder->id,
            'product_id'                => $this->spareProduct1->id,
            'warehouse_id'              => null,
            'requested_qty'             => 1.0,
        ]);
    }
}
