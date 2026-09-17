<?php

namespace Tests\Feature;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderRequest;
use App\Domains\Production\Models\ProductionPlan;
use App\Domains\Production\Models\ProductionPmSchedule;
use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1 & 17: Preserved baseline test - dashboard renders successfully with metrics and pending orders.
     */
    public function test_production_dashboard_renders_successfully_with_metrics_and_pending_sales_orders(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Test Radiator Core',
            'sku'       => 'PR-CORE-001',
            'type'      => 'finished_good',
            'unit'      => 'pcs',
        ]);

        $request = ProductionOrderRequest::create([
            'tenant_id'          => $tenant->id,
            'product_id'         => $product->id,
            'quantity_requested' => 10.0,
            'status'             => 'draft',
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $tenant->id,
            'order_number'     => 'ORD-2026-TEST-001',
            'product_id'       => $product->id,
            'quantity_ordered' => 10.0,
            'status'           => 'released',
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(3)->toDateString(),
        ]);

        ProductionRequisitionSlip::create([
            'tenant_id'           => $tenant->id,
            'production_order_id' => $order->id,
            'requisition_number'  => 'REQ-TEST-001',
            'requisition_date'    => now()->toDateString(),
            'status'              => 'Fully Issued',
            'requested_by'        => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Production Dashboard');
        $response->assertSee('Pending Sales Orders to Manufacture');
        $response->assertSee('Ready To Start');
        $response->assertSee($order->order_number);
    }

    /**
     * 3: Unauthorized / unauthenticated access check.
     */
    public function test_unauthenticated_user_is_redirected_from_production_dashboard(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertRedirect(route('login'));
    }

    /**
     * 2: Tenant isolation test: orders from other tenants must never be visible.
     */
    public function test_production_dashboard_tenant_isolation(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'admin']);

        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

        $productB = Product::create([
            'tenant_id' => $tenantB->id,
            'name'      => 'Secret Tenant B Widget',
            'sku'       => 'SKU-TENANT-B',
            'type'      => 'finished_good',
            'unit'      => 'pcs',
        ]);

        $orderB = ProductionOrder::create([
            'tenant_id'        => $tenantB->id,
            'order_number'     => 'ORD-SECRET-TENANT-B',
            'product_id'       => $productB->id,
            'quantity_ordered' => 500.0,
            'status'           => 'released',
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenantA->id])
            ->withHeader('X-Tenant', $tenantA->slug)
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('ORD-SECRET-TENANT-B');
        $response->assertDontSee('Secret Tenant B Widget');
    }

    /**
     * 4, 5, 6, 7, 8: Action Center surfaces overdue orders, machine breakdowns, quality holds, and material blockers.
     */
    public function test_critical_action_center_surfaces_exceptions(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Action Center Assembly',
            'sku'       => 'SKU-AC-001',
            'type'      => 'finished_good',
            'unit'      => 'pcs',
        ]);

        // 1. Overdue order (end date 5 days ago)
        $overdueOrder = ProductionOrder::create([
            'tenant_id'        => $tenant->id,
            'order_number'     => 'ORD-OVERDUE-999',
            'product_id'       => $product->id,
            'quantity_ordered' => 25.0,
            'status'           => 'in_progress',
            'start_date'       => now()->subDays(10)->toDateString(),
            'end_date'         => now()->subDays(5)->toDateString(),
        ]);

        // 2. Machine Breakdown
        $wc = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Stamping Center',
            'code'      => 'WC-STAMP',
            'status'    => 'active',
        ]);

        Machine::create([
            'tenant_id'      => $tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'Press Machine 01',
            'code'           => 'MCH-PRS-01',
            'status'         => 'under_maintenance',
            'current_state'  => 'Breakdown',
        ]);

        // 3. Pending Quality Plan and Inspection
        $plan = ProductionQualityPlan::create([
            'tenant_id' => $tenant->id,
            'name'      => 'In-Process QA Plan',
            'version'   => 1,
            'status'     => 'active',
            'type'       => 'in_process',
            'created_by' => $user->id,
        ]);

        ProductionQualityInspection::create([
            'tenant_id'           => $tenant->id,
            'quality_plan_id'     => $plan->id,
            'production_order_id' => $overdueOrder->id,
            'inspection_number'   => 'QC-INSP-001',
            'stage'               => 'in_process',
            'status'              => 'pending',
            'inspection_date'     => now()->toDateString(),
        ]);

        ProductionNcr::create([
            'tenant_id'   => $tenant->id,
            'ncr_number'  => 'NCR-2026-001',
            'status'      => 'open',
            'category'    => 'internal',
            'description' => 'Dimensional defect on press line',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Critical Action Center');
        $response->assertSee('Overdue Orders');
        $response->assertSee('Machine Breakdown');
        $response->assertSee('Pending QC & NCR');
        $response->assertSee('ORD-OVERDUE-999');
    }

    /**
     * 9, 10, 16: Executive KPI values sourced from existing services, operational WIP count shown, no monetary WIP valuation.
     */
    public function test_executive_kpis_and_operational_wip_render_correctly_without_monetary_valuation(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name'      => 'WIP Test Unit',
            'sku'       => 'WIP-TEST-001',
            'type'      => 'finished_good',
            'unit'      => 'pcs',
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $tenant->id,
            'order_number'     => 'ORD-WIP-001',
            'product_id'       => $product->id,
            'quantity_ordered' => 50.0,
            'status'           => 'in_progress',
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(7)->toDateString(),
        ]);

        // Create operational WIP record
        ProductionWip::create([
            'tenant_id'           => $tenant->id,
            'production_order_id' => $order->id,
            'product_id'          => $product->id,
            'quantity'            => 50.0,
            'available_quantity'  => 45.0,
            'status'              => 'active',
            'total_value'         => 1234567.89, // Hypothetical monetary value that must NOT be exposed as an authoritative financial KPI
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Active Orders Pipeline');
        $response->assertSee('Volume & Adherence');
        $response->assertSee('Plant OEE Score');
        $response->assertSee('Shop Floor WIP');
        $response->assertSee('Active Tracking Jobs');
        // Assert monetary valuation is NOT shown on the KPI header
        $response->assertDontSee('$1,234,567.89');
        $response->assertDontSee('1,234,567.89');
    }

    /**
     * 11, 12, 13, 14, 15: Operational worklist tabs render cleanly with empty states when data is absent.
     */
    public function test_operational_worklist_tabs_and_empty_states(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Pending Demand');
        $response->assertSee('Ready To Start');
        $response->assertSee('Active In-Progress');
        $response->assertSee('At-Risk / Overdue');
        // Empty states should be present
        $response->assertSee('All sales order demands have active Production Orders created.');
        $response->assertSee('No orders currently waiting in Ready-to-Start status.');
        $response->assertSee('Zero Overdue Orders! All production schedules are within target delivery dates.');
    }

    /**
     * Phase 2 - RBAC check (Audit Recommendation F-03): Authenticated user without production permissions receives HTTP 403.
     */
    public function test_authenticated_unauthorized_user_receives_403(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'operator']);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(403);
    }

    /**
     * Phase 2 - Live Machine-State Pulse & Andon Integration.
     */
    public function test_live_machine_state_and_andon_pulse_renders_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $wc = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Precision Milling Cell',
            'code'      => 'WC-MILL-01',
            'status'    => 'active',
        ]);

        Machine::create([
            'tenant_id'      => $tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'CNC Milling Center 1',
            'code'           => 'MCH-CNC-01',
            'status'         => 'active',
            'current_state'  => 'Running',
        ]);

        Machine::create([
            'tenant_id'            => $tenant->id,
            'work_center_id'       => $wc->id,
            'name'                 => 'CNC Milling Center 2',
            'code'                 => 'MCH-CNC-02',
            'status'               => 'under_maintenance',
            'current_state'        => 'Breakdown',
            'current_state_reason' => 'Spindle motor overheating',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Live Manufacturing Pulse & Andon Status');
        $response->assertSee('Running');
        $response->assertSee('Breakdown');
        $response->assertSee('MCH-CNC-02');
        $response->assertSee('CNC Milling Center 2');
        $response->assertSee('Equipment Requiring Immediate Attention');
        $response->assertSee('Spindle motor overheating');
        $response->assertSee('Live Andon Board');
    }

    /**
     * Phase 2 - Work Center Capacity & Bottleneck Indicators.
     */
    public function test_work_center_capacity_and_bottleneck_indicators_render(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $wc = WorkCenter::create([
            'tenant_id'             => $tenant->id,
            'name'                  => 'Assembly Packaging Center',
            'code'                  => 'WC-ASY-01',
            'status'                => 'active',
            'capacity_per_hour'     => 10.0,
            'efficiency_percentage' => 100.0,
        ]);

        Machine::create([
            'tenant_id'      => $tenant->id,
            'work_center_id' => $wc->id,
            'name'           => 'Packing Unit A',
            'code'           => 'MCH-PCK-01',
            'status'         => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Work-Center Capacity & Bottleneck Pulse');
        $response->assertSee('Assembly Packaging Center');
        $response->assertSee('WC-ASY-01');
        $response->assertSee('Zero Bottlenecks Active!');
        $response->assertSee('Capacity Balanced');
    }

    /**
     * Phase 2 - Live MES Shop-Floor Execution Pulse.
     */
    public function test_live_mes_shop_floor_pulse_renders_active_operations(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name'      => 'MES Sensor Assembly',
            'sku'       => 'SKU-MES-01',
            'type'      => 'finished_good',
            'unit'      => 'pcs',
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $tenant->id,
            'order_number'     => 'ORD-MES-001',
            'product_id'       => $product->id,
            'quantity_ordered' => 100.0,
            'status'           => 'in_progress',
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(5)->toDateString(),
        ]);

        $schedule = ProductionSchedule::create([
            'tenant_id'           => $tenant->id,
            'production_order_id' => $order->id,
            'schedule_number'     => 'SCH-MES-001',
            'status'              => 'in_progress',
            'scheduled_at'        => now(),
        ]);

        $wc = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name'      => 'SMT Line 1',
            'code'      => 'WC-SMT-01',
            'status'    => 'active',
        ]);

        $orderOp = ProductionOrderOperation::create([
            'tenant_id'           => $tenant->id,
            'production_order_id' => $order->id,
            'operation_number'    => 'OP-010',
            'name'                => 'Surface Mount Technology',
            'sequence'            => 1,
            'status'              => 'running',
        ]);

        ProductionScheduleOperation::create([
            'tenant_id'                     => $tenant->id,
            'production_schedule_id'        => $schedule->id,
            'production_order_id'           => $order->id,
            'production_order_operation_id' => $orderOp->id,
            'work_center_id'                => $wc->id,
            'sequence'                      => 1,
            'status'                        => 'running',
            'planned_start'                 => now()->subHour(),
            'planned_finish'                => now()->addHour(),
            'actual_start'                  => now()->subHour(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Shop Floor MES Execution Pulse');
        $response->assertSee('Running Ops');
        $response->assertSee('Ready in Queue');
        $response->assertSee('Open Shop Floor / MES');
    }

    /**
     * Phase 2 - Tenant Isolation for Machine, Work Center and Andon Data.
     */
    public function test_phase2_manufacturing_intelligence_tenant_isolation(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'admin']);

        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

        $wcB = WorkCenter::create([
            'tenant_id' => $tenantB->id,
            'name'      => 'Secret Tenant B Laser Center',
            'code'      => 'WC-SECRET-B',
            'status'    => 'active',
        ]);

        Machine::create([
            'tenant_id'      => $tenantB->id,
            'work_center_id' => $wcB->id,
            'name'           => 'Secret Laser Cutter B',
            'code'           => 'MCH-SECRET-B',
            'status'         => 'active',
            'current_state'  => 'Breakdown',
        ]);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenantA->id])
            ->withHeader('X-Tenant', $tenantA->slug)
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Secret Tenant B Laser Center');
        $response->assertDontSee('WC-SECRET-B');
        $response->assertDontSee('Secret Laser Cutter B');
        $response->assertDontSee('MCH-SECRET-B');
    }

    /**
     * Phase 3A - Quality Intelligence renders FPY, inspection counts, and top defect categories.
     */
    public function test_phase3_quality_intelligence_renders_fpy_and_inspections(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $plan = ProductionQualityPlan::create([
            'tenant_id' => $tenant->id,
            'name' => 'General Quality Assurance Plan',
            'version' => '1.0',
            'type' => 'process',
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        ProductionQualityInspection::create([
            'tenant_id' => $tenant->id,
            'inspection_number' => 'INS-PH3-001',
            'quality_plan_id' => $plan->id,
            'stage' => 'in_process',
            'status' => 'completed',
            'result' => 'passed',
            'sample_size' => 10,
            'inspected_quantity' => 10,
            'passed_qty' => 10,
            'failed_qty' => 0,
            'inspected_at' => now(),
        ]);

        ProductionQualityInspection::create([
            'tenant_id' => $tenant->id,
            'inspection_number' => 'INS-PH3-002',
            'quality_plan_id' => $plan->id,
            'stage' => 'in_process',
            'status' => 'completed',
            'result' => 'failed',
            'sample_size' => 10,
            'inspected_quantity' => 10,
            'passed_qty' => 8,
            'failed_qty' => 2,
            'inspected_at' => now(),
        ]);

        $ncr = ProductionNcr::create([
            'tenant_id' => $tenant->id,
            'ncr_number' => 'NCR-PH3-001',
            'category' => 'Dimensional Variance',
            'status' => 'open',
            'disposition_type' => 'rework',
            'description' => 'Bore diameter out of tolerance',
        ]);

        ProductionCapa::create([
            'tenant_id' => $tenant->id,
            'capa_number' => 'CAPA-PH3-001',
            'ncr_id' => $ncr->id,
            'status' => 'open',
            'root_cause_category' => 'Tool Wear',
            'corrective_action' => 'Replace tool insert and re-calibrate',
            'action_owner_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Quality Intelligence &amp; Defect Analytics', false);
        $response->assertSee('First Pass Yield');
        $response->assertSee('Dimensional Variance');
        $response->assertSee(route('production.quality.dashboard'));
        $response->assertSee(route('production.inspections.index'));
    }

    /**
     * Phase 3B - Six Big Losses & Cycle Time Intelligence renders TPM categories and flow times.
     */
    public function test_phase3_six_big_losses_and_cycle_time_intelligence_renders(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard', ['timeframe' => 'month']));

        $response->assertStatus(200);
        $response->assertSee('TPM Six Big Losses Classification');
        $response->assertSee('Equipment Failure');
        $response->assertSee('Setup &amp; Adjust', false);
        $response->assertSee('Minor Stops');
        $response->assertSee('Reduced Speed');
        $response->assertSee('Startup Rejects');
        $response->assertSee('Production Scrap');
        $response->assertSee('Cycle Times &amp; Waiting Averages', false);
        $response->assertSee('Setup');
        $response->assertSee('Processing');
        $response->assertSee('Total Cycle');
        $response->assertSee('Resource Utilizations', false);
        $response->assertSee('Overall Downtime Rate');
    }

    /**
     * Phase 3C - Plant Maintenance & Subcontracting SLA Integration renders overdue PMs and SLA.
     */
    public function test_phase3_plant_maintenance_and_subcontracting_sla_renders(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $wc = WorkCenter::create([
            'tenant_id' => $tenant->id,
            'name'      => 'CNC Milling Cell 3',
            'code'      => 'WC-CNC-03',
            'status'    => 'active',
        ]);

        $machine = Machine::create([
            'tenant_id'      => $tenant->id,
            'work_center_id' => $wc->id,
            'name'           => '5-Axis CNC Mill #1',
            'code'           => 'MCH-CNC-5AX-01',
            'status'         => 'active',
        ]);

        ProductionPmSchedule::create([
            'tenant_id'        => $tenant->id,
            'machine_id'       => $machine->id,
            'name'             => 'Spindle Bearing Greasing',
            'code'             => 'PM-SPINDLE-01',
            'maintenance_type' => 'preventive',
            'frequency_type'   => 'days',
            'frequency_value'  => 30,
            'next_due_date'    => now()->subDays(5)->toDateString(),
            'priority'         => 'high',
            'is_active'        => true,
        ]);

        ProductionMaintenanceWorkOrder::create([
            'tenant_id'           => $tenant->id,
            'work_order_number'   => 'MWO-BRK-001',
            'machine_id'          => $machine->id,
            'type'                => 'breakdown',
            'priority'            => 'critical',
            'status'              => 'in_progress',
            'problem_description' => 'Coolant pump pressure failure',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug ?? 'test-tenant')
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Plant Maintenance &amp; Subcontracting SLA', false);
        $response->assertSee('Overdue PM');
        $response->assertSee('Breakdown WOs');
        $response->assertSee('Vendor SLA &amp; External Operations', false);
        $response->assertSee('OTD:');
        $response->assertSee(route('production.maintenance.dashboard'));
        $response->assertSee(route('production.maintenance.work-orders.index'));
        $response->assertSee(route('production.subcontract.analytics'));
    }

    /**
     * Phase 3 - Tenant Isolation for Quality, Maintenance and Subcontracting.
     */
    public function test_phase3_tenant_isolation_for_quality_maintenance_and_subcontracting(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-a-ph3']);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'admin']);

        $tenantB = Tenant::factory()->create(['slug' => 'tenant-b-ph3']);

        $wcB = WorkCenter::create([
            'tenant_id' => $tenantB->id,
            'name'      => 'Tenant B Secret Cell',
            'code'      => 'WC-SECRET-B3',
            'status'    => 'active',
        ]);

        $machineB = Machine::create([
            'tenant_id'      => $tenantB->id,
            'work_center_id' => $wcB->id,
            'name'           => 'Secret Tenant B Extruder',
            'code'           => 'MCH-SEC-B3',
            'status'         => 'active',
        ]);

        ProductionNcr::create([
            'tenant_id'   => $tenantB->id,
            'ncr_number'  => 'NCR-SEC-999',
            'category'    => 'Secret Tenant B Defect Category',
            'status'      => 'open',
            'description' => 'Confidential defect',
        ]);

        ProductionPmSchedule::create([
            'tenant_id'        => $tenantB->id,
            'machine_id'       => $machineB->id,
            'name'             => 'Secret PM Overhaul B',
            'code'             => 'PM-SEC-999',
            'maintenance_type' => 'preventive',
            'frequency_type'   => 'days',
            'frequency_value'  => 7,
            'next_due_date'    => now()->subDays(10)->toDateString(),
            'priority'         => 'critical',
            'is_active'        => true,
        ]);

        ProductionMaintenanceWorkOrder::create([
            'tenant_id'           => $tenantB->id,
            'work_order_number'   => 'MWO-SECRET-B-999',
            'machine_id'          => $machineB->id,
            'type'                => 'breakdown',
            'priority'            => 'critical',
            'status'              => 'in_progress',
            'problem_description' => 'Secret breakdown issue',
        ]);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenantA->id])
            ->withHeader('X-Tenant', $tenantA->slug)
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('Secret Tenant B Defect Category');
        $response->assertDontSee('Secret PM Overhaul B');
        $response->assertDontSee('MWO-SECRET-B-999');
    }

    /**
     * Phase 4A: Operational Execution Variance & Duration Variance
     */
    public function test_phase4_operational_execution_variance_renders_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Precision Piston Ring',
            'sku'       => 'PST-RNG-004',
            'type'      => 'finished_good',
            'unit'      => 'pcs',
        ]);

        $order = ProductionOrder::create([
            'tenant_id'        => $tenant->id,
            'order_number'     => 'ORD-P4-VAR-001',
            'product_id'       => $product->id,
            'quantity_ordered' => 200.0,
            'quantity_produced'=> 180.0,
            'status'           => 'in_progress',
            'start_date'       => now()->startOfDay()->toDateString(),
            'end_date'         => now()->addDays(2)->toDateString(),
        ]);

        // Create completed operation with known planned and actual durations (minutes)
        ProductionOrderOperation::create([
            'tenant_id'               => $tenant->id,
            'production_order_id'     => $order->id,
            'sequence'                => 1,
            'operation_number'        => 'OP-10',
            'name'                    => 'Precision Machining',
            'status'                  => ProductionOrderOperation::STATUS_COMPLETED,
            'setup_time_planned'      => 30.0,
            'processing_time_planned' => 90.0,
            'total_time_planned'      => 120.0, // 2.0 hours
            'setup_time_actual'       => 20.0,
            'processing_time_actual'  => 70.0,  // 90 minutes = 1.5 hours
            'actual_end_time'         => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug)
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Execution Variance, Order Risk &amp; Planning Pulse', false);
        $response->assertSee('Execution Variance (Today)');
        $response->assertSee('Output Units (Planned vs Actual)');
        $response->assertSee('Operation Hours (Actual vs Plan)');
        $response->assertSee('Full Variance Analysis');
        $response->assertSee(route('production.variances.index'));
    }

    /**
     * Phase 4B: Near-Term Order Completion Risk detects at-risk orders (<72h & progress <50%)
     */
    public function test_phase4_near_term_order_completion_risk_detects_at_risk_orders(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Aluminum Valve Cover',
            'sku'       => 'VLV-CVR-004',
            'type'      => 'finished_good',
            'unit'      => 'pcs',
        ]);

        // Order 1: Due in 48h, progress = 20% (<50%) -> MUST BE AT RISK
        $atRiskOrder = ProductionOrder::create([
            'tenant_id'        => $tenant->id,
            'order_number'     => 'ORD-AT-RISK-001',
            'product_id'       => $product->id,
            'quantity_ordered' => 100.0,
            'quantity_produced'=> 20.0,
            'status'           => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addHours(48)->toDateString(),
        ]);

        // Order 2: Due in 48h, progress = 75% (>=50%) -> SAFE, MUST NOT BE LISTED
        $safeOrder = ProductionOrder::create([
            'tenant_id'        => $tenant->id,
            'order_number'     => 'ORD-SAFE-PROGRESS-002',
            'product_id'       => $product->id,
            'quantity_ordered' => 100.0,
            'quantity_produced'=> 75.0,
            'status'           => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addHours(48)->toDateString(),
        ]);

        // Order 3: Due in 10 days, progress = 10% (due > 72h) -> MUST NOT BE LISTED
        $laterOrder = ProductionOrder::create([
            'tenant_id'        => $tenant->id,
            'order_number'     => 'ORD-LATER-DATE-003',
            'product_id'       => $product->id,
            'quantity_ordered' => 100.0,
            'quantity_produced'=> 10.0,
            'status'           => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(10)->toDateString(),
        ]);

        // Order 4: Active order with open NCR -> Quality Hold
        $qcOrder = ProductionOrder::create([
            'tenant_id'        => $tenant->id,
            'order_number'     => 'ORD-QC-HOLD-004',
            'product_id'       => $product->id,
            'quantity_ordered' => 50.0,
            'quantity_produced'=> 5.0,
            'status'           => ProductionOrder::STATUS_RELEASED,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(5)->toDateString(),
        ]);

        ProductionNcr::create([
            'tenant_id'           => $tenant->id,
            'production_order_id' => $qcOrder->id,
            'ncr_number'          => 'NCR-P4-HOLD-001',
            'category'            => 'Dimensional Deviation',
            'status'              => 'open',
            'description'         => 'Flange thickness out of tolerance',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug)
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Near-Term Completion Risk (&lt;72h)', false);
        $response->assertSee('ORD-AT-RISK-001');
        $response->assertSee('1 Quality Hold');
        $response->assertSee(route('production.planning-exceptions.index'));

        $viewAtRisk = $response->viewData('atRiskOrders');
        $this->assertTrue($viewAtRisk->contains('order_number', 'ORD-AT-RISK-001'));
        $this->assertFalse($viewAtRisk->contains('order_number', 'ORD-SAFE-PROGRESS-002'));
        $this->assertFalse($viewAtRisk->contains('order_number', 'ORD-LATER-DATE-003'));
    }

    /**
     * Phase 4C: Production Planning & ECO Pulse renders pipeline counts
     */
    public function test_phase4_production_planning_and_eco_pulse_renders_pipeline_counts(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Camshaft Assembly',
            'sku'       => 'CAM-ASY-004',
            'type'      => 'finished_good',
            'unit'      => 'pcs',
        ]);

        // Production Plans across statuses
        ProductionPlan::create([
            'tenant_id'   => $tenant->id,
            'plan_number' => 'PLAN-P4-001',
            'name'        => 'Quarterly Camshaft Batch',
            'product_id'  => $product->id,
            'quantity'    => 500.0,
            'start_date'  => now()->toDateString(),
            'end_date'    => now()->addDays(30)->toDateString(),
            'status'      => ProductionPlan::STATUS_DRAFT,
        ]);

        ProductionPlan::create([
            'tenant_id'   => $tenant->id,
            'plan_number' => 'PLAN-P4-002',
            'name'        => 'Approved Plan Batch',
            'product_id'  => $product->id,
            'quantity'    => 300.0,
            'start_date'  => now()->toDateString(),
            'end_date'    => now()->addDays(20)->toDateString(),
            'status'      => ProductionPlan::STATUS_APPROVED,
        ]);

        // ECOs across statuses
        ProductionEco::create([
            'tenant_id'   => $tenant->id,
            'eco_number'  => 'ECO-P4-001',
            'title'       => 'Optimize Camshaft Tolerance',
            'change_type' => ProductionEco::CHANGE_TYPE_ROUTING,
            'product_id'  => $product->id,
            'status'      => ProductionEco::STATUS_UNDER_REVIEW,
        ]);

        ProductionEco::create([
            'tenant_id'   => $tenant->id,
            'eco_number'  => 'ECO-P4-002',
            'title'       => 'Material Specification Update',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id'  => $product->id,
            'status'      => ProductionEco::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id])
            ->withHeader('X-Tenant', $tenant->slug)
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Planning &amp; ECO Pulse', false);
        $response->assertSee('Master Plans');
        $response->assertSee('ECO Pipeline');
        $response->assertSee(route('production.plans.index'));
        $response->assertSee(route('production.ecos.index'));
    }

    /**
     * Phase 4 Tenant Isolation: At-risk orders, plans, and ECOs of Tenant B are hidden from Tenant A
     */
    public function test_phase4_tenant_isolation_for_risk_plans_and_ecos(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'tenant-alpha-p4']);
        $tenantB = Tenant::factory()->create(['slug' => 'tenant-beta-p4']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'admin']);

        $productB = Product::create([
            'tenant_id' => $tenantB->id,
            'name'      => 'Secret Turbocharger Housing',
            'sku'       => 'TURBO-SEC-B4',
            'type'      => 'finished_good',
            'unit'      => 'pcs',
        ]);

        ProductionOrder::create([
            'tenant_id'        => $tenantB->id,
            'order_number'     => 'ORD-SEC-RISK-B999',
            'product_id'       => $productB->id,
            'quantity_ordered' => 100.0,
            'quantity_produced'=> 10.0,
            'status'           => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addHours(24)->toDateString(),
        ]);

        ProductionPlan::create([
            'tenant_id'   => $tenantB->id,
            'plan_number' => 'PLAN-SEC-B999',
            'name'        => 'Confidential Beta Plan',
            'product_id'  => $productB->id,
            'quantity'    => 1000.0,
            'start_date'  => now()->toDateString(),
            'end_date'    => now()->addDays(30)->toDateString(),
            'status'      => ProductionPlan::STATUS_DRAFT,
        ]);

        ProductionEco::create([
            'tenant_id'   => $tenantB->id,
            'eco_number'  => 'ECO-SEC-B999',
            'title'       => 'Classified ECO Redesign',
            'change_type' => ProductionEco::CHANGE_TYPE_BOM,
            'product_id'  => $productB->id,
            'status'      => ProductionEco::STATUS_UNDER_REVIEW,
        ]);

        $response = $this->actingAs($userA)
            ->withSession(['tenant_id' => $tenantA->id])
            ->withHeader('X-Tenant', $tenantA->slug)
            ->get(route('production.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('ORD-SEC-RISK-B999');
        $response->assertDontSee('PLAN-SEC-B999');
        $response->assertDontSee('ECO-SEC-B999');
        $response->assertDontSee('Secret Turbocharger Housing');
    }
}


