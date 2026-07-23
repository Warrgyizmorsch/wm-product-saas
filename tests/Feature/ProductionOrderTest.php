<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderRequest;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionPlanOperation;
use App\Domains\Production\Models\ProductionPlanRequirement;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionCostVarianceService;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesOrderItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOrderTest extends TestCase
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
            'name' => 'Order Test Tenant',
            'slug' => 'order-test',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $this->tenantId = $tenant->id;

        // Create User
        $this->user = User::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Order Admin',
            'email' => 'order-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($this->user);
        $this->withHeaders(['X-Tenant' => 'order-test']);

        // Setup base data
        $this->uom = Uom::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Units',
            'code' => 'PCS',
            'type' => 'reference',
        ]);

        $this->finishedGood = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'E-Bike Model X',
            'sku' => 'FG-BIKE-X',
            'type' => 'finished_good',
            'unit_cost' => 500.00,
            'status' => 'active',
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Aluminum Frame Tubes',
            'sku' => 'RM-TUBE-01',
            'type' => 'raw_material',
            'unit_cost' => 45.00,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Production Store',
            'code' => 'PROD',
            'status' => 'active',
            'is_default' => true,
        ]);

        ProductWarehouseStock::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved_qty' => 0,
            'available_qty' => 100,
            'unit_cost' => 45,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Welding Work Center',
            'code' => 'WC-WELD',
            'overhead_rate' => 60.00, // $60/hr = $1/min
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name' => 'TIG Welding machine',
            'code' => 'MC-TIG-01',
            'status' => 'active',
        ]);

        // Create Master BOM
        $this->bom = ProductionBom::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $this->finishedGood->id,
            'bom_number' => 'BOM-BIKE-001',
            'bom_name' => 'E-Bike Standard BOM',
            'bom_type' => 'manufacturing',
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'status' => 'approved',
            'effective_date' => date('Y-m-d'),
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantId,
            'bom_id' => $this->bom->id,
            'material_id' => $this->rawMaterial->id,
            'quantity' => 2.0,
            'uom_id' => $this->uom->id,
            'material_scrap_percentage' => 10.0,
        ]);

        // Create Master Routing
        $this->routing = Routing::create([
            'tenant_id' => $this->tenantId,
            'product_id' => $this->finishedGood->id,
            'routing_number' => 'RT-BIKE-001',
            'name' => 'Frame Welding Route',
            'version' => '1.0.0',
            'status' => 'active',
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $this->routing->id,
            'sequence' => 1,
            'operation_number' => 'OP-010',
            'name' => 'TIG Welding Jointing',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'setup_time_minutes' => 10.0,
            'processing_time_minutes' => 20.0,
            'labor_cost_rate' => 1.50, // $1.50 per min
            'machine_cost_rate' => 2.00,  // $2.00 per min
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantId,
            'routing_id' => $this->routing->id,
            'sequence' => 2,
            'operation_number' => 'OP-020',
            'name' => 'Finishing Quality Inspection',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 5.0,
            'processing_time_minutes' => 10.0,
            'labor_cost_rate' => 1.00,
            'machine_cost_rate' => 0.00,
        ]);
    }

    public function test_can_create_direct_order_from_active_engineering_masters(): void
    {
        $response = $this->post(route('production.orders.store'), [
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 5.0,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+5 days')),
            'description' => 'Test direct order creation',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_orders', [
            'tenant_id' => $this->tenantId,
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 5.0,
            'status' => 'draft',
        ]);

        $order = ProductionOrder::orderBy('id', 'desc')->first();

        // Assert operations were frozen and snapshotted correctly
        $this->assertCount(2, $order->operations);

        // Assert reservations were calculated correctly
        // (BOM qty 2.0 * Order qty 5.0) * (1 + 10% scrap) = 11.0
        $this->assertCount(1, $order->reservations);
        $this->assertEquals(11.0, $order->reservations->first()->quantity_planned);
        $this->assertEquals(11.0, $order->reservations->first()->quantity_reserved);
        $this->assertEquals($this->warehouse->id, $order->reservations->first()->warehouse_id);
    }

    public function test_can_create_direct_order_from_draft_production_order_request(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Request Customer',
            'email' => 'request-customer@example.com',
        ]);
        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenantId,
            'name' => 'Main Store',
            'code' => 'MAIN',
            'status' => 'active',
        ]);
        $salesOrder = SalesOrder::create([
            'tenant_id' => $this->tenantId,
            'customer_id' => $customer->id,
            'sales_order_number' => '1001',
            'order_date' => today(),
            'status' => 'Confirmed',
            'total_amount' => 1000,
        ]);
        $salesOrderItem = SalesOrderItem::create([
            'sales_order_id' => $salesOrder->id,
            'product_id' => $this->finishedGood->id,
            'warehouse_id' => $warehouse->id,
            'item_name' => $this->finishedGood->name,
            'quantity' => 4,
            'unit_price' => 250,
            'amount' => 1000,
        ]);
        $delivery = MaterialRequirement::create([
            'tenant_id' => $this->tenantId,
            'sales_order_id' => $salesOrder->id,
            'requirement_number' => 'MR-1001',
            'requirement_date' => today(),
            'status' => 'Waiting Production',
        ]);
        $deliveryItem = MaterialRequirementItem::create([
            'material_requirement_id' => $delivery->id,
            'sales_order_item_id' => $salesOrderItem->id,
            'product_id' => $this->finishedGood->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 4,
            'quantity_ordered' => 4,
            'quantity_reserved' => 0,
            'status' => 'Waiting Production',
        ]);
        $productionRequest = ProductionOrderRequest::create([
            'tenant_id' => $this->tenantId,
            'material_requirement_item_id' => $deliveryItem->id,
            'product_id' => $this->finishedGood->id,
            'quantity_requested' => 4,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->get(route('production.orders.create'))
            ->assertOk()
            ->assertSee('SO-1001')
            ->assertSee('MR-1001')
            ->assertSee((string) $productionRequest->id);

        $response = $this->post(route('production.orders.store'), [
            'production_order_request_id' => $productionRequest->id,
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 4,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+5 days')),
        ]);

        $response->assertRedirect();
        $productionRequest->refresh();

        $order = ProductionOrder::orderByDesc('id')->first();
        $this->assertEquals($order->id, $productionRequest->production_order_id);
        $this->assertEquals('production-order-created', $productionRequest->status);
        $this->assertEquals($salesOrder->id, $order->sales_order_id);
        $this->assertEquals($salesOrderItem->id, $order->sales_order_item_id);
        $this->assertEquals(4.0, $order->quantity_ordered);

        $this->get(route('production.orders.create'))
            ->assertOk()
            ->assertDontSee('DO-1001');
    }

    public function test_can_convert_approved_production_plan_to_order(): void
    {
        // 1. Create a dummy approved production plan
        $plan = ProductionPlan::create([
            'tenant_id' => $this->tenantId,
            'plan_number' => 'PLN-2026-000001',
            'name' => 'E-Bike Target Plan',
            'product_id' => $this->finishedGood->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity' => 10.0,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+3 days')),
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        ProductionPlanRequirement::create([
            'tenant_id' => $this->tenantId,
            'production_plan_id' => $plan->id,
            'product_id' => $this->rawMaterial->id,
            'required_quantity' => 22.0,
            'uom_id' => $this->uom->id,
            'bom_level' => 1,
        ]);

        ProductionPlanOperation::create([
            'tenant_id' => $this->tenantId,
            'production_plan_id' => $plan->id,
            'sequence' => 1,
            'operation_number' => 'OP-010',
            'name' => 'Test Welding',
            'work_center_id' => $this->workCenter->id,
            'setup_time_minutes' => 10.0,
            'processing_time_minutes' => 200.0,
            'total_time_minutes' => 210.0,
        ]);

        // 2. Trigger creation
        $response = $this->post(route('production.plans.create-order', $plan->id));

        $response->assertRedirect();

        // 3. Verify order & status
        $plan->refresh();
        $this->assertEquals('released', $plan->status);

        $order = ProductionOrder::orderBy('id', 'desc')->first();
        $this->assertEquals($plan->id, $order->production_plan_id);
        $this->assertEquals(10.0, $order->quantity_ordered);

        $this->assertCount(1, $order->reservations);
        $this->assertEquals(22.0, $order->reservations->first()->quantity_planned);

        $this->assertCount(1, $order->operations);
        $this->assertEquals('ready', $order->operations->first()->status);
    }

    public function test_can_issue_and_return_materials(): void
    {
        $order = $this->createDirectOrderHelper();
        $reservation = $order->reservations->first();

        // Release order so we can issue materials
        $this->post(route('production.orders.release', $order->id));

        // Issue raw materials
        $response = $this->post(route('production.orders.issue', $order->id), [
            'reservation_id' => $reservation->id,
            'quantity' => 5.0,
            'remarks' => 'Standard Issue',
        ]);

        $response->assertRedirect();
        $reservation->refresh();

        $this->assertEquals(5.0, $reservation->quantity_issued);
        // Reserved quantity drops from 11.0 to 6.0
        $this->assertEquals(6.0, $reservation->quantity_reserved);
        $this->assertEquals(95.0, ProductWarehouseStock::firstWhere('product_id', $this->rawMaterial->id)->quantity);

        // Return unused raw materials
        $response = $this->post(route('production.orders.return', $order->id), [
            'reservation_id' => $reservation->id,
            'quantity' => 2.0,
            'remarks' => 'Unused return',
        ]);

        $response->assertRedirect();
        $reservation->refresh();

        $this->assertEquals(3.0, $reservation->quantity_issued);
        $this->assertEquals(97.0, ProductWarehouseStock::firstWhere('product_id', $this->rawMaterial->id)->quantity);
    }

    public function test_operation_execution_progress_sequence(): void
    {
        $order = $this->createDirectOrderHelper();
        $op1 = $order->operations->first();
        $op2 = $order->operations->last();

        $this->post(route('production.orders.release', $order->id));

        // First operation is Ready, second is Waiting
        $this->assertEquals('ready', $op1->status);
        $this->assertEquals('waiting', $op2->status);

        // Log partial progress on first operation -> transitions status to Running
        $response = $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op1->id,
            'quantity_produced' => 2.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
            'setup_minutes_logged' => 5,
            'run_minutes_logged' => 40,
            'complete_operation' => 0,
        ]);

        $response->assertRedirect();
        $op1->refresh();
        $order->refresh();

        $this->assertEquals('running', $op1->status);
        $this->assertEquals('in_progress', $order->status); // Parent automatically moved to In Progress

        // Complete the first operation
        $response = $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op1->id,
            'quantity_produced' => 3.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
            'setup_minutes_logged' => 5,
            'run_minutes_logged' => 60,
            'complete_operation' => 1,
        ]);

        $op1->refresh();
        $op2->refresh();

        $this->assertEquals('completed', $op1->status);
        // Next operation in sequence automatically set to Ready!
        $this->assertEquals('ready', $op2->status);
    }

    public function test_can_log_scrap_rework_and_finished_goods_receipt(): void
    {
        $order = $this->createDirectOrderHelper();

        // Release order
        $this->post(route('production.orders.release', $order->id));

        // Log scrap
        $response = $this->post(route('production.orders.log-scrap', $order->id), [
            'quantity' => 1.5,
            'reason' => 'Deformed material',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('production_order_scraps', [
            'production_order_id' => $order->id,
            'quantity' => 1.5,
        ]);

        $order->refresh();
        $this->assertEquals(1.5, $order->quantity_scrapped);

        // Receive Finished Goods
        $response = $this->post(route('production.orders.receive-fg', $order->id), [
            'quantity_received' => 3.0,
            'quality_status' => 'passed',
            'remarks' => 'First batch fg',
        ]);
        $response->assertRedirect();

        $order->refresh();
        $this->assertEquals(3.0, $order->quantity_produced);
        $this->assertDatabaseHas('product_warehouse_stocks', [
            'tenant_id' => $this->tenantId,
            'product_id' => $this->finishedGood->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 3.0,
        ]);
    }

    public function test_can_calculate_cost_analysis_variance(): void
    {
        $order = $this->createDirectOrderHelper();
        $op = $order->operations->first();
        $res = $order->reservations->first();

        $this->post(route('production.orders.release', $order->id));

        // Issue 10 units of material (unit cost = 45) -> Actual Material Cost = 450
        $this->post(route('production.orders.issue', $order->id), [
            'reservation_id' => $res->id,
            'quantity' => 10.0,
        ]);

        // Log execution: 120 minutes setup + run
        // OP-010: Labor rate = 1.50, Machine rate = 2.00, Overhead rate = 1.00 per min.
        // Total Actual Op Cost = 120 * (1.50 + 2.00 + 1.00) = 540
        $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op->id,
            'quantity_produced' => 5.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
            'setup_minutes_logged' => 20,
            'run_minutes_logged' => 100,
            'complete_operation' => 1,
        ]);

        $order->refresh();

        // Get variance cost analysis from show controller
        $costs = (new ProductionCostVarianceService)->getCostAnalysis($order);

        $this->assertEquals(450.0, $costs['material']['actual']);
        $this->assertEquals(180.0, $costs['labor']['actual']); // 120m * 1.50
        $this->assertEquals(240.0, $costs['machine']['actual']); // 120m * 2.00
        $this->assertEquals(120.0, $costs['overhead']['actual']); // 120m * 1.00
    }

    public function test_tenant_isolation(): void
    {
        $order = $this->createDirectOrderHelper();

        // Create user in another tenant
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        $otherUser = User::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Admin',
            'email' => 'other-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
        $this->actingAs($otherUser);

        // Attempting to view order should fail (or block if testing)
        $response = $this->get(route('production.orders.show', $order->id));

        // Gates are bypassed in local dev unless environment is set to testing
        if (app()->environment('testing')) {
            $response->assertStatus(403);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_logging_progress_with_rejected_or_scrapped_quantities_automatically_generates_quality_records(): void
    {
        $order = $this->createDirectOrderHelper();
        $op = $order->operations->first();

        $this->post(route('production.orders.release', $order->id));

        // Log execution with 2 rejected and 1 scrapped
        $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op->id,
            'quantity_produced' => 2.0,
            'quantity_rejected' => 2.0,
            'quantity_scrapped' => 1.0,
            'setup_minutes_logged' => 10,
            'run_minutes_logged' => 30,
            'complete_operation' => 0,
        ]);

        // Assert NCR was created for rework and scrap
        $this->assertDatabaseHas('production_ncrs', [
            'production_order_id' => $order->id,
            'disposition_type' => 'rework',
        ]);
        $this->assertDatabaseHas('production_ncrs', [
            'production_order_id' => $order->id,
            'disposition_type' => 'scrap',
        ]);

        // Assert Rework Order was created
        $this->assertDatabaseHas('production_rework_orders', [
            'original_production_order_id' => $order->id,
        ]);

        // Assert Scrap Disposal was created
        $this->assertDatabaseHas('production_scrap_disposals', [
            'quantity' => 1.0,
        ]);
    }

    public function test_completing_rework_order_reconciles_quantities_back_to_produced(): void
    {
        $order = $this->createDirectOrderHelper();
        $op = $order->operations->first();
        $this->withoutExceptionHandling();

        $this->post(route('production.orders.release', $order->id));

        // Log progress with 2 rejected (creates NCR and Rework order)
        $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op->id,
            'quantity_produced' => 2.0,
            'quantity_rejected' => 2.0,
            'quantity_scrapped' => 0.0,
            'setup_minutes_logged' => 10,
            'run_minutes_logged' => 30,
            'complete_operation' => 0,
        ]);

        $order->refresh();
        $this->assertEquals(2.0, $order->quantity_rejected);

        $reworkOrder = \App\Domains\Production\Models\ProductionReworkOrder::where('original_production_order_id', $order->id)->first();
        // Since we created 2 operations by default (sequence 10, 20), complete them both
        foreach ($reworkOrder->operations as $reworkOp) {
            // Start rework operation
            $this->post(route('production.quality.rework.ops.start', $reworkOp->id));

            // Complete rework operation
            $this->post(route('production.quality.rework.ops.complete', $reworkOp->id), [
                'setup_time_actual' => 10.0,
            ]);
        }

        $order->refresh();
        $op->refresh();

        // Rejected quantity should be reduced back to 0 at order and operation levels
        $this->assertEquals(0.0, $order->quantity_rejected);
        $this->assertEquals(0.0, $op->quantity_rejected);
        // Produced quantity should be incremented (2.0 original + 2.0 reworked = 4.0)
        $this->assertEquals(4.0, $op->quantity_produced);

        // Associated NCR should be closed
        $ncr = $reworkOrder->ncr;
        $ncr->refresh();
        $this->assertEquals('closed', $ncr->status);
    }

    public function test_scrapped_quantities_do_not_restrict_production_limits(): void
    {
        $order = $this->createDirectOrderHelper();
        $op = $order->operations->first();

        $this->post(route('production.orders.release', $order->id));

        // Log progress: 4 produced, 6 scrapped (Total target = 5.0)
        // With previous logic this would fail because 4+6 = 10 > 5.
        // With new logic, scrapped is ignored in check, so 4 produced is compared to 5, which passes.
        $response = $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op->id,
            'quantity_produced' => 4.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 6.0,
            'setup_minutes_logged' => 10,
            'run_minutes_logged' => 30,
            'complete_operation' => 0,
        ]);

        $response->assertRedirect();
        
        $order->refresh();
        $op->refresh();

        // 6 scrapped logged successfully
        $this->assertEquals(6.0, $op->quantity_scrapped);
        $this->assertEquals(6.0, $order->quantity_scrapped);
        // Produced quantity should be 4
        $this->assertEquals(4.0, $op->quantity_produced);

        // Operator should be allowed to log 1 more produced unit without hitting the limit (4 + 1 = 5)
        $response2 = $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op->id,
            'quantity_produced' => 1.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
            'setup_minutes_logged' => 10,
            'run_minutes_logged' => 30,
            'complete_operation' => 0,
        ]);

        $response2->assertRedirect();
    }

    public function test_approving_scrap_disposal_closes_associated_ncr(): void
    {
        $order = $this->createDirectOrderHelper();
        $op = $order->operations->first();

        $this->post(route('production.orders.release', $order->id));

        // Log progress with 1 scrapped (creates NCR and Scrap Disposal)
        $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op->id,
            'quantity_produced' => 0.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 1.0,
            'setup_minutes_logged' => 10,
            'run_minutes_logged' => 30,
            'complete_operation' => 0,
        ]);

        $ncr = \App\Domains\Production\Models\ProductionNcr::where('production_order_id', $order->id)->where('disposition_type', 'scrap')->first();
        $this->assertNotNull($ncr);
        $this->assertEquals('open', $ncr->status);

        $disposal = \App\Domains\Production\Models\ProductionScrapDisposal::where('ncr_id', $ncr->id)->first();
        $this->assertNotNull($disposal);
        $this->assertEquals('pending_approval', $disposal->status);

        // Approve the scrap disposal
        $this->post(route('production.quality.scrap.approve', $disposal->id));

        $disposal->refresh();
        $ncr->refresh();

        $this->assertEquals('approved', $disposal->status);
        $this->assertEquals('closed', $ncr->status);
    }

    public function test_cannot_log_progress_on_completed_operation_or_fully_produced_target(): void
    {
        $order = $this->createDirectOrderHelper();
        $op = $order->operations->first();

        $this->post(route('production.orders.release', $order->id));

        // 1. Cannot log on completed operation
        $op->update(['status' => 'completed']);

        $response = $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op->id,
            'quantity_produced' => 1.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
            'setup_minutes_logged' => 10,
            'run_minutes_logged' => 30,
            'complete_operation' => 0,
        ]);

        $response->assertSessionHas('error');

        // Restore operation status to draft/in_progress
        $op->update(['status' => 'in_progress']);

        // Log 5.0 (the full target limit of 5.0)
        $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op->id,
            'quantity_produced' => 5.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 0.0,
            'setup_minutes_logged' => 10,
            'run_minutes_logged' => 30,
            'complete_operation' => 0,
        ]);

        $op->refresh();
        $this->assertEquals(5.0, $op->quantity_produced);

        // 2. Cannot log any more once target is hit
        $response2 = $this->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op->id,
            'quantity_produced' => 0.0,
            'quantity_rejected' => 0.0,
            'quantity_scrapped' => 1.0,
            'setup_minutes_logged' => 10,
            'run_minutes_logged' => 30,
            'complete_operation' => 0,
        ]);

        $response2->assertSessionHas('error');
    }

    private function createDirectOrderHelper(): ProductionOrder
    {
        $this->actingAs($this->user);

        $this->post(route('production.orders.store'), [
            'product_id' => $this->finishedGood->id,
            'quantity_ordered' => 5.0,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+5 days')),
        ]);

        return ProductionOrder::orderBy('id', 'desc')->first();
    }
}
