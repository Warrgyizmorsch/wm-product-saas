<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Batch as InventoryBatch;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\CapacityPlanningService;
use App\Domains\Production\Services\LotTraceabilityService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSchedulingAndTraceabilityWiringTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;
    private WorkCenter $workCenter;
    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::factory()->create([
            'id'   => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role'      => 'admin',
        ]);
        $this->actingAs($this->user);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Assembly Station Alpha',
            'code'      => 'WC-STA-A',
            'status'    => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'      => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name'           => 'CNC Router',
            'code'           => 'MCH-CNC-R',
            'status'         => 'active',
        ]);
    }

    /** @test */
    public function forward_and_backward_scheduling_service_generates_tenant_scoped_schedules()
    {
        $product = \App\Domains\Inventory\Models\Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Component X',
            'sku'       => 'CMP-X',
            'type'      => 'manufactured',
        ]);

        $bom = ProductionBom::create([
            'tenant_id'      => $this->tenantId,
            'product_id'     => $product->id,
            'bom_number'     => 'BOM-CMP-X',
            'version'        => '1.0',
            'base_quantity'  => 1.0,
            'status'         => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        $routing = Routing::create([
            'tenant_id'      => $this->tenantId,
            'product_id'     => $product->id,
            'name'           => 'Component X Routing',
            'routing_number' => 'RTG-CMP-X',
            'version'        => '1.0',
            'status'         => 'active',
        ]);

        \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id'        => $this->tenantId,
            'routing_id'       => $routing->id,
            'sequence'         => 10,
            'operation_number' => 'OP-10',
            'name'             => 'Machining',
            'work_center_id'   => $this->workCenter->id,
        ]);

        $orderService = app(\App\Domains\Production\Services\ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id'       => $product->id,
            'quantity_ordered' => 5,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(3)->toDateString(),
        ], $this->tenantId, $this->user->id);

        $orderService->release($order->id, $this->user->id);

        $schedulingService = app(SchedulingService::class);

        // 1. Forward Scheduling
        $forwardSchedule = $schedulingService->generateSchedule($order, now(), ProductionSchedule::TYPE_FORWARD);
        $this->assertEquals($this->tenantId, $forwardSchedule->tenant_id);
        $this->assertEquals(ProductionSchedule::TYPE_FORWARD, $forwardSchedule->scheduling_type);

        // 2. Backward Scheduling
        $backwardSchedule = $schedulingService->generateSchedule($order, now()->addDays(5), ProductionSchedule::TYPE_BACKWARD);
        $this->assertEquals($this->tenantId, $backwardSchedule->tenant_id);
        $this->assertEquals(ProductionSchedule::TYPE_BACKWARD, $backwardSchedule->scheduling_type);
    }

    /** @test */
    public function capacity_planning_service_is_read_only_and_calculates_work_center_capacity()
    {
        $capacityService = app(CapacityPlanningService::class);

        $workCenterLoads = $capacityService->getWorkCenterCapacity($this->tenantId, now()->startOfDay(), now()->addDays(7)->endOfDay());

        $this->assertIsArray($workCenterLoads);
        $this->assertNotEmpty($workCenterLoads);
        $this->assertEquals('Assembly Station Alpha', $workCenterLoads[0]['work_center']->name);
    }

    /** @test */
    public function lot_traceability_service_performs_forward_backward_and_genealogy_traces()
    {
        $product = \App\Domains\Inventory\Models\Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Item Trace Test',
            'sku'       => 'SKU-TRC-01',
            'type'      => 'manufactured',
        ]);

        $order = ProductionOrder::create([
            'tenant_id'           => $this->tenantId,
            'order_number'        => 'ORD-TRC-001',
            'product_id'          => $product->id,
            'quantity_ordered'    => 10,
            'quantity_produced'   => 0,
            'status'              => ProductionOrder::STATUS_DRAFT,
            'start_date'          => now()->toDateString(),
            'end_date'            => now()->addDays(5)->toDateString(),
        ]);

        $batch = ProductionBatch::create([
            'tenant_id'           => $this->tenantId,
            'batch_number'        => 'BAT-2026-000100',
            'production_order_id' => $order->id,
            'product_id'          => $product->id,
            'planned_quantity'    => 10.0,
            'actual_quantity'     => 0.0,
            'status'              => 'planned',
        ]);

        $traceService = app(LotTraceabilityService::class);

        $forward  = $traceService->forwardTrace($this->tenantId, 'batch', $batch->id);
        $backward = $traceService->backwardTrace($this->tenantId, 'batch', $batch->id);
        $both     = $traceService->buildGenealogy($this->tenantId, 'batch', $batch->id);

        $this->assertIsArray($forward['nodes']);
        $this->assertIsArray($backward['nodes']);
        $this->assertIsArray($both['nodes']);
    }

    /** @test */
    public function cross_tenant_production_orders_and_batches_are_inaccessible_in_traceability()
    {
        $otherTenantId = 999;
        Tenant::factory()->create(['id' => $otherTenantId, 'slug' => 'other-tenant']);

        $productOther = \App\Domains\Inventory\Models\Product::create([
            'tenant_id' => $otherTenantId,
            'name'      => 'Other Item',
            'sku'       => 'SKU-OTH-01',
            'type'      => 'manufactured',
        ]);

        $orderOther = ProductionOrder::create([
            'tenant_id'         => $otherTenantId,
            'order_number'      => 'ORD-OTH-001',
            'product_id'        => $productOther->id,
            'quantity_ordered'  => 10,
            'quantity_produced' => 0,
            'status'            => ProductionOrder::STATUS_DRAFT,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(5)->toDateString(),
        ]);

        $otherBatch = ProductionBatch::create([
            'tenant_id'           => $otherTenantId,
            'batch_number'        => 'BAT-OTHER-001',
            'production_order_id' => $orderOther->id,
            'product_id'          => $productOther->id,
            'planned_quantity'    => 10.0,
            'actual_quantity'     => 0.0,
            'status'              => 'planned',
        ]);

        // Attempting to search other tenant's batch from tenant 1 context returns redirect with error
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.traceability.search', [
                'type' => 'batch',
                'code' => 'BAT-OTHER-001',
            ]));

        $response->assertRedirect(route('production.mes.traceability.index'));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function traceability_page_renders_with_direction_filter()
    {
        $product = \App\Domains\Inventory\Models\Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Render Item Test',
            'sku'       => 'SKU-RND-01',
            'type'      => 'manufactured',
        ]);

        $order = ProductionOrder::create([
            'tenant_id'         => $this->tenantId,
            'order_number'      => 'ORD-RND-001',
            'product_id'        => $product->id,
            'quantity_ordered'  => 10,
            'quantity_produced' => 0,
            'status'            => ProductionOrder::STATUS_DRAFT,
            'start_date'        => now()->toDateString(),
            'end_date'          => now()->addDays(5)->toDateString(),
        ]);

        $batch = ProductionBatch::create([
            'tenant_id'           => $this->tenantId,
            'batch_number'        => 'BAT-2026-000200',
            'production_order_id' => $order->id,
            'product_id'          => $product->id,
            'planned_quantity'    => 10.0,
            'actual_quantity'     => 0.0,
            'status'              => 'planned',
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.traceability.search', [
                'type'      => 'batch',
                'code'      => 'BAT-2026-000200',
                'direction' => 'forward',
            ]));

        $response->assertStatus(200);
        $response->assertSee('BAT-2026-000200');
        $response->assertSee('Export CSV');
    }
}
