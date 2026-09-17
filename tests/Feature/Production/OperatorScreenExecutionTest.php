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
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\QualityInspectionService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorScreenExecutionTest extends TestCase
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
    protected ProductionQualityPlan $qualityPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Operator Screen Test Tenant',
            'slug' => 'operatortenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Floor Operator',
            'email' => 'operator_touch@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $uomPcs = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS', 'symbol' => 'pcs']);
        $uomMtr = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Meters', 'code' => 'MTR', 'symbol' => 'm']);

        $this->rawWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WH-RAW',
            'name' => 'Raw Materials Storage',
            'type' => 'internal',
            'is_active' => true,
        ]);

        $this->fgWarehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'WH-FG',
            'name' => 'Finished Goods Warehouse',
            'type' => 'internal',
            'is_active' => true,
        ]);

        $this->rawPipe = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'RAW-PIPE-001',
            'name' => 'Steel Pipe 2 Inch',
            'type' => 'raw',
            'uom_id' => $uomMtr->id,
            'unit_cost' => 120.0,
            'cost_price' => 120.0,
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

        $this->qualityPlan = ProductionQualityPlan::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Table Leg In-Process Quality Plan',
            'code' => 'QP-LEG-01',
            'type' => 'in_process',
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);
    }

    protected function createTestOrder(float $qty = 40.0, bool $qualityRequired = true): ProductionOrder
    {
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-OP-TEST-' . rand(1000, 9999),
            'product_id' => $this->legProduct->id,
            'bom_id' => $this->legBom->id,
            'routing_id' => $this->legRouting->id,
            'quantity_ordered' => $qty,
            'quantity_produced' => 0,
            'status' => 'released',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $order->id,
            'schedule_number' => 'SCH-' . $order->order_number,
            'status' => 'released',
            'scheduled_at' => now(),
        ]);

        $rOp = $this->legRouting->operations->first();
        if ($rOp) {
            $rOp->update(['quality_required' => $qualityRequired]);
        }
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
            'quality_required' => $qualityRequired,
            'status' => 'running',
        ]);

        ProductionScheduleOperation::create([
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

        return $order;
    }

    /**
     * Test 1 — Operator screen renders with aligned controls and no manual complete button.
     */
    public function test_operator_screen_renders_with_aligned_controls_and_no_manual_complete_button(): void
    {
        $order = $this->createTestOrder(40, true);
        $op = $order->operations->first();

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.operator.execution', $op->id));

        $response->assertStatus(200);

        // Aligned Toolbar Buttons
        $response->assertSee('LOG PROGRESS');
        $response->assertSee('LOG SCRAP');
        $response->assertSee('QC CHECK');

        // Operation Output & Completion Progress Card & Autofill
        $response->assertSee('Operation Output and Completion Progress');
        $response->assertSee('Target Quantity');
        $response->assertSee('Completed / Produced');
        $response->assertSee('Remaining Left');
        $response->assertSee('value="40"', false);

        // Manual COMPLETE button must be strictly absent
        $response->assertDontSee('data-bs-target="#completeModal"');
        $response->assertDontSee('id="completeModal"');

        // REWORK / SCRAP disposition button should NOT appear when rejected quantity is 0
        $response->assertDontSee('REWORK / SCRAP');

        // Now set rejected quantity to 5 and verify REWORK / SCRAP button appears
        $op->update(['quantity_rejected' => 5]);
        $responseWithRejects = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->get(route('production.mes.operator.execution', $op->id));

        $responseWithRejects->assertSee('REWORK / SCRAP');
        $responseWithRejects->assertSee('5');
    }

    /**
     * Test 2 — Non-QC operation logging and auto-completion when target is met.
     */
    public function test_non_qc_operation_logging_and_auto_completion(): void
    {
        $order = $this->createTestOrder(25, false); // QC = false
        $op = $order->operations->first();
        $schedOp = ProductionScheduleOperation::where('production_order_operation_id', $op->id)->first();

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.complete', $schedOp->id), [
                'quantity_produced' => 25,
                'quantity_rejected' => 0,
                'quantity_scrapped' => 0,
                'remarks' => 'Completed all 25 units on shift without QC',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify shared completion logic automatically marked operation completed
        $op->refresh();
        $this->assertEquals(25.0, (float) $op->quantity_produced);
        $this->assertEquals(0.0, (float) $op->quantity_rejected);
        $this->assertEquals('completed', $op->status);

        $schedOp->refresh();
        $this->assertEquals('completed', $schedOp->status);
    }

    /**
     * Test 3 — QC operation output enters pending buffer and QC inspection auto-completes operation.
     */
    public function test_qc_operation_output_enters_pending_and_qc_inspection_completes_operation(): void
    {
        $order = $this->createTestOrder(30, true); // QC = true
        $op = $order->operations->first();
        $schedOp = ProductionScheduleOperation::where('production_order_operation_id', $op->id)->first();

        // 1. Operator logs 30 units produced
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.complete', $schedOp->id), [
                'quantity_produced' => 30,
                'quantity_rejected' => 0,
                'quantity_scrapped' => 0,
            ]);

        $op->refresh();
        // Good output is not directly incremented until QC approval
        $this->assertEquals(0.0, (float) $op->quantity_produced);
        $this->assertEquals('running', $op->status);

        // Verify pending QC buffer
        $pendingQc = app(MesExecutionService::class)->getPendingQcQuantity($op->id);
        $this->assertEquals(30.0, $pendingQc);

        // 2. Perform QC inspection: 30 accepted, 0 rejected
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.quality-inspection', $op->id), [
                'quality_plan_id' => $this->qualityPlan->id,
                'audited_by' => $this->user->id,
                'accepted_qty' => 30,
                'rejected_qty' => 0,
                'remarks' => 'All units passed dimensional check',
            ]);

        // Operation must now auto-complete
        $op->refresh();
        $this->assertEquals(30.0, (float) $op->quantity_produced);
        $this->assertEquals('completed', $op->status);

        $schedOp->refresh();
        $this->assertEquals('completed', $schedOp->status);
    }

    /**
     * Test 4 — Non-QC operation rejects direct rejected quantity submission.
     */
    public function test_non_qc_rejected_quantity_protection(): void
    {
        $order = $this->createTestOrder(20, false); // QC = false
        $op = $order->operations->first();
        $schedOp = ProductionScheduleOperation::where('production_order_operation_id', $op->id)->first();

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.complete', $schedOp->id), [
                'quantity_produced' => 15,
                'quantity_rejected' => 5, // Must be rejected!
                'quantity_scrapped' => 0,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Quality Check is not required', session('error'));

        $op->refresh();
        $this->assertEquals(0.0, (float) $op->quantity_rejected);
    }

    /**
     * Test 5 — Operator scrap execution using scrappable materials.
     */
    public function test_operator_scrap_execution_with_scrappable_materials(): void
    {
        $order = $this->createTestOrder(20, true);
        $op = $order->operations->first();

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.scrap', $op->id), [
                'product_id' => $this->rawPipe->id,
                'quantity' => 2,
                'reason' => 'Cutting Error / Wrong Dimension',
                'remarks' => 'Scrapped steel pipe due to blade deflection',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('production_order_scraps', [
            'tenant_id' => $this->tenant->id,
            'production_order_operation_id' => $op->id,
            'product_id' => $this->rawPipe->id,
            'quantity' => 2,
            'reason' => 'Cutting Error / Wrong Dimension',
        ]);
    }

    /**
     * Test 6 — Operator rework disposition execution creates Rework Order and NCR.
     */
    public function test_operator_rework_disposition_execution(): void
    {
        $order = $this->createTestOrder(20, true);
        $op = $order->operations->first();

        // Perform QC with 15 accepted and 5 rejected
        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $op->id,
            'accepted_qty' => 15,
            'rejected_qty' => 5,
            'quality_plan_id' => $this->qualityPlan->id,
            'audited_by' => $this->user->id,
        ], $this->user->id);

        $op->refresh();
        $this->assertEquals(5.0, (float) $op->quantity_rejected);

        // Submit Rework disposition
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.disposition', $op->id), [
                'disposition_type' => 'rework',
                'quantity' => 5,
                'work_center_id' => $this->workCenter->id,
                'machine_id' => $this->machine->id,
                'assigned_to' => $this->user->id,
                'rework_type' => 'repair',
                'reason' => 'Surface Scratch / Burr',
                'instructions' => 'Deburr and polish edges',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Rejected quantity should be cleared/dispositioned
        $op->refresh();
        $this->assertEquals(0.0, (float) $op->quantity_rejected);

        $this->assertDatabaseHas('production_order_reworks', [
            'tenant_id' => $this->tenant->id,
            'production_order_operation_id' => $op->id,
            'quantity' => 5,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('production_rework_orders', [
            'tenant_id' => $this->tenant->id,
            'original_production_order_id' => $order->id,
            'status' => 'draft',
        ]);
    }

    /**
     * Test 7 — Operator scrap disposition execution increments scrap and clears rejected qty.
     */
    public function test_operator_scrap_disposition_execution(): void
    {
        $order = $this->createTestOrder(20, true);
        $op = $order->operations->first();

        // Perform QC with 17 accepted and 3 rejected
        app(QualityInspectionService::class)->processShopfloorInspection($this->tenant->id, [
            'production_order_operation_id' => $op->id,
            'accepted_qty' => 17,
            'rejected_qty' => 3,
            'quality_plan_id' => $this->qualityPlan->id,
            'audited_by' => $this->user->id,
        ], $this->user->id);

        $op->refresh();
        $this->assertEquals(3.0, (float) $op->quantity_rejected);

        // Submit Scrap disposition
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.mes.disposition', $op->id), [
                'disposition_type' => 'scrap',
                'quantity' => 3,
                'reason' => 'Cracked tube beyond repair',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $op->refresh();
        $this->assertEquals(0.0, (float) $op->quantity_rejected);
        $this->assertEquals(3.0, (float) $op->quantity_scrapped);
    }
}
