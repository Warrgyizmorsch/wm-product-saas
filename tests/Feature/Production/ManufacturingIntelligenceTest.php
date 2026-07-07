<?php

namespace Tests\Feature\Production;

use App\Models\User;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionMachineStateHistory;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionKpiTarget;
use App\Domains\Production\Models\ProductionDashboardPreference;
use App\Domains\Production\Services\OeeCalculationService;
use App\Domains\Production\Services\KpiCalculationService;
use App\Domains\Production\Services\DashboardPreferenceService;
use App\Domains\Production\Services\DashboardRefreshService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturingIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;
    private WorkCenter $workCenter;
    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base data
        \App\Models\Tenant::factory()->create([
            'id' => $this->tenantId,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        $this->actingAs($this->user);

        $this->workCenter = WorkCenter::create([
            'tenant_id'     => $this->tenantId,
            'name'          => 'CNC Work Center',
            'code'          => 'WC-CNC',
            'overhead_rate' => 50.00
        ]);

        $this->machine = Machine::create([
            'tenant_id'      => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name'           => 'CNC Machine 01',
            'code'           => 'MC-CNC-01',
            'status'         => 'active',
            'current_state'  => 'Idle'
        ]);
    }

    /**
     * Test OEE & Six Big Loss calculations.
     */
    public function test_oee_and_six_big_losses_calculations(): void
    {
        $oeeService = app(OeeCalculationService::class);
        $start = Carbon::today()->startOfDay();
        $end = Carbon::today()->endOfDay();

        // 1. Create a closed downtime event (Setup & Adjustment category)
        ProductionMachineDowntime::create([
            'tenant_id'       => $this->tenantId,
            'machine_id'      => $this->machine->id,
            'work_center_id'  => $this->workCenter->id,
            'category'        => 'Setup',
            'reason'          => 'Molds alignment adjustment',
            'start_time'      => Carbon::today()->addHours(1),
            'end_time'        => Carbon::today()->addHours(1)->addMinutes(30),
            'duration_minutes'=> 30,
            'status'          => 'resolved',
            'reported_by'     => $this->user->id,
            'created_by'      => $this->user->id,
        ]);

        // 2. Create state history with short duration (Minor Stops category)
        ProductionMachineStateHistory::create([
            'tenant_id'       => $this->tenantId,
            'machine_id'      => $this->machine->id,
            'state'           => 'Idle',
            'started_at'      => Carbon::today()->addHours(2),
            'ended_at'        => Carbon::today()->addHours(2)->addMinutes(3),
            'duration_seconds'=> 180, // 3 minutes minor stop
        ]);

        // 3. Create dummy order dependencies
        $uom = \App\Domains\Inventory\Models\Uom::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Units',
            'code'      => 'PCS',
            'type'      => 'reference'
        ]);

        $product = \App\Domains\Inventory\Models\Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Metal Bolt A',
            'sku'       => 'FG-BOLT-01',
            'type'      => 'finished_good',
            'unit_cost' => 10.00,
            'status'    => 'active',
        ]);

        $bom = \App\Domains\Production\Models\ProductionBom::create([
            'tenant_id'      => $this->tenantId,
            'product_id'     => $product->id,
            'bom_number'     => 'BOM-BOLT-001',
            'bom_name'       => 'Bolt Standard BOM',
            'bom_type'       => 'manufacturing',
            'base_quantity'  => 1.0,
            'base_uom_id'    => $uom->id,
            'version'        => '1.0.0',
            'status'         => 'approved',
            'effective_date' => date('Y-m-d'),
        ]);

        $routing = \App\Domains\Production\Models\Routing::create([
            'tenant_id'      => $this->tenantId,
            'product_id'     => $product->id,
            'routing_number' => 'RT-BOLT-01',
            'name'           => 'Bolt Routing',
            'version'        => '1.0.0',
            'status'         => 'active',
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $this->tenantId,
            'order_number'     => 'PO-TEST-002',
            'product_id'       => $product->id,
            'bom_id'           => $bom->id,
            'routing_id'       => $routing->id,
            'quantity_ordered' => 20,
            'status'           => ProductionOrder::STATUS_RELEASED,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(2)->toDateString(),
        ]);

        $op = ProductionOrderOperation::create([
            'tenant_id'               => $this->tenantId,
            'production_order_id'     => $order->id,
            'sequence'                => 10,
            'operation_number'        => 'OP10',
            'name'                    => 'Milling',
            'work_center_id'          => $this->workCenter->id,
            'machine_id'              => $this->machine->id,
            'status'                  => ProductionOrderOperation::STATUS_RUNNING,
            'setup_time_planned'      => 10,
            'processing_time_planned' => 2.5,
            'total_time_planned'      => 12.5,
        ]);

        // 4. Log progress with scraps/rejects
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $order->id,
            'operation_id'        => $op->id,
            'machine_id'          => $this->machine->id,
            'operator_id'         => $this->user->id,
            'quantity_produced'   => 10,
            'quantity_rejected'   => 1,
            'quantity_scrapped'   => 1,
            'recorded_at'         => Carbon::today()->addHours(3),
        ]);

        $metrics = $oeeService->calculateForMachine($this->tenantId, $this->machine->id, $start, $end);
        $losses  = $oeeService->calculateSixBigLosses($this->tenantId, $this->machine->id, $start, $end);

        $this->assertIsArray($metrics);
        $this->assertEquals(30.00, $metrics['downtime_minutes']);
        $this->assertEquals(10.00, $metrics['total_produced']);
        $this->assertEquals(97.92, $metrics['availability']);

        // Verify Six Big Losses
        $this->assertEquals(30.00, $losses['setup_adjustment_minutes']);
        $this->assertEquals(3.00, $losses['minor_stops_minutes']);
        $this->assertEquals(1.00, $losses['startup_rejects_count']);
    }

    /**
     * Test KPI Targets vs Variance tracking.
     */
    public function test_kpi_targets_and_variance_tracking(): void
    {
        $kpiService = app(KpiCalculationService::class);

        // 1. Create a custom OEE target
        ProductionKpiTarget::create([
            'tenant_id'    => $this->tenantId,
            'kpi_name'     => 'oee',
            'target_value' => 80.00,
        ]);

        // 2. Query target vs variance for OEE = 85.00
        $result = $kpiService->getKpiWithTargetsAndVariance($this->tenantId, 'oee', 85.00);

        $this->assertEquals(80.00, $result['target_value']);
        $this->assertEquals(5.00, $result['variance']);
        $this->assertEquals('Above Target', $result['status']);
    }

    /**
     * Test User Dashboard preferences storage & widget order.
     */
    public function test_user_dashboard_preferences_saving_and_loading(): void
    {
        $prefService = app(DashboardPreferenceService::class);

        // 1. Save preferences
        $prefService->savePreferences($this->tenantId, $this->user->id, 'executive', [
            'widgets' => ['today_oee', 'scrap_rejects'],
            'layout'  => 'default',
        ]);

        // 2. Load preferences
        $prefs = $prefService->getPreferences($this->tenantId, $this->user->id, 'executive');

        $this->assertEquals(['today_oee', 'scrap_rejects'], $prefs['widgets']);
        $this->assertEquals('default', $prefs['layout']);
    }

    /**
     * Test Dashboard Refresh Service polling execution.
     */
    public function test_dashboard_refresh_service_execution(): void
    {
        $refreshService = app(DashboardRefreshService::class);

        $result = $refreshService->refreshExecutiveDashboard($this->tenantId, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('today_oee', $result);
        $this->assertArrayHasKey('production_summary', $result);
        $this->assertArrayHasKey('utilizations', $result);
    }
}
