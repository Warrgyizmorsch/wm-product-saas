<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEventTimeline;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\BatchProductionService;
use App\Domains\Production\Services\CapaService;
use App\Domains\Production\Services\DowntimeService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\NcrService;
use App\Domains\Production\Services\ProductionEventService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\QualityInspectionService;
use App\Domains\Production\Services\ReworkService;
use App\Domains\Production\Services\SerialNumberService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionTimelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Product $product;
    private ProductionBom $bom;
    private Routing $routing;

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

        $this->product = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Test Assembly Widget',
            'sku'       => 'WGT-001',
            'type'      => 'manufactured',
        ]);

        $this->bom = ProductionBom::create([
            'tenant_id'      => $this->tenantId,
            'product_id'     => $this->product->id,
            'bom_number'     => 'BOM-WGT-001',
            'version'        => '1.0',
            'base_quantity'  => 1.0,
            'status'         => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Main Assembly Center',
            'code'      => 'WC-001',
        ]);

        $this->machine = Machine::create([
            'tenant_id'      => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name'           => 'CNC Milling Machine',
            'code'           => 'MCH-001',
            'status'         => 'active',
        ]);

        $this->routing = Routing::create([
            'tenant_id'      => $this->tenantId,
            'product_id'     => $this->product->id,
            'name'           => 'Standard Assembly Routing',
            'routing_number' => 'RTG-WGT-001',
            'version'        => '1.0',
            'status'         => 'active',
        ]);

        \App\Domains\Production\Models\RoutingOperation::create([
            'tenant_id'        => $this->tenantId,
            'routing_id'       => $this->routing->id,
            'sequence'         => 10,
            'operation_number' => 'OP-010',
            'name'             => 'Primary Assembly Operation',
            'work_center_id'   => $this->workCenter->id,
        ]);
    }

    /** @test */
    public function event_service_creates_tenant_scoped_timeline_record()
    {
        $service = app(ProductionEventService::class);

        $event = $service->writeEvent($this->tenantId, [
            'event_type'   => 'Test Event',
            'title'        => 'Test Event Title',
            'description'  => 'Test event description',
            'severity'     => 'info',
            'event_source' => 'UnitTest',
        ]);

        $this->assertDatabaseHas('production_event_timelines', [
            'id'           => $event->id,
            'tenant_id'    => $this->tenantId,
            'event_type'   => 'Test Event',
            'event_source' => 'UnitTest',
        ]);
    }

    /** @test */
    public function order_lifecycle_emits_timeline_events()
    {
        $orderService = app(ProductionOrderService::class);

        // 1. Order Direct Creation
        $order = $orderService->createDirect([
            'product_id'       => $this->product->id,
            'quantity_ordered' => 10,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(2)->toDateString(),
        ], $this->tenantId, $this->user->id);

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $order->id,
            'event_type'          => 'Order Created',
            'event_source'        => 'ProductionOrderService',
        ]);

        // 2. Order Release
        $orderService->release($order->id, $this->user->id);

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $order->id,
            'event_type'          => 'Order Released',
            'event_source'        => 'ProductionOrderService',
        ]);

        // 3. Order Completion
        \App\Domains\Production\Models\ProductionOrderOperation::where('production_order_id', $order->id)
            ->update(['status' => \App\Domains\Production\Models\ProductionOrderOperation::STATUS_COMPLETED]);

        $orderService->complete($order->id, $this->user->id);

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $order->id,
            'event_type'          => 'Production Completed',
            'event_source'        => 'ProductionOrderService',
        ]);
    }

    /** @test */
    public function order_cancellation_emits_timeline_event()
    {
        $orderService = app(ProductionOrderService::class);

        $order = $orderService->createDirect([
            'product_id'       => $this->product->id,
            'quantity_ordered' => 5,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(2)->toDateString(),
        ], $this->tenantId, $this->user->id);

        $orderService->cancel($order->id, $this->user->id);

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $order->id,
            'event_type'          => 'Production Cancelled',
            'event_source'        => 'ProductionOrderService',
        ]);
    }

    /** @test */
    public function downtime_lifecycle_emits_downtime_events()
    {
        $downtimeService = app(DowntimeService::class);

        $downtime = $downtimeService->startDowntime(
            $this->tenantId,
            $this->machine->id,
            'Breakdown',
            'Motor overheating',
            $this->user->id
        );

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'    => $this->tenantId,
            'machine_id'   => $this->machine->id,
            'event_type'   => 'Downtime Started',
            'severity'     => 'warning',
            'event_source' => 'DowntimeService',
        ]);

        $downtimeService->endDowntime(
            $this->tenantId,
            $downtime->id,
            $this->user->id,
            'Replaced fan assembly'
        );

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'    => $this->tenantId,
            'machine_id'   => $this->machine->id,
            'event_type'   => 'Downtime Ended',
            'event_source' => 'DowntimeService',
        ]);
    }

    /** @test */
    public function batch_lifecycle_emits_batch_events()
    {
        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id'       => $this->product->id,
            'quantity_ordered' => 10,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(2)->toDateString(),
        ], $this->tenantId, $this->user->id);

        $batchService = app(BatchProductionService::class);

        $batch = $batchService->createBatch(
            $this->tenantId,
            $order->id,
            $this->product->id,
            100.0,
            ProductionBatch::STATUS_PLANNED
        );

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'           => $this->tenantId,
            'production_batch_id' => $batch->id,
            'event_type'          => 'Batch Created',
            'event_source'        => 'BatchProductionService',
        ]);
    }

    /** @test */
    public function bulk_serial_generation_creates_one_summarized_timeline_event()
    {
        $orderService = app(ProductionOrderService::class);
        $order = $orderService->createDirect([
            'product_id'       => $this->product->id,
            'quantity_ordered' => 10,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(2)->toDateString(),
        ], $this->tenantId, $this->user->id);

        $serialService = app(SerialNumberService::class);

        $serials = $serialService->generateSerials(
            $this->tenantId,
            $order->id,
            $this->product->id,
            10,
            'SN-TEST',
            1001
        );

        $this->assertCount(10, $serials);

        // Assert ONLY ONE summarized event was logged for the 10 serials
        $eventCount = ProductionEventTimeline::where('tenant_id', $this->tenantId)
            ->where('event_type', 'Serial Generated')
            ->count();

        $this->assertEquals(1, $eventCount);
    }

    /** @test */
    public function quality_inspection_and_ncr_and_capa_emit_events()
    {
        $plan = ProductionQualityPlan::create([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Standard Check Plan',
            'version'    => '1.0',
            'status'     => 'active',
            'type'       => 'in_process',
            'product_id' => $this->product->id,
            'created_by' => $this->user->id,
        ]);

        $inspectionService = app(QualityInspectionService::class);
        $ncrService = app(NcrService::class);
        $capaService = app(CapaService::class);

        // 1. Inspection Created
        $inspection = $inspectionService->createInspection($this->tenantId, [
            'quality_plan_id' => $plan->id,
            'stage'           => 'in_process',
        ]);

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'    => $this->tenantId,
            'event_type'   => 'Inspection Created',
            'event_source' => 'QualityInspectionService',
        ]);

        // 2. NCR Created
        $ncr = $ncrService->createNcr($this->tenantId, [
            'category'    => 'defect',
            'description' => 'Dimensional variance out of spec',
        ]);

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'    => $this->tenantId,
            'event_type'   => 'NCR Logged',
            'event_source' => 'NcrService',
        ]);

        // 3. CAPA Created & Closed
        $capa = $capaService->createCapa($this->tenantId, [
            'ncr_id'          => $ncr->id,
            'action_owner_id' => $this->user->id,
            'action_plan'     => 'Recalibrate tooling',
        ]);

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'    => $this->tenantId,
            'event_type'   => 'CAPA Created',
            'event_source' => 'CapaService',
        ]);

        $capaService->closeCapa($capa->id, $this->user->id, 'Tooling recalibrated and verified', 'signature123', $this->tenantId);

        $this->assertDatabaseHas('production_event_timelines', [
            'tenant_id'    => $this->tenantId,
            'event_type'   => 'CAPA Closed',
            'event_source' => 'CapaService',
        ]);
    }

    /** @test */
    public function transaction_rollback_removes_timeline_event()
    {
        try {
            DB::transaction(function () {
                app(ProductionEventService::class)->writeEvent($this->tenantId, [
                    'event_type'   => 'Rollback Test',
                    'title'        => 'Rollback Event',
                    'description'  => 'Will roll back',
                    'severity'     => 'info',
                    'event_source' => 'UnitTest',
                ]);

                throw new \Exception('Forced transaction failure');
            });
        } catch (\Exception $e) {
            // Transaction rolled back
        }

        $this->assertDatabaseMissing('production_event_timelines', [
            'tenant_id'  => $this->tenantId,
            'event_type' => 'Rollback Test',
        ]);
    }

    /** @test */
    public function tenant_isolation_hides_other_tenant_timeline_events()
    {
        $otherTenantId = 999;
        Tenant::factory()->create(['id' => $otherTenantId, 'slug' => 'other-tenant']);

        app(ProductionEventService::class)->writeEvent($otherTenantId, [
            'event_type'   => 'Other Tenant Event',
            'title'        => 'Secret Other Tenant Event',
            'description'  => 'Should not be seen',
            'severity'     => 'info',
            'event_source' => 'System',
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.timeline.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Secret Other Tenant Event');
    }

    /** @test */
    public function timeline_page_renders_with_null_relations_gracefully()
    {
        app(ProductionEventService::class)->writeEvent($this->tenantId, [
            'event_type'   => 'System Event',
            'title'        => 'System Initialized',
            'description'  => 'No order or machine attached',
            'severity'     => 'info',
            'event_source' => 'System',
        ]);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.timeline.index'));

        $response->assertStatus(200);
        $response->assertSee('System Initialized');
    }
}
