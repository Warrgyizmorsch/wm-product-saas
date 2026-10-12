<?php

namespace Tests\Feature;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Projects\DTO\ReportFilterDTO;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Projects\Services\ProjectDashboardService;
use App\Domains\Projects\Services\ProjectReportService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase13ProductionProjectIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAlpha;
    private Tenant $tenantBeta;
    private User $alphaOwner;
    private Company $companyA;
    private Company $companyB;
    private Branch $branchA;
    private Branch $branchB;
    private Project $project;
    private Milestone $milestone;
    private TaskList $taskList;
    private Task $task;
    private Product $product;
    private ProductionBom $bom;
    private Routing $routing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantAlpha = Tenant::create([
            'name' => 'Alpha Holdings',
            'slug' => 'alpha-holdings',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantBeta = Tenant::create([
            'name' => 'Beta Holdings',
            'slug' => 'beta-holdings',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);
        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        $this->alphaOwner = User::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Alpha Owner',
            'email' => 'owner@alphaholdings.test',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenantAlpha->id, 'user_id' => $this->alphaOwner->id, 'role_id' => $ownerRole->id]);

        $this->companyA = Company::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_name' => 'Company A',
        ]);
        $this->companyB = Company::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_name' => 'Company B',
        ]);

        $this->branchA = Branch::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'name' => 'Branch A',
            'code' => 'BR-A',
        ]);
        $this->branchB = Branch::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'name' => 'Branch B',
            'code' => 'BR-B',
        ]);

        $this->project = Project::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'project_code' => 'PRJ-1301',
            'name' => 'Turbine Fabrication Project',
            'owner_id' => $this->alphaOwner->id,
            'start_date' => now()->format('Y-m-d'),
            'status' => Project::STATUS_ACTIVE,
            'budget_amount' => 50000.0,
            'budget_hours' => 500.0,
        ]);

        $this->milestone = Milestone::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'project_id' => $this->project->id,
            'name' => 'Blade Machining',
            'status' => Milestone::STATUS_ACTIVE,
        ]);

        $this->taskList = TaskList::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'project_id' => $this->project->id,
            'name' => 'Manufacturing Tasks',
        ]);

        $this->task = Task::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'project_id' => $this->project->id,
            'milestone_id' => $this->milestone->id,
            'task_list_id' => $this->taskList->id,
            'task_code' => 'PRJ-1301-T-01',
            'title' => 'Fabricate Rotor Blade Set',
            'priority' => 'High',
            'status' => Task::STATUS_IN_PROGRESS,
            'assignee_id' => $this->alphaOwner->id,
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Titanium Rotor Blade',
            'sku' => 'TRB-100',
            'type' => 'finished_good',
            'unit_cost' => 150.0,
        ]);

        $rawMaterial = Product::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Titanium Ingot',
            'sku' => 'TI-INGOT-01',
            'type' => 'raw_material',
            'unit_cost' => 50.0,
        ]);

        $uom = \App\Domains\Inventory\Models\Uom::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'category' => 'Goods',
        ]);

        $this->bom = ProductionBom::create([
            'tenant_id' => $this->tenantAlpha->id,
            'bom_number' => 'BOM-TRB-100',
            'bom_name' => 'Titanium Rotor Blade BOM',
            'product_id' => $this->product->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $uom->id,
            'effective_date' => now()->toDateString(),
            'status' => 'approved',
            'bom_type' => 'manufacturing',
            'version' => '1.0',
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenantAlpha->id,
            'bom_id' => $this->bom->id,
            'material_id' => $rawMaterial->id,
            'uom_id' => $uom->id,
            'quantity' => 2.0,
        ]);

        $this->routing = Routing::create([
            'tenant_id' => $this->tenantAlpha->id,
            'routing_number' => 'RT-TRB-100',
            'name' => 'Titanium Blade 5-Axis Milling',
            'product_id' => $this->product->id,
            'status' => 'active',
            'version' => '1.0',
        ]);

        RoutingOperation::create([
            'tenant_id' => $this->tenantAlpha->id,
            'routing_id' => $this->routing->id,
            'operation_number' => 'OP-10',
            'name' => 'CNC Milling',
            'sequence' => 1,
            'setup_time_minutes' => 30,
            'processing_time_minutes' => 60,
            'labor_cost_rate' => 0.5,
            'machine_cost_rate' => 1.0,
        ]);

        $this->withHeader('X-Tenant', $this->tenantAlpha->slug);
    }

    public function test_link_production_order_fails_when_tenant_mismatched(): void
    {
        $this->actingAs($this->alphaOwner);

        // Production order in Beta tenant
        $betaOrder = ProductionOrder::create([
            'tenant_id' => $this->tenantBeta->id,
            'order_number' => 'PO-BETA-01',
            'product_id' => $this->product->id,
            'quantity_ordered' => 5,
            'quantity_produced' => 0,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $response = $this->post(route('projects.tasks.link-production-order', [$this->project, $this->task]), [
            'production_order_id' => $betaOrder->id,
        ]);

        $response->assertSessionHasErrors('production_order_id');
        $this->assertDatabaseMissing('production_orders', [
            'id' => $betaOrder->id,
            'project_task_id' => $this->task->id,
        ]);
    }

    public function test_link_production_order_fails_when_company_mismatched(): void
    {
        $this->actingAs($this->alphaOwner);

        // Production order in Company B
        $mismatchedOrder = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyB->id,
            'branch_id' => $this->branchA->id,
            'order_number' => 'PO-MISMATCH-CO',
            'product_id' => $this->product->id,
            'quantity_ordered' => 5,
            'quantity_produced' => 0,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $response = $this->post(route('projects.tasks.link-production-order', [$this->project, $this->task]), [
            'production_order_id' => $mismatchedOrder->id,
        ]);

        $response->assertSessionHasErrors('production_order_id');
        $this->assertDatabaseMissing('production_orders', [
            'id' => $mismatchedOrder->id,
            'project_task_id' => $this->task->id,
        ]);
    }

    public function test_link_production_order_fails_when_branch_mismatched(): void
    {
        $this->actingAs($this->alphaOwner);

        // Production order in Branch B
        $mismatchedOrder = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchB->id,
            'order_number' => 'PO-MISMATCH-BR',
            'product_id' => $this->product->id,
            'quantity_ordered' => 5,
            'quantity_produced' => 0,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $response = $this->post(route('projects.tasks.link-production-order', [$this->project, $this->task]), [
            'production_order_id' => $mismatchedOrder->id,
        ]);

        $response->assertSessionHasErrors('production_order_id');
        $this->assertDatabaseMissing('production_orders', [
            'id' => $mismatchedOrder->id,
            'project_task_id' => $this->task->id,
        ]);
    }

    public function test_link_production_order_succeeds_when_tenant_company_and_branch_match(): void
    {
        $this->actingAs($this->alphaOwner);

        $validOrder = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'order_number' => 'PO-VALID-LINK',
            'product_id' => $this->product->id,
            'quantity_ordered' => 5,
            'quantity_produced' => 0,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        $response = $this->post(route('projects.tasks.link-production-order', [$this->project, $this->task]), [
            'production_order_id' => $validOrder->id,
        ]);

        $response->assertRedirect(route('projects.tasks.show', [$this->project, $this->task]));
        $response->assertSessionHas('success');

        $validOrder->refresh();
        $this->assertEquals($this->project->id, $validOrder->project_id);
        $this->assertEquals($this->milestone->id, $validOrder->project_milestone_id);
        $this->assertEquals($this->task->id, $validOrder->project_task_id);
    }

    public function test_store_production_order_rejects_cross_company_project_link(): void
    {
        $this->actingAs($this->alphaOwner);

        $response = $this->post(route('production.orders.store'), [
            'product_id' => $this->product->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 10,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'company_id' => $this->companyB->id, // Mismatched with project which is in Company A
            'branch_id' => $this->branchA->id,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        $response->assertSessionHasErrors('project_id');
    }

    public function test_store_production_order_persists_project_context(): void
    {
        $this->actingAs($this->alphaOwner);

        $response = $this->post(route('production.orders.store'), [
            'product_id' => $this->product->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 10,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'project_id' => $this->project->id,
            'project_milestone_id' => $this->milestone->id,
            'project_task_id' => $this->task->id,
        ]);

        $response->assertSessionHasNoErrors();

        $createdOrder = ProductionOrder::where('tenant_id', $this->tenantAlpha->id)
            ->where('project_id', $this->project->id)
            ->first();

        $this->assertNotNull($createdOrder);
        $this->assertEquals($this->project->id, $createdOrder->project_id);
        $this->assertEquals($this->milestone->id, $createdOrder->project_milestone_id);
        $this->assertEquals($this->task->id, $createdOrder->project_task_id);
    }

    public function test_standalone_production_order_remains_valid_without_project(): void
    {
        $this->actingAs($this->alphaOwner);

        $response = $this->post(route('production.orders.store'), [
            'product_id' => $this->product->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 7,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(4)->format('Y-m-d'),
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
        ]);

        $response->assertSessionHasNoErrors();

        $standaloneOrder = ProductionOrder::where('tenant_id', $this->tenantAlpha->id)
            ->whereNull('project_id')
            ->first();

        $this->assertNotNull($standaloneOrder);
        $this->assertNull($standaloneOrder->project_id);
        $this->assertNull($standaloneOrder->project_task_id);
    }

    public function test_reverse_navigation_on_production_order_show_and_index(): void
    {
        $this->actingAs($this->alphaOwner);

        $linkedOrder = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'order_number' => 'PO-REV-NAV-01',
            'product_id' => $this->product->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 15,
            'quantity_produced' => 5,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_milestone_id' => $this->milestone->id,
            'project_task_id' => $this->task->id,
        ]);

        // Test Show View contains project code and link
        $showResponse = $this->get(route('production.orders.show', $linkedOrder->id));
        $showResponse->assertOk();
        $showResponse->assertSee($this->project->project_code);
        $showResponse->assertSee($this->task->task_code);
        $showResponse->assertSee(route('projects.show', $this->project->project_code));

        // Test Index View contains project link
        $indexResponse = $this->get(route('production.orders.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee($this->project->project_code);
    }

    public function test_project_manufacturing_summary_aggregates_live_data(): void
    {
        $this->actingAs($this->alphaOwner);

        // Create 1 draft, 1 in-progress, 1 completed order
        ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'order_number' => 'PO-SUM-01',
            'product_id' => $this->product->id,
            'quantity_ordered' => 10,
            'quantity_produced' => 0,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_DRAFT,
            'project_id' => $this->project->id,
        ]);

        ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'order_number' => 'PO-SUM-02',
            'product_id' => $this->product->id,
            'quantity_ordered' => 20,
            'quantity_produced' => 10,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_IN_PROGRESS,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'order_number' => 'PO-SUM-03',
            'product_id' => $this->product->id,
            'quantity_ordered' => 30,
            'quantity_produced' => 30,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_COMPLETED,
            'project_id' => $this->project->id,
        ]);

        // Unrelated standalone order in another project or no project
        ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-OTHER-99',
            'product_id' => $this->product->id,
            'quantity_ordered' => 100,
            'quantity_produced' => 100,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(3)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_COMPLETED,
        ]);

        $response = $this->get(route('projects.show', $this->project));
        $response->assertOk();
        $response->assertSee('PO-SUM-01');
        $response->assertSee('PO-SUM-02');
        $response->assertSee('PO-SUM-03');
        $response->assertDontSee('PO-OTHER-99');

        // Check manufacturing summary data passed to view
        $summary = $response->viewData('manufacturingSummary');
        $this->assertNotNull($summary);
        $this->assertEquals(3, $summary['total_orders']);
        $this->assertEquals(1, $summary['completed_orders']);
        $this->assertEquals(1, $summary['in_progress_orders']);
        $this->assertEquals(1, $summary['pending_orders']);
        $this->assertEquals(60.0, $summary['ordered_quantity']);
        $this->assertEquals(40.0, $summary['produced_quantity']);
        $this->assertEquals(66.7, $summary['overall_progress']);

        // Check Cost Consumed KPI on Project show
        $costSummary = $response->viewData('costSummary');
        $this->assertNotNull($costSummary);
        $this->assertArrayHasKey('total_consumed', $costSummary);
        $this->assertArrayHasKey('mfg_cost', $costSummary);
        $response->assertSee(__('projects.cost_consumed'));
    }


    public function test_financial_reporting_aggregates_timesheet_and_production_actual_costs(): void
    {
        $this->actingAs($this->alphaOwner);

        // 1. Add timesheet log: 10 hours @ $50/hr = $500
        TimeLog::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'project_id' => $this->project->id,
            'task_id' => $this->task->id,
            'user_id' => $this->alphaOwner->id,
            'hours' => 10.0,
            'hourly_rate' => 50.0,
            'log_date' => now()->format('Y-m-d'),
            'approval_status' => TimeLog::STATUS_APPROVED,
        ]);

        // 2. Create production order with actual material issues and operations:
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'company_id' => $this->companyA->id,
            'branch_id' => $this->branchA->id,
            'order_number' => 'PO-COST-01',
            'product_id' => $this->product->id,
            'bom_id' => $this->bom->id,
            'routing_id' => $this->routing->id,
            'quantity_ordered' => 10,
            'quantity_produced' => 10,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_COMPLETED,
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]);

        // Issue 20 units of raw material @ $50/unit = $1,000 actual material cost
        $rawMat = Product::where('sku', 'TI-INGOT-01')->firstOrFail();
        ProductionOrderIssue::create([
            'tenant_id' => $this->tenantAlpha->id,
            'production_order_id' => $order->id,
            'product_id' => $rawMat->id,
            'quantity_issued' => 20.0,
            'issued_at' => now(),
            'created_by' => $this->alphaOwner->id,
        ]);

        // Operation: 60 minutes actual processing @ $0.5 labor rate + $1.0 machine rate = $30 labor + $60 machine = $90
        $routingOp = RoutingOperation::where('routing_id', $this->routing->id)->firstOrFail();
        ProductionOrderOperation::create([
            'tenant_id' => $this->tenantAlpha->id,
            'production_order_id' => $order->id,
            'routing_operation_id' => $routingOp->id,
            'operation_number' => 'OP-10',
            'name' => 'CNC Milling',
            'sequence' => 1,
            'status' => 'completed',
            'setup_time_planned' => 30,
            'processing_time_planned' => 60,
            'total_time_planned' => 90,
            'setup_time_actual' => 0,
            'processing_time_actual' => 60,
            'quantity_produced' => 10,
        ]);

        // Total Production Cost = $1,000 (material) + $30 (labor) + $60 (machine) = $1,090.0
        // Total Timesheet Cost = 10h * $50 = $500.0
        // Expected Combined Actual Cost = $1,590.0

        $reportService = app(ProjectReportService::class);
        $filters = new ReportFilterDTO(tenant_id: $this->tenantAlpha->id);
        $reportData = $reportService->getBudgetCostReportData($filters);

        $projectRow = $reportData->firstWhere('id', $this->project->id);
        $this->assertNotNull($projectRow);
        $this->assertEquals(500.0, $projectRow['timesheet_cost']);
        $this->assertEquals(1090.0, $projectRow['production_cost']);
        $this->assertEquals(1590.0, $projectRow['actual_cost']);

        // Check Portfolio KPIs in ProjectDashboardService
        $dashboardService = app(ProjectDashboardService::class);
        $kpi = $dashboardService->getPortfolioKpis($filters);
        $this->assertEquals(1590.0, $kpi->total_incurred_cost);

        // Ensure standalone orders elsewhere do NOT get double counted or included
        $standaloneOrder = ProductionOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'order_number' => 'PO-STANDALONE-COST',
            'product_id' => $this->product->id,
            'quantity_ordered' => 5,
            'quantity_produced' => 5,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'status' => ProductionOrder::STATUS_COMPLETED,
        ]);
        ProductionOrderIssue::create([
            'tenant_id' => $this->tenantAlpha->id,
            'production_order_id' => $standaloneOrder->id,
            'product_id' => $rawMat->id,
            'quantity_issued' => 10.0, // $500
            'issued_at' => now(),
            'created_by' => $this->alphaOwner->id,
        ]);

        // Re-check report: projectRow cost must still be strictly 1590.0!
        $refreshedReportData = $reportService->getBudgetCostReportData($filters);
        $refreshedRow = $refreshedReportData->firstWhere('id', $this->project->id);
        $this->assertEquals(1590.0, $refreshedRow['actual_cost']);
    }

    public function test_production_order_create_preselects_product_and_quantity_from_task(): void
    {
        $this->actingAs($this->alphaOwner);

        $customer = \App\Domains\CRM\Models\Customer::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Preselect Customer',
            'email' => 'preselect@example.com',
        ]);

        $so = \App\Domains\Sales\Models\SalesOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'customer_id' => $customer->id,
            'sales_order_number' => 'SO-TEST-PRESELECT',
            'order_date' => now()->format('Y-m-d'),
            'status' => 'Confirmed',
            'total_amount' => 5000.00,
        ]);

        $soItem = \App\Domains\Sales\Models\SalesOrderItem::create([
            'tenant_id' => $this->tenantAlpha->id,
            'sales_order_id' => $so->id,
            'product_id' => $this->product->id,
            'item_name' => $this->product->name,
            'quantity' => 12,
            'unit_price' => 200.00,
            'amount' => 2400.00,
        ]);

        $this->milestone->update(['sales_order_item_id' => $soItem->id]);

        $response = $this->get(route('production.orders.create', [
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
        ]));

        $response->assertOk();
        $response->assertSee($this->project->project_code);
        $response->assertSee($this->task->task_code);
        $response->assertViewHas('selectedProductId', $this->product->id);
        $response->assertViewHas('selectedQty', 12);
    }

    public function test_task_workspace_shows_create_po_for_manufacture_and_hides_for_trade(): void
    {
        $this->actingAs($this->alphaOwner);

        $customer = \App\Domains\CRM\Models\Customer::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Trade Customer',
            'email' => 'trade@example.com',
        ]);

        $so = \App\Domains\Sales\Models\SalesOrder::create([
            'tenant_id' => $this->tenantAlpha->id,
            'customer_id' => $customer->id,
            'sales_order_number' => 'SO-TEST-TRADE',
            'order_date' => now()->format('Y-m-d'),
            'status' => 'Confirmed',
            'total_amount' => 1500.00,
        ]);

        $tradeProduct = Product::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name' => 'Traded Packaging Box',
            'sku' => 'BOX-TRADE',
            'type' => 'goods',
            'supplier_method' => 'trade',
            'planning_type' => 'purchase',
            'unit_cost' => 5.0,
        ]);

        $soItemTrade = \App\Domains\Sales\Models\SalesOrderItem::create([
            'tenant_id' => $this->tenantAlpha->id,
            'sales_order_id' => $so->id,
            'product_id' => $tradeProduct->id,
            'item_name' => $tradeProduct->name,
            'quantity' => 50,
            'unit_price' => 10.00,
            'amount' => 500.00,
        ]);

        $tradeMilestone = Milestone::create([
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'name' => 'Deliver Boxes',
            'sales_order_item_id' => $soItemTrade->id,
            'start_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        $tradeTask = Task::create([
            'tenant_id' => $this->tenantAlpha->id,
            'project_id' => $this->project->id,
            'milestone_id' => $tradeMilestone->id,
            'task_list_id' => $this->taskList->id,
            'task_code' => 'PRJ-1301-T-TRADE',
            'title' => 'Deliver Boxes',
            'priority' => 'Medium',
            'status' => Task::STATUS_OPEN,
            'assignee_id' => $this->alphaOwner->id,
        ]);

        // Trade task workspace: does NOT show Create Production Order button
        $responseTrade = $this->get(route('projects.tasks.show', [$this->project, $tradeTask]));
        $responseTrade->assertOk();
        $responseTrade->assertViewHas('isTradeItem', true);
        $responseTrade->assertDontSee(route('production.orders.create', ['project_id' => $this->project->id, 'project_task_id' => $tradeTask->id]));

        // Manufactured task workspace: DOES show Create Production Order button
        $this->product->update(['supplier_method' => 'manufacture', 'planning_type' => 'manufacture']);
        $soItemMfg = \App\Domains\Sales\Models\SalesOrderItem::create([
            'tenant_id' => $this->tenantAlpha->id,
            'sales_order_id' => $so->id,
            'product_id' => $this->product->id,
            'item_name' => $this->product->name,
            'quantity' => 10,
            'unit_price' => 100.00,
            'amount' => 1000.00,
        ]);
        $this->milestone->update(['sales_order_item_id' => $soItemMfg->id]);

        $responseMfg = $this->get(route('projects.tasks.show', [$this->project, $this->task]));
        $responseMfg->assertOk();
        $responseMfg->assertViewHas('isManufacturedItem', true);
        $responseMfg->assertSee(route('production.orders.create', [
            'project_id' => $this->project->id,
            'project_task_id' => $this->task->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]));
    }
}
