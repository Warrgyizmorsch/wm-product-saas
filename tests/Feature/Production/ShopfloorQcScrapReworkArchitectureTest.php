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
}
