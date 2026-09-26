<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionCostAdjustment;
use App\Domains\Production\Models\ProductionMachineDowntime;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ReportingService;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionMisReportsTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private int $tenant2Id = 2;
    private User $adminUser;
    private User $standardUser;
    private User $tenant2User;
    private Product $finishedGood;
    private Product $rawMaterial;
    private Uom $uom;
    private ProductionOrder $order1;
    private ProductionOrder $order2;
    private ProductionOrder $tenant2Order;
    private WorkCenter $workCenter;
    private Machine $machine;
    private ProductionOrderOperation $op1;
    private ProductionOrderOperation $tenant2Op;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Tenant 1 & Tenant 2
        $this->tenant1 = Tenant::factory()->create(['id' => $this->tenantId, 'slug' => 'tenant-1']);
        $this->tenant2 = Tenant::factory()->create(['id' => $this->tenant2Id, 'slug' => 'tenant-2']);

        $this->withHeader('X-Tenant', 'tenant-1');

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role'      => 'admin',
        ]);

        $this->standardUser = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role'      => 'employee',
        ]);

        $this->tenant2User = User::factory()->create([
            'tenant_id' => $this->tenant2Id,
            'role'      => 'admin',
        ]);

        // 2. Setup UOM & Products
        $this->uom = Uom::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Pieces',
            'code'      => 'PCS',
            'type'      => 'reference',
        ]);

        $this->finishedGood = Product::create([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Modular Office Desk',
            'sku'        => 'FG-DESK-001',
            'type'       => 'finished_good',
            'unit_cost'  => 150.00,
            'status'     => 'active',
            'uom_id'     => $this->uom->id,
        ]);

        $this->rawMaterial = Product::create([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Steel Leg Frame',
            'sku'        => 'RM-STEEL-01',
            'type'       => 'raw_material',
            'unit_cost'  => 25.00,
            'status'     => 'active',
            'uom_id'     => $this->uom->id,
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id'     => $this->tenantId,
            'name'          => 'Assembly Cell A',
            'code'          => 'WC-ASM-01',
            'overhead_rate' => 60.00,
            'cost_per_hour' => 120.00,
            'status'        => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'      => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name'           => 'Assembly Press 01',
            'code'           => 'MC-ASM-01',
            'status'         => 'active',
        ]);

        $bom = ProductionBom::create([
            'tenant_id'      => $this->tenantId,
            'product_id'     => $this->finishedGood->id,
            'bom_number'     => 'BOM-DESK-01',
            'bom_name'       => 'Desk Standard BOM',
            'bom_type'       => 'manufacturing',
            'base_quantity'  => 1.0,
            'base_uom_id'    => $this->uom->id,
            'version'        => '1.0.0',
            'status'         => 'approved',
            'effective_date' => now()->toDateString(),
        ]);

        $routing = Routing::create([
            'tenant_id'      => $this->tenantId,
            'product_id'     => $this->finishedGood->id,
            'routing_number' => 'RT-DESK-01',
            'name'           => 'Desk Routing',
            'version'        => '1.0.0',
            'status'         => 'active',
        ]);

        $routingOp = RoutingOperation::create([
            'tenant_id'            => $this->tenantId,
            'routing_id'           => $routing->id,
            'sequence'             => 10,
            'operation_number'     => 'OP10',
            'name'                 => 'Main Assembly',
            'work_center_id'       => $this->workCenter->id,
            'labor_cost_rate'      => 2.00, // per min
            'machine_cost_rate'    => 1.50, // per min
            'setup_time_minutes'   => 10,
            'processing_time_minutes' => 5,
        ]);

        // 3. Create Orders in Tenant 1
        // Order 1: Completed, 10 ordered, 10 produced, 1 scrapped
        $this->order1 = ProductionOrder::create([
            'tenant_id'        => $this->tenantId,
            'order_number'     => 'PO-2026-0001',
            'product_id'       => $this->finishedGood->id,
            'bom_id'           => $bom->id,
            'routing_id'       => $routing->id,
            'quantity_ordered' => 10.0,
            'quantity_produced'=> 10.0,
            'quantity_scrapped'=> 1.0,
            'quantity_rejected'=> 0.0,
            'status'           => ProductionOrder::STATUS_COMPLETED,
            'start_date'       => now()->subDays(5)->toDateString(),
            'end_date'         => now()->subDays(1)->toDateString(),
            'actual_start_date'=> now()->subDays(5),
            'actual_end_date'  => now()->subDays(1),
        ]);

        // Order 2: In Progress, 20 ordered, 8 produced, 0 scrapped
        $this->order2 = ProductionOrder::create([
            'tenant_id'        => $this->tenantId,
            'order_number'     => 'PO-2026-0002',
            'product_id'       => $this->finishedGood->id,
            'bom_id'           => $bom->id,
            'routing_id'       => $routing->id,
            'quantity_ordered' => 20.0,
            'quantity_produced'=> 8.0,
            'quantity_scrapped'=> 0.0,
            'quantity_rejected'=> 0.0,
            'status'           => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date'       => now()->subDays(2)->toDateString(),
            'end_date'         => now()->addDays(3)->toDateString(),
        ]);

        // Order 1 Operations & Cost Setup
        $this->op1 = ProductionOrderOperation::create([
            'tenant_id'               => $this->tenantId,
            'production_order_id'     => $this->order1->id,
            'routing_operation_id'    => $routingOp->id,
            'sequence'                => 10,
            'operation_number'        => 'OP10',
            'name'                    => 'Main Assembly',
            'work_center_id'          => $this->workCenter->id,
            'machine_id'              => $this->machine->id,
            'status'                  => ProductionOrderOperation::STATUS_COMPLETED,
            'setup_time_planned'      => 10,
            'processing_time_planned' => 50,
            'total_time_planned'      => 60,
            'setup_time_actual'       => 15,
            'processing_time_actual'  => 55,
        ]);

        // Order 1 Reservations & Issues
        // Planned: 20 raw materials (2 per desk). Issued: 22 (variance +2)
        $res1 = ProductionOrderReservation::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order1->id,
            'product_id'          => $this->rawMaterial->id,
            'quantity_planned'    => 20.0,
            'quantity_issued'     => 22.0,
            'uom_id'              => $this->uom->id,
        ]);

        ProductionOrderIssue::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order1->id,
            'reservation_id'      => $res1->id,
            'product_id'          => $this->rawMaterial->id,
            'quantity_issued'     => 22.0,
            'issue_type'          => 'standard',
            'issued_at'           => now(),
        ]);

        // Order 1 Manual Cost Adjustment (+50.00 tooling)
        ProductionCostAdjustment::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order1->id,
            'adjustment_date'     => now()->toDateString(),
            'cost_component'      => 'other',
            'category'            => 'tooling',
            'description'         => 'Special jig preparation',
            'amount'              => 50.00,
            'status'              => 'recorded',
        ]);

        // 4. Create Order in Tenant 2 (Isolation Test)
        $this->tenant2Order = ProductionOrder::create([
            'tenant_id'        => $this->tenant2Id,
            'order_number'     => 'PO-T2-9999',
            'product_id'       => $this->finishedGood->id,
            'quantity_ordered' => 50.0,
            'quantity_produced'=> 50.0,
            'status'           => ProductionOrder::STATUS_COMPLETED,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->toDateString(),
        ]);

        $this->tenant2Op = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenant2Id,
            'production_order_id' => $this->tenant2Order->id,
            'sequence'            => 10,
            'operation_number'    => 'OP10',
            'name'                => 'T2 Main Op',
            'status'              => ProductionOrderOperation::STATUS_COMPLETED,
        ]);
    }

    /**
     * RBAC verification: Non-permitted user receives 403 Forbidden.
     */
    public function test_unauthorized_user_cannot_access_reports(): void
    {
        $this->actingAs($this->standardUser);

        $response = $this->get(route('production.intelligence.reports.index'));
        $response->assertStatus(403);

        $responseShow = $this->get(route('production.intelligence.reports.show', 'production-orders'));
        $responseShow->assertStatus(403);

        $responseExport = $this->get(route('production.intelligence.reports.export', 'production-orders'));
        $responseExport->assertStatus(403);
    }

    /**
     * Reports index page renders with all 6 report cards.
     */
    public function test_authorized_user_can_view_reports_index_with_all_cards(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('production.intelligence.reports.index'));
        $response->assertStatus(200);

        $response->assertSee('Machine Performance');
        $response->assertSee('Work Center Report');
        $response->assertSee('Downtime Breakdown');
        $response->assertSee('Production Orders & Output', false);
        $response->assertSee('Material Consumption & Variance', false);
        $response->assertSee('Production Cost & Variance', false);

        // Verify filter date translations
        $response->assertDontSee('production.date_start');
        $response->assertDontSee('production.date_end');
        $response->assertSee('Start Date');
        $response->assertSee('End Date');

        $responseDetail = $this->get(route('production.intelligence.reports.show', 'machine'));
        $responseDetail->assertStatus(200);
        $responseDetail->assertDontSee('production.date_start');
        $responseDetail->assertDontSee('production.date_end');
        $responseDetail->assertSee('Start Date');
        $responseDetail->assertSee('End Date');
    }

    /**
     * Production Order Summary Report calculates metrics and supports filters.
     */
    public function test_production_order_summary_report_computes_correct_metrics(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('production.intelligence.reports.show', [
            'type' => 'production-orders',
            'date_start' => now()->subMonth()->toDateString(),
            'date_end' => now()->addMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee('PO-2026-0001');
        $response->assertSee('PO-2026-0002');
        $response->assertSee('Modular Office Desk');
        $response->assertSee('10.00'); // Order 1 produced
        $response->assertSee('8.00');  // Order 2 produced
        $response->assertSee('100%');  // Order 1 completion
        $response->assertSee('40%');   // Order 2 completion (8/20 = 40%)

        // Filter by Status = completed
        $filteredResponse = $this->get(route('production.intelligence.reports.show', [
            'type' => 'production-orders',
            'status' => 'completed',
        ]));

        $filteredResponse->assertStatus(200);
        $filteredResponse->assertSee('PO-2026-0001');
        $filteredResponse->assertDontSee('PO-2026-0002');
    }

    /**
     * Production Order Summary CSV Export streams correct CSV data.
     */
    public function test_production_order_summary_csv_export(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('production.intelligence.reports.export', 'production-orders'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('"Order Number"', $content);
        $this->assertStringContainsString('"Product Name"', $content);
        $this->assertStringContainsString('PO-2026-0001', $content);
        $this->assertStringContainsString('PO-2026-0002', $content);
        $this->assertStringContainsString('FG-DESK-001', $content);
        $this->assertStringNotContainsString('PO-T2-9999', $content); // Tenant 2 isolated
    }

    /**
     * Material Consumption Report calculates variances, costs, and groups by UOM safely.
     */
    public function test_material_consumption_report_computes_correct_variances(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('production.intelligence.reports.show', [
            'type' => 'material-consumption',
            'date_start' => now()->subMonth()->toDateString(),
            'date_end' => now()->addMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee('PO-2026-0001');
        $response->assertSee('Steel Leg Frame');
        $response->assertSee('20.00'); // Planned qty
        $response->assertSee('22.00'); // Issued qty
        $response->assertSee('+2.00'); // Variance qty (22 - 20 = +2)
        $response->assertSee('50.00'); // Variance cost (2 * 25.00 = 50.00)

        // Test CSV Export
        $exportResponse = $this->get(route('production.intelligence.reports.export', 'material-consumption'));
        $exportResponse->assertStatus(200);
        $csvContent = $exportResponse->streamedContent();

        $this->assertStringContainsString('"Order Number"', $csvContent);
        $this->assertStringContainsString('"Finished Good"', $csvContent);
        $this->assertStringContainsString('"Component Name"', $csvContent);
        $this->assertStringContainsString('PO-2026-0001', $csvContent);
        $this->assertStringContainsString('RM-STEEL-01', $csvContent);
        $this->assertStringContainsString('22', $csvContent);
    }

    /**
     * Cost & Variance Report reuses canonical cost calculations and manual adjustments.
     */
    public function test_production_cost_variance_report_computes_correct_costs(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->get(route('production.intelligence.reports.show', [
            'type' => 'cost-variance',
            'date_start' => now()->subMonth()->toDateString(),
            'date_end' => now()->addMonth()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee('PO-2026-0001');
        $response->assertSee('Modular Office Desk');
        $response->assertSee('50.00'); // Manual adjustment seen in report

        // Test CSV Export
        $exportResponse = $this->get(route('production.intelligence.reports.export', 'cost-variance'));
        $exportResponse->assertStatus(200);
        $csvContent = $exportResponse->streamedContent();

        $this->assertStringContainsString('"Order Number"', $csvContent);
        $this->assertStringContainsString('"Planned Cost"', $csvContent);
        $this->assertStringContainsString('"Actual Total Cost"', $csvContent);
        $this->assertStringContainsString('PO-2026-0001', $csvContent);
        $this->assertStringContainsString('FG-DESK-001', $csvContent);
    }

    /**
     * Tenant Isolation: User in Tenant 1 never sees Tenant 2 data.
     */
    public function test_reports_enforce_strict_tenant_isolation(): void
    {
        $this->actingAs($this->adminUser);

        // Production Orders report
        $poReport = $this->get(route('production.intelligence.reports.show', 'production-orders'));
        $poReport->assertDontSee('PO-T2-9999');

        // CSV export
        $poExport = $this->get(route('production.intelligence.reports.export', 'production-orders'));
        $this->assertStringNotContainsString('PO-T2-9999', $poExport->streamedContent());

        // Login as Tenant 2 admin -> should see PO-T2-9999 and NOT PO-2026-0001
        $this->actingAs($this->tenant2User);
        $this->withHeader('X-Tenant', 'tenant-2');
        $t2Report = $this->get(route('production.intelligence.reports.show', 'production-orders'));
        $t2Report->assertSee('PO-T2-9999');
        $t2Report->assertDontSee('PO-2026-0001');
    }

    /**
     * Regression check: Existing machine, work-center, and downtime reports remain 100% functional.
     */
    public function test_existing_reports_regression(): void
    {
        $this->actingAs($this->adminUser);

        // Machine Report
        $this->get(route('production.intelligence.reports.show', 'machine'))->assertStatus(200);
        $this->get(route('production.intelligence.reports.export', 'machine'))->assertStatus(200);

        // Work Center Report
        $this->get(route('production.intelligence.reports.show', 'work-center'))->assertStatus(200);
        $this->get(route('production.intelligence.reports.export', 'work-center'))->assertStatus(200);

        // Downtime Report
        $this->get(route('production.intelligence.reports.show', 'downtime'))->assertStatus(200);
        $this->get(route('production.intelligence.reports.export', 'downtime'))->assertStatus(200);
    }

    /**
     * Daily Production Report (DPR): Correctly aggregates day-wise output, yield, and tenant isolation.
     */
    public function test_daily_production_report_renders_and_computes_aggregates(): void
    {
        $this->actingAs($this->adminUser);

        // Day 1 (yesterday)
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order1->id,
            'operation_id'        => $this->op1->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 5.0,
            'quantity_rejected'   => 1.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 120.0,
            'setup_minutes_logged'=> 15.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now()->subDay()->setTime(10, 0, 0),
            'remarks'             => 'Shift 1 batch A',
        ]);

        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order1->id,
            'operation_id'        => $this->op1->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 5.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 1.0,
            'run_minutes_logged'  => 60.0,
            'setup_minutes_logged'=> 10.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now()->subDay()->setTime(14, 30, 0),
            'remarks'             => 'Shift 2 batch B',
        ]);

        // Day 2 (today)
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order2->id,
            'operation_id'        => $this->op1->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 8.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 180.0,
            'setup_minutes_logged'=> 20.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now()->setTime(11, 0, 0),
            'remarks'             => 'Order 2 day run',
        ]);

        // Tenant 2 log (Isolation test)
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenant2Id,
            'production_order_id' => $this->tenant2Order->id,
            'operation_id'        => $this->tenant2Op->id,
            'quantity_produced'   => 999.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 500.0,
            'setup_minutes_logged'=> 60.0,
            'recorded_by'         => $this->tenant2User->id,
            'recorded_at'         => now(),
            'remarks'             => 'TENANT2_SECRET_PRODUCTION',
        ]);

        // 1. Reports Index shows Daily Production Report card
        $indexResponse = $this->get(route('production.intelligence.reports.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Daily Production Report');

        // 2. View Daily Production Report
        $response = $this->get(route('production.intelligence.reports.show', [
            'type'       => 'daily-production',
            'date_start' => now()->subDays(2)->toDateString(),
            'date_end'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertSee('Daily Production Report');
        // Total Good Units: 5 + 5 + 8 = 18
        $response->assertSee('18');
        // Total Attempted: 18 + 1 + 1 = 20 -> Yield 18/20 = 90.0%
        $response->assertSee('90.0%');
        // Total Run Time: 360 min = 6.0 hrs
        $response->assertSee('6.0 hrs');
        // Work Center and Machine names
        $response->assertSee('Assembly Cell A');
        $response->assertSee('Assembly Press 01');
        // Production Order numbers
        $response->assertSee('PO-2026-0001');
        $response->assertSee('PO-2026-0002');
        // Tenant isolation: Tenant 2 secret remarks should never be seen
        $response->assertDontSee('TENANT2_SECRET_PRODUCTION');
        $response->assertDontSee('PO-T2-9999');
    }

    /**
     * Daily Production Report respects filters (date range, order, work center, machine).
     */
    public function test_daily_production_report_filters(): void
    {
        $this->actingAs($this->adminUser);

        // Progress log for Order 1
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order1->id,
            'operation_id'        => $this->op1->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 10.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 60.0,
            'setup_minutes_logged'=> 10.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now()->subDays(10),
            'remarks'             => 'Ten days ago production',
        ]);

        // Progress log for Order 2 (today)
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order2->id,
            'operation_id'        => $this->op1->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 20.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 120.0,
            'setup_minutes_logged'=> 15.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now(),
            'remarks'             => 'Today production',
        ]);

        // Filter: Date range strictly covering today
        $responseDateFilter = $this->get(route('production.intelligence.reports.show', [
            'type'       => 'daily-production',
            'date_start' => now()->startOfDay()->toDateString(),
            'date_end'   => now()->endOfDay()->toDateString(),
        ]));
        $responseDateFilter->assertStatus(200);
        $responseDateFilter->assertSee('Today production');
        $responseDateFilter->assertDontSee('Ten days ago production');

        // Filter: Order ID for Order 1 with date range covering 15 days ago to now
        $responseOrderFilter = $this->get(route('production.intelligence.reports.show', [
            'type'       => 'daily-production',
            'date_start' => now()->subDays(15)->toDateString(),
            'date_end'   => now()->toDateString(),
            'order_id'   => $this->order1->id,
        ]));
        $responseOrderFilter->assertStatus(200);
        $responseOrderFilter->assertSee('Ten days ago production');
        $responseOrderFilter->assertDontSee('Today production');

        // Filter: Non-existent work center ID -> should show empty state
        $responseEmpty = $this->get(route('production.intelligence.reports.show', [
            'type'           => 'daily-production',
            'work_center_id' => 99999,
        ]));
        $responseEmpty->assertStatus(200);
        $responseEmpty->assertSee('No production activity found');
    }

    /**
     * Daily Production Report exports (CSV, Excel, PDF).
     */
    public function test_daily_production_report_exports(): void
    {
        $this->actingAs($this->adminUser);

        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $this->order1->id,
            'operation_id'        => $this->op1->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 15.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 90.0,
            'setup_minutes_logged'=> 15.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now(),
            'remarks'             => 'Export testing row',
        ]);

        // CSV Export
        $csvResponse = $this->get(route('production.intelligence.reports.export', [
            'type'       => 'daily-production',
            'date_start' => now()->subDays(2)->toDateString(),
            'date_end'   => now()->toDateString(),
        ]));
        $csvResponse->assertStatus(200);
        $csvContent = $csvResponse->streamedContent();
        $this->assertStringContainsString('Date', $csvContent);
        $this->assertStringContainsString('Order Number', $csvContent);
        $this->assertStringContainsString('PO-2026-0001', $csvContent);
        $this->assertStringContainsString('Export testing row', $csvContent);

        // Excel Export
        $excelResponse = $this->get(route('production.intelligence.reports.export-excel', [
            'type'       => 'daily-production',
            'date_start' => now()->subDays(2)->toDateString(),
            'date_end'   => now()->toDateString(),
        ]));
        $excelResponse->assertStatus(200);

        // PDF Export
        $pdfResponse = $this->get(route('production.intelligence.reports.export-pdf', [
            'type'       => 'daily-production',
            'date_start' => now()->subDays(2)->toDateString(),
            'date_end'   => now()->toDateString(),
        ]));
        $pdfResponse->assertStatus(200);
    }

    /**
     * DPR correctly distinguishes FG output, SFG output, component output, and in-process stages.
     * Never combines 20 legs + 10 compB + 5 tops + 5 weld + 5 finish + 5 assy + 5 QC + 5 pack into "60 FG units".
     */
    public function test_daily_production_report_multi_level_and_multi_operation_output_semantics(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Create realistic multi-level products
        $fgTable = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Industrial Dining Table',
            'sku'       => 'FG-TBL-100',
            'type'      => 'finished_good',
            'uom_id'    => $this->uom->id,
            'status'    => 'active',
        ]);

        $sfgFrame = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Welded Metal Frame SFG',
            'sku'       => 'SFG-FRM-100',
            'type'      => 'semi_finished',
            'uom_id'    => $this->uom->id,
            'status'    => 'active',
        ]);

        $cmpLeg = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Steel Tube Leg',
            'sku'       => 'CMP-LEG-100',
            'type'      => 'component',
            'uom_id'    => $this->uom->id,
            'status'    => 'active',
        ]);

        $cmpCompB = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Corner Bracket Component B',
            'sku'       => 'CMP-BRK-100',
            'type'      => 'component',
            'uom_id'    => $this->uom->id,
            'status'    => 'active',
        ]);

        $cmpTop = Product::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Solid Oak Table Top',
            'sku'       => 'CMP-TOP-100',
            'type'      => 'component',
            'uom_id'    => $this->uom->id,
            'status'    => 'active',
        ]);

        // 2. Multi-level Production Order for 5 Tables
        $multiOrder = ProductionOrder::create([
            'tenant_id'        => $this->tenantId,
            'order_number'     => 'PO-MULTI-005',
            'product_id'       => $fgTable->id,
            'quantity_ordered' => 5.0,
            'quantity_produced'=> 5.0,
            'status'           => ProductionOrder::STATUS_IN_PROGRESS,
            'start_date'       => now()->toDateString(),
            'end_date'         => now()->addDays(2)->toDateString(),
        ]);

        // 3. Component Operations (1-stage each)
        $opLeg = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'source_product_id'   => $cmpLeg->id,
            'bom_level'           => 2,
            'is_intermediate'     => true,
            'sequence'            => 10,
            'operation_number'    => 'OP-LEG-10',
            'name'                => 'Tube & Component Cutting',
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'status'              => ProductionOrderOperation::STATUS_COMPLETED,
        ]);

        $opCompB = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'source_product_id'   => $cmpCompB->id,
            'bom_level'           => 2,
            'is_intermediate'     => true,
            'sequence'            => 10,
            'operation_number'    => 'OP-BRK-10',
            'name'                => 'Cutting Bracket B',
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'status'              => ProductionOrderOperation::STATUS_COMPLETED,
        ]);

        $opTop = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'source_product_id'   => $cmpTop->id,
            'bom_level'           => 2,
            'is_intermediate'     => true,
            'sequence'            => 10,
            'operation_number'    => 'OP-TOP-10',
            'name'                => 'Table Top Processing',
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'status'              => ProductionOrderOperation::STATUS_COMPLETED,
        ]);

        // 4. SFG Frame Operations (2 sequential stages: Weld -> Finish)
        $opFrameWeld = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'source_product_id'   => $sfgFrame->id,
            'bom_level'           => 2,
            'is_intermediate'     => true,
            'sequence'            => 10,
            'operation_number'    => 'OP-FRM-10',
            'name'                => 'Welding / Frame Assembly',
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'status'              => ProductionOrderOperation::STATUS_COMPLETED,
        ]);

        $opFrameFinish = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'source_product_id'   => $sfgFrame->id,
            'previous_operation_id' => $opFrameWeld->id,
            'bom_level'           => 2,
            'is_intermediate'     => true,
            'sequence'            => 20,
            'operation_number'    => 'OP-FRM-20',
            'name'                => 'Finishing & Coating',
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'status'              => ProductionOrderOperation::STATUS_COMPLETED,
        ]);

        // 5. Master FG Table Operations (3 sequential stages: Final Assembly -> Quality Inspection -> Packaging)
        $opFinalAssy = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'source_product_id'   => $fgTable->id,
            'bom_level'           => 1,
            'is_intermediate'     => false,
            'sequence'            => 10,
            'operation_number'    => 'OP-FG-10',
            'name'                => 'Final Assembly',
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'status'              => ProductionOrderOperation::STATUS_COMPLETED,
        ]);

        $opQuality = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'source_product_id'   => $fgTable->id,
            'previous_operation_id' => $opFinalAssy->id,
            'bom_level'           => 1,
            'is_intermediate'     => false,
            'sequence'            => 20,
            'operation_number'    => 'OP-FG-20',
            'name'                => 'Quality Inspection',
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'status'              => ProductionOrderOperation::STATUS_COMPLETED,
        ]);

        $opPackaging = ProductionOrderOperation::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'source_product_id'   => $fgTable->id,
            'previous_operation_id' => $opQuality->id,
            'bom_level'           => 1,
            'is_intermediate'     => false,
            'sequence'            => 30,
            'operation_number'    => 'OP-FG-30',
            'name'                => 'Packaging',
            'work_center_id'      => $this->workCenter->id,
            'machine_id'          => $this->machine->id,
            'status'              => ProductionOrderOperation::STATUS_COMPLETED,
        ]);

        // 6. Record progress events corresponding exactly to the scenario:
        // Tube & Component Cutting -> 20 legs
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'operation_id'        => $opLeg->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 20.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 60.0,
            'setup_minutes_logged'=> 10.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now(),
            'remarks'             => 'Leg cutting complete',
        ]);

        // Cutting -> 10 component units
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'operation_id'        => $opCompB->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 10.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 30.0,
            'setup_minutes_logged'=> 10.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now(),
            'remarks'             => 'Bracket cutting complete',
        ]);

        // Table Top Processing -> 5 table tops
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'operation_id'        => $opTop->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 5.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 40.0,
            'setup_minutes_logged'=> 10.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now(),
            'remarks'             => 'Table top edge banding complete',
        ]);

        // Welding / Assembly -> 5 units (in-process stage)
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'operation_id'        => $opFrameWeld->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 5.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 50.0,
            'setup_minutes_logged'=> 10.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now(),
            'remarks'             => 'Frame welding complete',
        ]);

        // Finishing & Coating -> 5 units (terminal for SFG frame)
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'operation_id'        => $opFrameFinish->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 5.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 45.0,
            'setup_minutes_logged'=> 10.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now(),
            'remarks'             => 'Frame powder coating complete',
        ]);

        // Final Assembly -> 5 units (in-process stage for FG)
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'operation_id'        => $opFinalAssy->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 5.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 60.0,
            'setup_minutes_logged'=> 10.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now(),
            'remarks'             => 'Table final assembly complete',
        ]);

        // Quality Inspection -> 5 units (in-process stage for FG)
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'operation_id'        => $opQuality->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 5.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 20.0,
            'setup_minutes_logged'=> 5.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now(),
            'remarks'             => 'Table 100% QA check passed',
        ]);

        // Packaging -> 5 units (terminal for FG Table)
        ProductionOrderProgressLog::create([
            'tenant_id'           => $this->tenantId,
            'production_order_id' => $multiOrder->id,
            'operation_id'        => $opPackaging->id,
            'machine_id'          => $this->machine->id,
            'quantity_produced'   => 5.0,
            'quantity_rejected'   => 0.0,
            'quantity_scrapped'   => 0.0,
            'run_minutes_logged'  => 25.0,
            'setup_minutes_logged'=> 5.0,
            'recorded_by'         => $this->adminUser->id,
            'recorded_at'         => now(),
            'remarks'             => 'Table packaged and labeled',
        ]);

        // 7. Execute DPR query via ReportingService
        $service = app(ReportingService::class);
        $reportData = $service->generateDailyProductionReport($this->tenantId, [
            'order_id'   => $multiOrder->id,
            'date_start' => now()->subDay()->toDateString(),
            'date_end'   => now()->addDay()->toDateString(),
        ]);

        // 8. CRITICAL VERIFICATION:
        // - FG Produced MUST be 5.0 (NEVER 60.0! NEVER 25.0!)
        $this->assertEquals(5.0, $reportData['summary']['fg_produced'], 'Finished Goods Produced must equal 5, not the sum of all progress logs');
        $this->assertEquals(5.0, $reportData['summary']['total_good_units'], 'total_good_units must equal 5');

        // - SFG Produced MUST be 5.0 (Frame Finished, not 10.0 from weld + finish)
        $this->assertEquals(5.0, $reportData['summary']['sfg_produced'], 'SFG Produced must equal 5 (Welded Frame), not 10');

        // - Component Output MUST be 35.0 (20 legs + 10 compB + 5 tops)
        $this->assertEquals(35.0, $reportData['summary']['component_produced'], 'Component output must equal 35 (20 legs + 10 brackets + 5 tops)');

        // - Events count must be 8
        $this->assertEquals(8, $reportData['summary']['total_events_count']);

        // - Total event quantity processed across stages is 60.0
        $this->assertEquals(60.0, $reportData['summary']['total_event_quantity_processed']);

        // 9. Verify Product Output breakdown
        $productOutputs = collect($reportData['product_outputs']);
        $fgRow = $productOutputs->firstWhere('output_type', 'fg');
        $this->assertNotNull($fgRow);
        $this->assertEquals('Industrial Dining Table', $fgRow['product_name']);
        $this->assertEquals(5.0, $fgRow['output_qty']);

        $sfgRow = $productOutputs->firstWhere('output_type', 'sfg');
        $this->assertNotNull($sfgRow);
        $this->assertEquals('Welded Metal Frame SFG', $sfgRow['product_name']);
        $this->assertEquals(5.0, $sfgRow['output_qty']);

        $legRow = $productOutputs->firstWhere('product_sku', 'CMP-LEG-100');
        $this->assertNotNull($legRow);
        $this->assertEquals(20.0, $legRow['output_qty']);

        // 10. Verify HTTP response renders with correct semantics
        $response = $this->get(route('production.intelligence.reports.show', [
            'type'       => 'daily-production',
            'order_id'   => $multiOrder->id,
            'date_start' => now()->subDay()->toDateString(),
            'date_end'   => now()->addDay()->toDateString(),
        ]));
        $response->assertStatus(200);
        $response->assertSee('Finished Goods (FG) Produced');
        $response->assertSee('SFG / Intermediate Output');
        $response->assertSee('Component Output');
        $response->assertSee('Industrial Dining Table');
        $response->assertSee('PO-MULTI-005');
    }
}

