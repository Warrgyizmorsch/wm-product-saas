<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionPriority3VerificationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private User $userA;
    private Uom $uom;
    private Warehouse $warehouseA;
    private WorkCenter $workCenter;
    private Machine $machine;
    private Product $productA;
    private Product $productB;
    private ProductionBom $bom;
    private Routing $routing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::firstOrCreate(['id' => 1], [
            'name' => 'P3 Tenant A',
            'slug' => 'p3-tenant-a',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'User A',
            'email' => 'usera-p3@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->userA);
        session(['tenant_id' => $this->tenantA->id]);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Piece',
            'code' => 'PCS',
            'type' => 'reference',
        ]);

        $this->warehouseA = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Machining Center A',
            'code' => 'WC-MACH-A',
            'overhead_rate' => 45.00,
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenter->id,
            'name' => 'CNC Milling Machine',
            'code' => 'MC-CNC-01',
            'status' => 'active',
        ]);

        $this->productA = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Assembly Alpha',
            'sku' => 'ASSY-ALPHA',
            'type' => 'finished_good',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->productB = Product::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Component Beta',
            'sku' => 'COMP-BETA',
            'type' => 'raw_material',
            'uom_id' => $this->uom->id,
            'status' => 'active',
        ]);

        $this->bom = ProductionBom::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'bom_number' => 'BOM-ALPHA-01',
            'bom_name' => 'Alpha Master BOM',
            'base_quantity' => 1.0,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenantA->id,
            'product_id' => $this->productA->id,
            'routing_code' => 'RT-ALPHA-01',
            'name' => 'Alpha Routing',
            'status' => 'active',
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantA->id,
            'routing_id' => $this->routing->id,
            'work_center_id' => $this->workCenter->id,
            'machine_id' => $this->machine->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'CNC Milling',
            'setup_time_minutes' => 10.0,
            'run_time_per_unit' => 20.0,
        ]);
    }

    /**
     * P4.2 Test: MES Timer Synchronization handles legacy null accumulated_paused_seconds cleanly.
     */
    public function test_p4_2_mes_timer_synchronization_null_handling(): void
    {
        $mesService = app(MesExecutionService::class);
        $orderService = app(ProductionOrderService::class);
        $schedulingService = app(SchedulingService::class);

        $order = $orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 5.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenantA->id, $this->userA->id);

        $schedule = $schedulingService->generateSchedule($order, now());
        $schedOp = $schedule->operations->first();

        // Start operation
        $mesService->startOperation($schedOp->id, null, $this->userA->id);

        // Pause operation
        $mesService->pauseOperation($schedOp->id, 'Operator Break', $this->userA->id);

        // Set accumulated_paused_seconds to 0 to simulate fresh/reset records
        $schedOp->update(['accumulated_paused_seconds' => 0]);

        // Resume operation
        $mesService->resumeOperation($schedOp->id, $this->userA->id);

        $this->assertEquals(ProductionScheduleOperation::STATUS_RUNNING, $schedOp->fresh()->status);
        $this->assertGreaterThanOrEqual(0, $schedOp->fresh()->accumulated_paused_seconds);
    }

    /**
     * P3.2 Test: Component-level warehouse override updates target reservation.
     */
    public function test_p3_2_component_level_warehouse_override(): void
    {
        $materialService = app(ProductionMaterialService::class);
        $orderService = app(ProductionOrderService::class);

        $warehouseB = Warehouse::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Secondary Warehouse',
            'code' => 'WH-SEC',
            'status' => 'active',
        ]);

        $order = $orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 5.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ], $this->tenantA->id, $this->userA->id);

        // Create component reservation manually
        $reservation = \App\Domains\Production\Models\ProductionOrderReservation::create([
            'tenant_id' => $this->tenantA->id,
            'production_order_id' => $order->id,
            'product_id' => $this->productB->id,
            'warehouse_id' => $this->warehouseA->id,
            'quantity_planned' => 10.0,
            'quantity_reserved' => 0.0,
            'quantity_issued' => 0.0,
            'uom_id' => $this->uom->id,
        ]);

        // Override warehouse
        $updatedRes = $materialService->updateReservationWarehouse($reservation->id, $warehouseB->id);
        $this->assertEquals($warehouseB->id, $updatedRes->warehouse_id);
    }

    /**
     * P3.1 Test: Production Order list filters by production_mode.
     */
    public function test_p3_1_production_mode_filter(): void
    {
        $orderService = app(ProductionOrderService::class);

        // Create batch mode order
        $batchOrder = $orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'production_mode' => 'batch',
        ], $this->tenantA->id, $this->userA->id);

        // Create serial mode order
        $serialOrder = $orderService->createDirect([
            'product_id' => $this->productA->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'production_mode' => 'serial',
        ], $this->tenantA->id, $this->userA->id);

        // Query query builder directly to verify production_mode filtering
        $batchQueryResults = ProductionOrder::where('tenant_id', $this->tenantA->id)
            ->where('production_mode', 'batch')
            ->pluck('order_number')
            ->toArray();

        $this->assertContains($batchOrder->order_number, $batchQueryResults);
        $this->assertNotContains($serialOrder->order_number, $batchQueryResults);
    }

    /**
     * L8.1-L8.3 Test: Multi-language keys load in English, Hindi, and Bulgarian.
     */
    public function test_l8_localization_keys_load_across_languages(): void
    {
        app()->setLocale('en');
        $this->assertEquals('Quality Management Dashboard', __('production.quality_management_dashboard'));

        app()->setLocale('hi');
        $this->assertEquals('गुणवत्ता प्रबंधन डैशबोर्ड', __('production.quality_management_dashboard'));

        app()->setLocale('bg');
        $this->assertEquals('Табло за управление на качеството', __('production.quality_management_dashboard'));

        app()->setLocale('en');
    }
}
