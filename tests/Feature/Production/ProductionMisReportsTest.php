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
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\WorkCenter;
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

        $workCenter = WorkCenter::create([
            'tenant_id'     => $this->tenantId,
            'name'          => 'Assembly Cell A',
            'code'          => 'WC-ASM-01',
            'overhead_rate' => 60.00,
            'cost_per_hour' => 120.00,
            'status'        => 'active',
        ]);

        $machine = Machine::create([
            'tenant_id'      => $this->tenantId,
            'work_center_id' => $workCenter->id,
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
            'work_center_id'       => $workCenter->id,
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
        $op1 = ProductionOrderOperation::create([
            'tenant_id'               => $this->tenantId,
            'production_order_id'     => $this->order1->id,
            'routing_operation_id'    => $routingOp->id,
            'sequence'                => 10,
            'operation_number'        => 'OP10',
            'name'                    => 'Main Assembly',
            'work_center_id'          => $workCenter->id,
            'machine_id'              => $machine->id,
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
}
