<?php

namespace Tests\Feature\Production;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\QualityInspectionService;
use App\Domains\Inventory\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimplifiedQualityControlWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Product $product;
    private Warehouse $warehouse;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Routing $routing;
    private RoutingOperation $routingOp;
    private ProductionOrder $order;
    private ProductionOrderOperation $orderOp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->actingAs($this->user);
        $this->withHeaders(['X-Tenant' => $this->tenant->slug]);

        app()->instance('tenant.id', $this->tenant->id);
        session(['tenant_id' => $this->tenant->id]);

        $this->warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main FG Warehouse',
            'code' => 'WH-FG-01',
            'is_default' => true,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Finished Widget Component',
            'sku' => 'FWIDGET-001',
            'type' => 'finished_good',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assembly Work Center',
            'code' => 'WC-ASSY-01',
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'name' => 'Assembly Station 1',
            'code' => 'MAC-ASSY-01',
            'status' => 'available',
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'name' => 'Widget Assembly Routing',
            'code' => 'ROUT-WIDGET-01',
            'version' => '1.0',
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->routingOp = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $this->routing->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Final Quality & Assembly Check',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'quality_required' => true,
        ]);

        $this->order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'ORD-2026-QC001',
            'product_id' => $this->product->id,
            'quantity_ordered' => 6.00,
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'production_mode' => 'batch',
            'start_date' => now(),
            'end_date' => now()->addDays(5),
            'planned_start_date' => now(),
            'planned_end_date' => now()->addDays(5),
        ]);

        $this->orderOp = ProductionOrderOperation::create([
            'tenant_id' => $this->tenant->id,
            'production_order_id' => $this->order->id,
            'routing_operation_id' => $this->routingOp->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Final Quality & Assembly Check',
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'status' => ProductionOrderOperation::STATUS_RUNNING,
            'quantity_produced' => 0.0,
        ]);
    }

    public function test_operator_can_run_inline_quick_quality_inspection(): void
    {
        $inspectionService = app(QualityInspectionService::class);

        $inspection = $inspectionService->quickOperatorInspection($this->tenant->id, [
            'production_order_operation_id' => $this->orderOp->id,
            'production_order_id' => $this->order->id,
            'result' => 'passed',
            'remarks' => 'All assembly tolerances within limit.',
        ], $this->user->id);

        $this->assertInstanceOf(ProductionQualityInspection::class, $inspection);
        $this->assertEquals('approved', $inspection->status);
        $this->assertEquals('passed', $inspection->result);
        $this->assertEquals($this->orderOp->id, $inspection->production_order_operation_id);

        $this->assertDatabaseHas('production_quality_inspections', [
            'tenant_id' => $this->tenant->id,
            'production_order_operation_id' => $this->orderOp->id,
            'result' => 'passed',
            'status' => 'approved',
        ]);
    }

    public function test_operation_with_quality_required_completes_after_inline_qc(): void
    {
        $executionService = app(ProductionExecutionService::class);
        $inspectionService = app(QualityInspectionService::class);

        // Run quick operator QC
        $inspectionService->quickOperatorInspection($this->tenant->id, [
            'production_order_operation_id' => $this->orderOp->id,
            'production_order_id' => $this->order->id,
            'result' => 'passed',
            'remarks' => 'Passed inline QC check.',
        ], $this->user->id);

        // Now completion succeeds without quality gate error
        $executionService->logProgress($this->orderOp->id, 6.00, 0.00, 0.00, 0, 10, 'Completed with QC', null, $this->user->id, true);

        $this->orderOp->refresh();
        $this->assertEquals(ProductionOrderOperation::STATUS_COMPLETED, $this->orderOp->status);
        $this->assertEquals(6.00, (float) $this->orderOp->quantity_produced);
    }

    public function test_quick_qc_endpoint_handles_http_request(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['tenant_id' => $this->tenant->id])
            ->post(route('production.quality.inspections.quick'), [
                'production_order_operation_id' => $this->orderOp->id,
                'production_order_id' => $this->order->id,
                'result' => 'passed',
                'remarks' => 'HTTP Quick inspection test.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('production_quality_inspections', [
            'tenant_id' => $this->tenant->id,
            'production_order_operation_id' => $this->orderOp->id,
            'result' => 'passed',
        ]);
    }
}
