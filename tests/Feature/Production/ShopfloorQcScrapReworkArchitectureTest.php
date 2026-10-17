<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionWipService;
use App\Domains\Production\Services\QualityInspectionService;
use App\Domains\Production\Services\ScrapService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopfloorQcScrapReworkArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected Warehouse $rawWarehouse;
    protected Warehouse $fgWarehouse;
    protected Product $rawPipe;
    protected Product $legProduct;
    protected ProductionBom $legBom;
    protected Routing $legRouting;
    protected WorkCenter $workCenter;
    protected Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Architecture Test Tenant',
            'slug' => 'archtenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'QC Operator',
            'email' => 'operator@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $uomPcs = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS', 'category' => 'Goods']);
        $uomMtr = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Meters', 'code' => 'MTR', 'category' => 'Goods']);

        $this->rawWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Raw Material Store',
            'code' => 'RAW-STORE',
            'type' => 'standard',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->fgWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Goods Warehouse',
            'code' => 'FG-WH',
            'type' => 'standard',
            'status' => 'active',
            'is_default' => false,
        ]);

        $this->rawPipe = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'RM-PIPE-001',
            'name' => 'Steel Pipe 50x50mm',
            'type' => 'raw_material',
            'planning_type' => 'purchase',
            'uom_id' => $uomMtr->id,
            'unit_cost' => 300.0,
            'cost_price' => 300.0,
            'opening_stock' => 500.0,
        ]);

        ProductWarehouseStock::create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->rawWarehouse->id,
            'product_id' => $this->rawPipe->id,
            'quantity' => 500.0,
            'available_qty' => 500.0,
            'reserved_qty' => 0.0,
        ]);

        $this->legProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'SFG-LEG-001',
            'name' => 'Table Leg Steel',
            'type' => 'semi_finished',
            'planning_type' => 'manufacture',
            'uom_id' => $uomPcs->id,
            'unit_cost' => 650.0,
            'cost_price' => 650.0,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WC-CUT',
            'name' => 'Cutting Station',
            'status' => 'active',
            'capacity_per_hour' => 10,
            'efficiency_percentage' => 100,
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'code' => 'MAC-CUT-01',
            'name' => 'Pipe Saw Machine',
            'status' => 'active',
        ]);

        $this->legRouting = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->legProduct->id,
            'routing_code' => 'RT-LEG',
            'name' => 'Leg Cutting & Processing Routing',
            'version' => 1,
            'status' => 'approved',
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->legRouting->id,
            'sequence' => 10,
            'operation_number' => 'OP10',
            'name' => 'Leg Cutting & Deburring',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'setup_time_minutes' => 10,
            'processing_time_minutes' => 2,
            'labor_cost_rate' => 10.0,
            'machine_cost_rate' => 15.0,
            'quality_required' => true,
        ]);

        $this->legBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-LEG',
            'bom_name' => 'Leg Steel BOM',
            'product_id' => $this->legProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $uomPcs->id,
            'version' => 1,
            'routing_id' => $this->legRouting->id,
            'status' => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $this->legBom->id,
            'material_id' => $this->rawPipe->id,
            'quantity' => 0.75,
            'uom_id' => $uomMtr->id,
        ]);
    }

    protected function createTestOrder(float $qty = 40.0): ProductionOrder
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-TEST-' . rand(1000, 9999),
            'product_id' => $this->legProduct->id,
            'bom_id' => $this->legBom->id,
            'routing_id' => $this->legRouting->id,
            'quantity_ordered' => $qty,
            'quantity_produced' => 0,
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $schedule = \App\Domains\Production\Models\ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'schedule_number' => 'SCH-' . $order->order_number,
            'status' => 'released',
            'scheduled_at' => now(),
        ]);

        foreach ($this->legRouting->operations as $rOp) {
            $op = ProductionOrderOperation::create([
                'tenant_id' => $this->tenant->id,
                'production_order_id' => $order->id,
                'routing_operation_id' => $rOp->id,
                'sequence' => $rOp->sequence,
                'operation_number' => $rOp->operation_number,
                'name' => $rOp->name,
                'work_center_id' => $rOp->work_center_id,
                'machine_id' => $rOp->machine_id,
                'target_produced_qty' => $qty,
                'quantity_produced' => 0,
                'quantity_rejected' => 0,
                'quantity_scrapped' => 0,
                'quality_required' => $rOp->quality_required,
                'status' => 'running',
            ]);

            \App\Domains\Production\Models\ProductionScheduleOperation::create([
                'tenant_id' => $this->tenant->id,
                'production_schedule_id' => $schedule->id,
                'production_order_id' => $order->id,
                'production_order_operation_id' => $op->id,
                'work_center_id' => $op->work_center_id,
                'sequence' => $op->sequence,
                'status' => 'running',
                'planned_start' => now(),
                'planned_finish' => now()->addHours(2),
            ]);
        }

        return $order;
    }

    public function test_scenario_1_40_processed_40_qc_accepted_completes_operation(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        // Log progress 40 processed output
        \App\Domains\Production\Models\ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $orderOp->id,
            'quantity_produced' => 40,
            'recorded_at' => now(),
        ]);

        $orderOp->refresh();
        $this->assertEquals(0.0, $orderOp->quantity_produced);
        $this->assertEquals(40.0, app(MesExecutionService::class)->getPendingQcQuantity($orderOp->id));

        // QC Inspector records 40 Accepted
        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $orderOp->id,
            'accepted_qty' => 40,
            'rejected_qty' => 0,
        ], $this->user->id);

        $orderOp->refresh();
        $this->assertEquals(40.0, $orderOp->quantity_produced);
        $this->assertEquals(0.0, app(MesExecutionService::class)->getPendingQcQuantity($orderOp->id));
        $this->assertEquals('completed', $orderOp->status);
    }

    public function test_scenario_2_40_processed_37_accepted_3_rework_to_reqc_to_40_accepted(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        \App\Domains\Production\Models\ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $orderOp->id,
            'quantity_produced' => 40,
            'recorded_at' => now(),
        ]);

        // QC Inspector records 37 Accepted, 3 Rejected
        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $orderOp->id,
            'accepted_qty' => 37,
            'rejected_qty' => 3,
        ], $this->user->id);

        $orderOp->refresh();
        $this->assertEquals(37.0, $orderOp->quantity_produced);
        $this->assertEquals(3.0, $orderOp->quantity_rejected);
        $this->assertEquals('running', $orderOp->status);

        // Re-QC on reworked 3 units passes
        \App\Domains\Production\Models\ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $orderOp->id,
            'quantity_produced' => 3,
            'recorded_at' => now(),
        ]);

        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $orderOp->id,
            'accepted_qty' => 3,
            'rejected_qty' => 0,
        ], $this->user->id);

        $orderOp->refresh();
        $this->assertEquals(40.0, $orderOp->quantity_produced);
        $this->assertEquals('completed', $orderOp->status);
    }

    public function test_scenario_3_40_processed_37_accepted_3_scrap_replacement_material_issued(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        \App\Domains\Production\Models\ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $orderOp->id,
            'quantity_produced' => 40,
            'recorded_at' => now(),
        ]);

        // QC Inspector records 37 Accepted, 3 Scrapped/Rejected
        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $orderOp->id,
            'accepted_qty' => 37,
            'rejected_qty' => 3,
        ], $this->user->id);

        $orderOp->refresh();
        $this->assertEquals(37.0, $orderOp->quantity_produced);
        $this->assertEquals('running', $orderOp->status);

        // Replacement material evaluation (3 legs * 0.75 = 2.25 mtr)
        $result = app(ProductionMaterialService::class)->evaluateAndIssueReplacementMaterial(
            $this->tenant->id,
            $order->id,
            $this->rawPipe->id,
            2.25,
            'Scrap replacement for 3 legs'
        );

        $this->assertEquals('requested', $result['status']);
        $this->assertEquals(2.25, $result['requested_qty']);

        // Replacement run logs 3 good legs and passes QC
        \App\Domains\Production\Models\ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $orderOp->id,
            'quantity_produced' => 3,
            'recorded_at' => now(),
        ]);

        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $orderOp->id,
            'accepted_qty' => 3,
            'rejected_qty' => 0,
        ], $this->user->id);

        $orderOp->refresh();
        $this->assertEquals(40.0, $orderOp->quantity_produced);
        $this->assertEquals('completed', $orderOp->status);
    }

    public function test_scenario_4_partial_qc_and_multiple_inspections(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        \App\Domains\Production\Models\ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $orderOp->id,
            'quantity_produced' => 40,
            'recorded_at' => now(),
        ]);

        // Partial QC inspection 1: 20 units inspected (20 accepted)
        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $orderOp->id,
            'accepted_qty' => 20,
            'rejected_qty' => 0,
        ], $this->user->id);

        $this->assertEquals(20.0, app(MesExecutionService::class)->getPendingQcQuantity($orderOp->id));

        // Partial QC inspection 2: remaining 20 inspected (20 accepted)
        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $orderOp->id,
            'accepted_qty' => 20,
            'rejected_qty' => 0,
        ], $this->user->id);

        $orderOp->refresh();
        $this->assertEquals(0.0, app(MesExecutionService::class)->getPendingQcQuantity($orderOp->id));
        $this->assertEquals(40.0, $orderOp->quantity_produced);
        $this->assertEquals('completed', $orderOp->status);
    }

    public function test_scenario_5_material_shortage_triggers_mrp_shortage(): void
    {
        $order = $this->createTestOrder(40);
        ProductWarehouseStock::where('product_id', $this->rawPipe->id)->update([
            'quantity' => 1.0,
            'available_qty' => 1.0,
        ]);

        $result = app(ProductionMaterialService::class)->evaluateAndIssueReplacementMaterial(
            $this->tenant->id,
            $order->id,
            $this->rawPipe->id,
            2.25,
            'Material shortage test'
        );

        $this->assertEquals('requested', $result['status']);
        $this->assertEquals(2.25, $result['requested_qty']);
    }

    public function test_shopfloor_ui_renders_qc_metrics_and_action_buttons(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        // Log 40 units progress so pending QC is 40
        \App\Domains\Production\Models\ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $orderOp->id,
            'quantity_produced' => 40,
            'recorded_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Run QC');
        $response->assertSee('Scrap');
        $response->assertSee('Tgt: 40');
        $response->assertSee('QC: 40');

        $responseOp = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.operator.execution', $orderOp->id));
        $responseOp->assertStatus(200);
        $responseOp->assertSee('Shopfloor Quality Inspection Check');
        $responseOp->assertSee('Record Operational Scrap');
    }

    public function test_record_operational_scrap_route(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $orderOp->id), [
                'quantity' => 2,
                'reason' => 'Cutting Error / Wrong Dimension',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('production_order_scraps', [
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'production_order_operation_id' => $orderOp->id,
            'quantity' => 2,
            'reason' => 'Cutting Error / Wrong Dimension',
        ]);

        $orderOp->refresh();
        $this->assertEquals(2.0, $orderOp->quantity_scrapped);
    }

    public function test_record_disposition_route_rework_and_scrap(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        // Log 40 units progress so pending QC is 40
        \App\Domains\Production\Models\ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $orderOp->id,
            'quantity_produced' => 40,
            'recorded_at' => now(),
        ]);

        // Perform inspection with 37 passed and 3 rejected
        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $orderOp->id,
            'accepted_qty' => 37,
            'rejected_qty' => 3,
        ], $this->user->id);

        $orderOp->refresh();
        $this->assertEquals(3.0, $orderOp->quantity_rejected);

        // Post disposition as rework for 2 units
        $responseRework = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.disposition', $orderOp->id), [
                'disposition_type' => 'rework',
                'quantity' => 2,
                'reason' => 'Fix dimension tolerance',
                'instructions' => 'Reprocess on lathe machine',
            ]);

        $responseRework->assertRedirect();
        $responseRework->assertSessionHas('success');

        $this->assertDatabaseHas('production_rework_orders', [
            'tenant_id' => $this->tenant->id,
            'original_production_order_id' => $order->id,
            'status' => 'draft',
        ]);

        $orderOp->refresh();
        $this->assertEquals(1.0, $orderOp->quantity_rejected); // 3 - 2 rework = 1 remaining

        // Post disposition as scrap for 1 unit
        $responseScrap = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.disposition', $orderOp->id), [
                'disposition_type' => 'scrap',
                'quantity' => 1,
                'reason' => 'Irreparable structural crack',
            ]);

        $responseScrap->assertRedirect();
        $responseScrap->assertSessionHas('success');

        $orderOp->refresh();
        $this->assertEquals(0.0, $orderOp->quantity_rejected); // 1 - 1 scrap = 0 remaining
        $this->assertEquals(1.0, $orderOp->quantity_scrapped);
    }

    public function test_qc_inspection_fails_when_operation_has_no_pending_qc_units(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        // No progress logged yet (operation has not started output, pending QC is 0)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There are no pending QC units');

        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $orderOp->id,
            'accepted_qty' => 10,
            'rejected_qty' => 0,
        ], $this->user->id);
    }

    public function test_qc_inspection_fails_when_inspected_quantity_exceeds_pending_qc(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        // Log 15 units progress
        \App\Domains\Production\Models\ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $orderOp->id,
            'quantity_produced' => 15,
            'recorded_at' => now(),
        ]);

        // Trying to inspect 12 accepted + 5 rejected = 17 > 15 pending
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Total inspected quantity (17) cannot exceed pending QC units (15)');

        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $orderOp->id,
            'accepted_qty' => 12,
            'rejected_qty' => 5,
        ], $this->user->id);
    }

    public function test_qc_cannot_be_logged_via_endpoint_when_no_pending_qc(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        // Post to quality inspection endpoint with 0 pending QC
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.quality-inspection', $orderOp->id), [
                'accepted_qty' => 10,
                'rejected_qty' => 0,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $orderOp->refresh();
        $this->assertEquals(0.0, $orderOp->quantity_produced);
        $this->assertEquals(0.0, $orderOp->quantity_rejected);
    }

    public function test_run_qc_button_is_not_visible_when_pending_qc_is_zero(): void
    {
        $order = $this->createTestOrder(40);
        $orderOp = $order->operations->first();

        // 1. When pending QC is 0 (operation output not yet logged), Run QC action button is NOT visible
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('data-bs-target="#qcModal' . $orderOp->id . '"', false);
        $response->assertDontSee('title="Run Quality Inspection"', false);

        // 2. Now log 10 units progress -> Run QC button becomes visible with badge 10
        \App\Domains\Production\Models\ProductionOrderProgressLog::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'operation_id' => $orderOp->id,
            'quantity_produced' => 10,
            'recorded_at' => now(),
        ]);

        $responseWithQc = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.dashboard'));

        $responseWithQc->assertStatus(200);
        $responseWithQc->assertSee('data-bs-target="#qcModal' . $orderOp->id . '"', false);
        $responseWithQc->assertSee('title="Run Quality Inspection"', false);
        $responseWithQc->assertSee('QC: 10');
    }

    public function test_remaining_production_autofill_accounts_for_pending_qc(): void
    {
        // Target planned = 5 units
        $order = $this->createTestOrder(5);
        $orderOp = $order->operations->first();
        $schedOp = \App\Domains\Production\Models\ProductionScheduleOperation::where('production_order_operation_id', $orderOp->id)->first();
        $schedOp->update(['status' => 'running', 'actual_start' => now()->subHour()]);

        // 1. Operator logs 2 units produced -> They enter Pending QC
        $responseLog1 = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.complete', $schedOp->id), [
                'log_date' => now()->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'quantity_produced' => 2,
                'quantity_rejected' => 0,
                'quantity_scrapped' => 0,
            ]);
        $responseLog1->assertRedirect();
        $responseLog1->assertSessionHas('success');

        $this->assertEquals(2.0, app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($orderOp->id));

        // 2. QC approves 1 unit -> 1 unit remains in Pending QC, 1 unit is accepted
        $responseQc1 = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.quality-inspection', $orderOp->id), [
                'accepted_qty' => 1,
                'rejected_qty' => 0,
                'inspection_date' => now()->format('Y-m-d'),
            ]);
        $responseQc1->assertRedirect();
        $responseQc1->assertSessionHas('success');

        $orderOp->refresh();
        $this->assertEquals(1.0, (float) $orderOp->quantity_produced);
        $this->assertEquals(1.0, app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($orderOp->id));

        // 3. Inspect Dashboard & Operator views: Remaining quantity to log MUST be 3 (5 - (1 done + 1 pending QC)), NOT 4!
        $dashboardResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.dashboard'));
        $dashboardResponse->assertStatus(200);
        // The modal input value for quantity_produced must be 3
        $dashboardResponse->assertSee('value="3"', false);
        $dashboardResponse->assertDontSee('value="4"', false);

        $operatorResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.operator.execution', $orderOp->id));
        $operatorResponse->assertStatus(200);
        $operatorResponse->assertSee('3.00');

        // 4. Log remaining 3 units -> Must succeed without overproduction or WIP violation!
        $schedOp->refresh();
        $schedOp->update(['status' => 'running', 'actual_start' => now()->subHour()]);
        $responseLog2 = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.complete', $schedOp->id), [
                'log_date' => now()->format('Y-m-d'),
                'start_time' => '10:00',
                'end_time' => '11:00',
                'quantity_produced' => 3,
                'quantity_rejected' => 0,
                'quantity_scrapped' => 0,
            ]);
        $responseLog2->assertRedirect();
        $responseLog2->assertSessionHas('success');

        // Now pending QC is 4 (1 previous + 3 new)
        $this->assertEquals(4.0, app(\App\Domains\Production\Services\MesExecutionService::class)->getPendingQcQuantity($orderOp->id));
    }

    public function test_assign_to_and_executed_by_are_decoupled_on_shopfloor(): void
    {
        $order = $this->createTestOrder(10);
        $orderOp = $order->operations->first();
        $schedOp = \App\Domains\Production\Models\ProductionScheduleOperation::where('production_order_operation_id', $orderOp->id)->first();
        $schedOp->update(['status' => 'running', 'actual_start' => now()->subHour()]);

        // 1. Initial State: Unassigned
        $initialResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.dashboard'));
        $initialResponse->assertStatus(200);
        $initialResponse->assertSee('Executed By');
        $initialResponse->assertSee('Unassigned');

        // 2. User logs production output (e.g. $this->user)
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.complete', $schedOp->id), [
                'log_date' => now()->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '10:00',
                'quantity_produced' => 2,
                'quantity_rejected' => 0,
                'quantity_scrapped' => 0,
            ]);

        // Dashboard check: Executed By shows logger's name, Assign To MUST still remain Unassigned!
        $afterLogResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.dashboard'));
        $afterLogResponse->assertStatus(200);
        $afterLogResponse->assertSee('Executed / Logged by ' . $this->user->name, false);
        $afterLogResponse->assertSee('Unassigned');

        // 3. Assign operation to a different user
        $operatorUser = \App\Models\User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Designated Machine Operator',
        ]);
        \App\Domains\Production\Models\ProductionOperatorSkill::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $operatorUser->id,
            'skill_name' => 'General Operations',
            'skill_code' => 'SKL-ALL',
            'proficiency_level' => 'expert',
            'active' => true,
        ]);

        app(\App\Domains\Production\Services\OperatorAssignmentService::class)->assign(
            $this->tenant->id,
            $orderOp->id,
            $operatorUser->id,
            $this->user->id,
            'Assigned for morning shift'
        );

        // Dashboard check: Assign To now shows the designated operator, Executed By still shows logger!
        $afterAssignResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.dashboard'));
        $afterAssignResponse->assertStatus(200);
        $afterAssignResponse->assertSee('Pending: Designated Machine Operator');
        $afterAssignResponse->assertSee('Executed / Logged by ' . $this->user->name, false);
        // Since operation is running and operator is assigned, Edit button must NOT be visible
        $afterAssignResponse->assertDontSee('#assignOperatorModal' . $schedOp->id, false);
    }

    public function test_cannot_change_or_edit_operator_once_operation_is_started(): void
    {
        $order = $this->createTestOrder(10);
        $orderOp = $order->operations->first();
        $schedOp = \App\Domains\Production\Models\ProductionScheduleOperation::where('production_order_operation_id', $orderOp->id)->first();
        $schedOp->update(['status' => 'ready', 'actual_start' => null]);
        $orderOp->update(['status' => 'ready', 'actual_start_time' => null]);

        $op1 = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Operator Alpha']);
        $op2 = \App\Models\User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Operator Beta']);

        \App\Domains\Production\Models\ProductionOperatorSkill::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $op1->id,
            'skill_name' => 'General Operations',
            'skill_code' => 'SKL-ALL',
            'proficiency_level' => 'expert',
            'active' => true,
        ]);
        \App\Domains\Production\Models\ProductionOperatorSkill::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $op2->id,
            'skill_name' => 'General Operations',
            'skill_code' => 'SKL-ALL',
            'proficiency_level' => 'expert',
            'active' => true,
        ]);

        // 1. Initial assignment before operation starts:
        $assignment = app(\App\Domains\Production\Services\OperatorAssignmentService::class)->assign(
            $this->tenant->id,
            $orderOp->id,
            $op1->id,
            $this->user->id
        );

        // Before starting: Dashboard should show Operator Alpha and the Edit button
        $beforeStartResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.dashboard'));
        $beforeStartResponse->assertStatus(200);
        $beforeStartResponse->assertSee('Pending: Operator Alpha');
        $beforeStartResponse->assertSee('#assignOperatorModal' . $schedOp->id, false);
        $beforeStartResponse->assertSee('Edit');

        // Before starting: Production order screen also shows Reassign button
        $orderShowBefore = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']));
        $orderShowBefore->assertStatus(200);
        $orderShowBefore->assertSee('title="Reassign"', false);

        // Before starting: Reassigning to Operator Beta is allowed
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.assignments.assign'), [
                'production_order_operation_id' => $orderOp->id,
                'user_id' => $op2->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals($op2->id, $orderOp->fresh()->operator_id);

        // 2. Start the operation
        $schedOp->update(['status' => 'running', 'actual_start' => now()]);
        $orderOp->update(['status' => 'running', 'actual_start_time' => now()]);

        // Once started: Edit button and assign modal MUST NOT be rendered on shopfloor
        $afterStartResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.dashboard'));
        $afterStartResponse->assertStatus(200);
        $afterStartResponse->assertSee('Pending: Operator Beta');
        $afterStartResponse->assertDontSee('#assignOperatorModal' . $schedOp->id, false);

        // Once started: Production order screen MUST NOT render the Reassign button
        $orderShowAfter = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.orders.show', ['order' => $order->id, 'tab' => 'vtab-operations']));
        $orderShowAfter->assertStatus(200);
        $orderShowAfter->assertDontSee('title="Reassign"', false);

        // Attempting to reassign once started via HTTP POST must be rejected
        $failedPost = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.assignments.assign'), [
                'production_order_operation_id' => $orderOp->id,
                'user_id' => $op1->id,
            ]);
        $failedPost->assertRedirect();
        $failedPost->assertSessionHas('error', 'Cannot change operator: This operation has already started.');

        // Operator remains Operator Beta
        $this->assertEquals($op2->id, $orderOp->fresh()->operator_id);
    }
}

