<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionKpiTarget;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionScanLog;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\DashboardRefreshService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminUser;
    private User $pmUser;
    private User $salesUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Test Tenant',
            'slug'   => 'test-tenant',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        // Define users with concrete legacy role slug in db too
        $this->adminUser = $this->createUserWithRole('admin@example.com', 'tenant_owner');
        $this->pmUser = $this->createUserWithRole('pm@example.com', 'production_manager');
        $this->salesUser = $this->createUserWithRole('exec@example.com', 'sales_executive');
    }

    private function createUserWithRole(string $email, string $roleSlug): User
    {
        $user = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => $email,
            'email'     => $email,
            'password'  => bcrypt('password'),
            'role'      => $roleSlug,
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();

        UserRole::create([
            'user_id'   => $user->id,
            'role_id'   => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        return $user;
    }

    /** @test */
    public function test_lot_traceability_requires_mes_permission(): void
    {
        // salesUser has no production.mes.execute permission
        $response = $this->actingAs($this->salesUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.traceability.index'));
        $response->assertForbidden();

        $response = $this->actingAs($this->salesUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.traceability.search', ['lot_number' => 'LOT123']));
        $response->assertForbidden();

        // pmUser has production.mes.execute permission
        $response = $this->actingAs($this->pmUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.traceability.index'));
        $response->assertOk();
    }

    /** @test */
    public function test_dashboard_refresh_service_tenant_validation(): void
    {
        $otherTenant = Tenant::create([
            'name'   => 'Other Tenant',
            'slug'   => 'other-tenant',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        // Create a machine under otherTenant
        $wcOther = WorkCenter::create([
            'tenant_id' => $otherTenant->id,
            'name'      => 'WC Other',
            'code'      => 'WC-OTHER',
            'status'    => 'active',
        ]);
        $machineOther = Machine::create([
            'tenant_id'      => $otherTenant->id,
            'work_center_id' => $wcOther->id,
            'name'           => 'Machine Other',
            'code'           => 'M-OTHER',
            'status'         => 'active',
        ]);

        $service = app(DashboardRefreshService::class);

        // Attempting to query other tenant's machine under this tenant's context should throw an exception
        try {
            $service->refreshMachineDashboard($this->tenant->id, $machineOther->id);
            $this->fail('Expected InvalidArgumentException when querying other tenant machine');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Machine not found or access denied', $e->getMessage());
        }

        // Test refreshWorkCenterDashboard metric scoping against multi-tenant machine table
        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'WC 1',
            'code'      => 'WC-1',
            'status'    => 'active',
        ]);
        Machine::create([
            'tenant_id'      => $this->tenant->id,
            'work_center_id' => $wc1->id,
            'name'           => 'Machine 1',
            'code'           => 'M-1',
            'status'         => 'active',
            'current_state'  => 'Running',
        ]);

        // Direct DB insert simulating another tenant machine with same work_center_id value
        \Illuminate\Support\Facades\DB::table('production_machines')->insert([
            'tenant_id'      => $otherTenant->id,
            'work_center_id' => $wc1->id,
            'name'           => 'Machine Foreign Tenant',
            'code'           => 'M-FOREIGN',
            'status'         => 'active',
            'current_state'  => 'Running',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $wcDash = $service->refreshWorkCenterDashboard($this->tenant->id, $wc1->id);
        $this->assertEquals(1, $wcDash['running_machines']);
        $this->assertEquals(1, $wcDash['total_machines']);
    }

    /** @test */
    public function test_create_production_order_from_plan_authorization(): void
    {
        $uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS']);
        $product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'Widget',
            'sku'            => 'WIDGET-1',
            'type'           => 'finished_good',
            'item_type'      => 'Goods',
            'variation_type' => 'Single',
            'status'         => 'active',
            'selling_price'  => 10,
            'cost_price'     => 5,
            'unit_cost'      => 5,
            'uom_id'         => $uom->id,
        ]);
        $plan = ProductionPlan::create([
            'tenant_id'        => $this->tenant->id,
            'plan_number'      => 'PLAN-001',
            'name'             => 'Test Plan',
            'status'           => 'approved',
            'start_date'       => now(),
            'end_date'         => now()->addDays(5),
            'created_by'       => $this->pmUser->id,
            'product_id'       => $product->id,
            'planned_quantity' => 100,
        ]);

        // salesUser has no permission to create production order
        $response = $this->actingAs($this->salesUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.plans.create-order', $plan->id));
        $response->assertForbidden();
    }

    /** @test */
    public function test_production_plan_create_product_query_tenant_isolation(): void
    {
        $otherTenant = Tenant::create([
            'name'   => 'Other Tenant Prod',
            'slug'   => 'other-tenant-prod',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        Product::create([
            'tenant_id' => $otherTenant->id,
            'name'      => 'Foreign Finished Good',
            'sku'       => 'FG-FOREIGN',
            'type'      => 'finished_good',
            'selling_price' => 10,
            'cost_price' => 5,
        ]);

        $localProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Local Finished Good',
            'sku'       => 'FG-LOCAL',
            'type'      => 'finished_good',
            'selling_price' => 10,
            'cost_price' => 5,
        ]);

        $response = $this->actingAs($this->pmUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.plans.create'));
        $response->assertOk();
        $response->assertViewHas('products', function ($products) use ($localProduct) {
            return $products->count() === 1 && $products->first()->id === $localProduct->id;
        });
    }

    /** @test */
    public function test_kpi_target_crud(): void
    {
        // View targets list
        $response = $this->actingAs($this->pmUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.kpi-targets.index'));
        $response->assertOk();
        $response->assertViewHas('targets');

        // Store new KPI targets
        $response = $this->actingAs($this->pmUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('production.kpi-targets.store'), [
                'oee'          => 88.50,
                'availability' => 92.00,
                'performance'  => 96.00,
                'quality'      => 99.50,
                'throughput'   => 120.00,
                'utilization'  => 85.00,
                'scrap_rate'   => 1.50,
                'downtime'     => 8.00,
            ]);

        $response->assertRedirect(route('production.kpi-targets.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('production_kpi_targets', [
            'tenant_id'    => $this->tenant->id,
            'kpi_name'     => 'oee',
            'target_value' => 88.50,
        ]);
    }

    /** @test */
    public function test_scan_logs_index_and_export(): void
    {
        // Log a scan event
        $log = ProductionScanLog::create([
            'tenant_id'         => $this->tenant->id,
            'entity_type'       => 'order',
            'entity_id'         => 1,
            'scan_type'         => 'order',
            'scanned_by'        => $this->pmUser->id,
            'device_identifier' => 'SCAN-100',
            'scanned_at'        => now(),
        ]);

        $response = $this->actingAs($this->pmUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.scan-logs.index'));
        $response->assertOk();
        $response->assertViewHas('logs');

        // Export scan logs to CSV
        \Illuminate\Support\Carbon::setTestNow(now());
        $response = $this->actingAs($this->pmUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.scan-logs.export'));
        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=production_scan_logs_' . date('Ymd_His') . '.csv');
        \Illuminate\Support\Carbon::setTestNow();
    }

    /** @test */
    public function test_model_relationships(): void
    {
        $uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS']);
        $product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'Widget',
            'sku'            => 'WIDGET-1',
            'type'           => 'finished_good',
            'item_type'      => 'Goods',
            'variation_type' => 'Single',
            'status'         => 'active',
            'selling_price'  => 10,
            'cost_price'     => 5,
            'unit_cost'      => 5,
            'uom_id'         => $uom->id,
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'ORD-MOCK-3',
            'product_id'       => $product->id,
            'quantity_ordered' => 100,
            'start_date'       => today(),
            'end_date'         => today()->addDays(5),
            'status'           => 'in_progress',
        ]);

        $batch = ProductionBatch::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $order->id,
            'batch_number'        => 'BAT-001',
            'product_id'          => $product->id,
            'status'              => 'pending',
            'planned_quantity'    => 100,
        ]);

        $ncr = ProductionNcr::create([
            'tenant_id'   => $this->tenant->id,
            'ncr_number'  => 'NCR-001',
            'category'    => 'process',
            'description' => 'Test NCR description',
            'defect_type' => 'Dimensions Out of Spec',
            'defect_qty'  => 5,
            'disposition' => 'pending',
            'status'      => 'open',
            'detected_by' => $this->pmUser->id,
            'batch_id'    => $batch->id,
        ]);

        $this->assertInstanceOf(ProductionBatch::class, $ncr->batch);
        $this->assertEquals($batch->id, $ncr->batch->id);
    }

    /** @test */
    public function test_scheduling_service_has_actuals_or_wip_tenant_isolation(): void
    {
        $otherTenant = Tenant::create([
            'name'   => 'Scheduling Tenant B',
            'slug'   => 'sched-tenant-b',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'Sched Product',
            'sku'            => 'SKU-SCHED',
            'type'           => 'finished_good',
            'item_type'      => 'Goods',
            'variation_type' => 'Single',
            'status'         => 'active',
            'selling_price'  => 10,
            'cost_price'     => 5,
        ]);

        $orderA = \App\Domains\Production\Models\ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'ORD-SCHED-A',
            'product_id'       => $product->id,
            'quantity_ordered' => 10,
            'start_date'       => today(),
            'end_date'         => today()->addDays(2),
            'status'           => 'released',
        ]);

        $scheduleA = \App\Domains\Production\Models\ProductionSchedule::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $orderA->id,
            'schedule_number'     => 'SCHED-A',
            'status'              => 'draft',
            'start_date'          => today(),
            'end_date'            => today()->addDays(2),
        ]);

        $wcSched = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'WC Sched',
            'code'      => 'WC-SCHED',
            'status'    => 'active',
        ]);

        $opOrderA = \App\Domains\Production\Models\ProductionOrderOperation::create([
            'tenant_id'           => $this->tenant->id,
            'production_order_id' => $orderA->id,
            'work_center_id'      => $wcSched->id,
            'operation_number'    => 'OP-10',
            'name'                => 'Operation 10',
            'sequence'            => 10,
            'status'              => 'ready',
        ]);

        $schedOpA = \App\Domains\Production\Models\ProductionScheduleOperation::create([
            'tenant_id'                     => $this->tenant->id,
            'production_schedule_id'        => $scheduleA->id,
            'production_order_id'           => $orderA->id,
            'production_order_operation_id' => $opOrderA->id,
            'work_center_id'                => $wcSched->id,
            'sequence'                      => 10,
            'planned_start'                 => now(),
            'planned_finish'                => now()->addHour(),
        ]);

        $productB = Product::create([
            'tenant_id'      => $otherTenant->id,
            'name'           => 'Sched Product B',
            'sku'            => 'SKU-SCHED-B',
            'type'           => 'finished_good',
            'item_type'      => 'Goods',
            'variation_type' => 'Single',
            'status'         => 'active',
            'selling_price'  => 10,
            'cost_price'     => 5,
        ]);

        $orderB = \App\Domains\Production\Models\ProductionOrder::create([
            'tenant_id'        => $otherTenant->id,
            'order_number'     => 'ORD-SCHED-B',
            'product_id'       => $productB->id,
            'quantity_ordered' => 10,
            'start_date'       => today(),
            'end_date'         => today()->addDays(2),
            'status'           => 'released',
        ]);

        // Cross-tenant WIP belonging to otherTenant referencing same schedOpA ID integer
        \App\Domains\Production\Models\ProductionWip::create([
            'tenant_id'                      => $otherTenant->id,
            'production_order_id'            => $orderB->id,
            'product_id'                     => $productB->id,
            'current_schedule_operation_id' => $schedOpA->id,
            'status'                         => 'active',
            'available_quantity'             => 10,
        ]);

        $service = app(\App\Domains\Production\Services\SchedulingService::class);
        $hasActuals = $service->hasExecutionHistory($scheduleA);

        $this->assertFalse($hasActuals, 'Cross-tenant WIP record should not trigger hasExecutionHistory for Tenant A schedule.');
    }

    /** @test */
    public function test_repeated_material_reservation_and_requisition_aggregation(): void
    {
        $uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Meters', 'code' => 'MTR']);
        $product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'name'           => 'Steel Square Pipe',
            'sku'            => 'RM-PIPE',
            'type'           => 'raw_material',
            'item_type'      => 'Goods',
            'variation_type' => 'Single',
            'status'         => 'active',
            'selling_price'  => 10,
            'cost_price'     => 5,
            'unit_cost'      => 5,
            'uom_id'         => $uom->id,
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenant->id,
            'order_number'     => 'ORD-AGG-001',
            'product_id'        => $product->id,
            'quantity_ordered' => 2,
            'start_date'       => today(),
            'end_date'         => today()->addDays(2),
            'status'           => 'draft',
        ]);

        $orderService = app(\App\Domains\Production\Services\ProductionOrderService::class);

        // Add material reservation twice for the same product (6.0m + 2.6m)
        $refMethod = new \ReflectionMethod($orderService, 'createMaterialReservation');
        $refMethod->setAccessible(true);
        $refMethod->invoke($orderService, $order, null, $product->id, 6.0, $uom->id, null);
        $refMethod->invoke($orderService, $order, null, $product->id, 2.6, $uom->id, null);

        $reservations = \App\Domains\Production\Models\ProductionOrderReservation::where('production_order_id', $order->id)->get();
        $this->assertCount(1, $reservations, 'Repeated materials should consolidate into a single reservation line.');
        $this->assertEquals(8.6, (float) $reservations->first()->quantity_planned);
    }
}
