<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Product;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Sales\Events\InvoicePosted;
use App\Domains\Sales\Models\Invoice;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProjectBillingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $tenantOwner;
    private User $unauthorizedUser;
    private Customer $customer;
    private Project $project;
    private Product $serviceProduct;
    private TaskList $taskList;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Primary Workspace',
            'slug'   => 'primary-workspace',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->otherTenant = Tenant::create([
            'name'   => 'Other Workspace',
            'slug'   => 'other-workspace',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        $readOnlyRole = Role::query()->whereNull('tenant_id')->where('slug', 'read_only')->firstOrFail();

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Tenant Owner',
            'email'     => 'owner@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->tenantOwner->id,
            'role_id'   => $ownerRole->id,
        ]);

        $this->unauthorizedUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Read Only Member',
            'email'     => 'readonly@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->unauthorizedUser->id,
            'role_id'   => $readOnlyRole->id,
        ]);

        $this->customer = Customer::create([
            'tenant_id'     => $this->tenant->id,
            'name'          => 'Acme Client Corp',
            'email'         => 'billing@acme.com',
            'billing_state' => 'Maharashtra',
        ]);

        $this->project = Project::create([
            'tenant_id'      => $this->tenant->id,
            'project_code'   => 'PRJ-BILL-01',
            'name'           => 'Enterprise Cloud Migration',
            'customer_id'    => $this->customer->id,
            'owner_id'       => $this->tenantOwner->id,
            'start_date'     => '2026-10-01',
            'end_date'       => '2026-12-31',
            'budget_type'    => 'Time & Material',
            'budget_amount'  => 500000.00,
            'billing_method' => 'Task Based',
            'status'         => Project::STATUS_ACTIVE,
        ]);

        $this->serviceProduct = Product::create([
            'tenant_id'     => $this->tenant->id,
            'name'          => 'Cloud Engineering Services',
            'sku'           => 'SRV-ENG-01',
            'type'          => 'component',
            'item_type'     => 'Service',
            'status'        => 'active',
            'selling_price' => 1500.00,
            'gst_rate'      => 18.00,
        ]);

        $this->taskList = TaskList::create([
            'tenant_id'  => $this->tenant->id,
            'project_id' => $this->project->id,
            'name'       => 'Infrastructure Phase',
        ]);

        $this->task = Task::create([
            'tenant_id'     => $this->tenant->id,
            'project_id'    => $this->project->id,
            'task_list_id'  => $this->taskList->id,
            'task_code'     => 'TSK-001',
            'title'         => 'Kubernetes Cluster Setup',
            'start_date'    => '2026-10-01',
            'due_date'      => '2026-10-05',
            'status'        => Task::STATUS_COMPLETED,
            'priority'      => 'High',
        ]);
    }

    public function test_can_fetch_unbilled_approved_time_logs_and_completed_milestones(): void
    {
        // 1. Eligible approved time log
        $eligibleLog = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => '2026-10-01',
            'hours'           => 8.0,
            'is_billable'     => true,
            'hourly_rate'     => 1500.00,
            'approval_status' => TimeLog::STATUS_APPROVED,
            'is_invoiced'     => false,
        ]);

        // 2. Pending log (should be excluded)
        TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => '2026-10-02',
            'hours'           => 4.0,
            'is_billable'     => true,
            'hourly_rate'     => 1500.00,
            'approval_status' => TimeLog::STATUS_PENDING,
            'is_invoiced'     => false,
        ]);

        // 3. Eligible completed milestone
        $eligibleMilestone = Milestone::create([
            'tenant_id'             => $this->tenant->id,
            'project_id'            => $this->project->id,
            'name'                  => 'Milestone 1: Cluster Provisioned',
            'status'                => Milestone::STATUS_COMPLETED,
            'completion_percentage' => 100,
            'billing_amount'        => 50000.00,
            'is_invoiced'           => false,
        ]);

        // 4. Incomplete milestone (should be excluded)
        Milestone::create([
            'tenant_id'             => $this->tenant->id,
            'project_id'            => $this->project->id,
            'name'                  => 'Milestone 2: Security Hardening',
            'status'                => Milestone::STATUS_ACTIVE,
            'completion_percentage' => 50,
            'billing_amount'        => 30000.00,
            'is_invoiced'           => false,
        ]);

        $billingService = app(\App\Domains\Projects\Services\ProjectBillingService::class);
        $unbilledLogs = $billingService->getUnbilledTimeLogs($this->project);
        $unbilledMilestones = $billingService->getUnbilledMilestones($this->project);

        $this->assertCount(1, $unbilledLogs);
        $this->assertEquals($eligibleLog->id, $unbilledLogs->first()->id);

        $this->assertCount(1, $unbilledMilestones);
        $this->assertEquals($eligibleMilestone->id, $unbilledMilestones->first()->id);
    }

    public function test_can_generate_draft_sales_invoice_for_billable_time_logs(): void
    {
        Event::fake([InvoicePosted::class]);

        $log = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => '2026-10-01',
            'hours'           => 10.0,
            'is_billable'     => true,
            'hourly_rate'     => 1500.00,
            'approval_status' => TimeLog::STATUS_APPROVED,
            'is_invoiced'     => false,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.billing.store', $this->project), [
                'invoice_date'       => '2026-10-01',
                'due_date'           => '2026-10-31',
                'payment_terms'      => 'Net 30',
                'service_product_id' => $this->serviceProduct->id,
                'time_log_ids'       => [$log->id],
                'notes'              => 'Project Sprint 1 Invoicing',
            ]);

        $response->assertRedirect(route('projects.show', [$this->project, 'tab' => 'billing']));
        $response->assertSessionHas('success');

        // Assert Invoice created in Draft status
        $invoice = Invoice::where('project_id', $this->project->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('Draft', $invoice->status);
        $this->assertEquals($this->customer->id, $invoice->customer_id);
        $this->assertEquals(15000.00, (float) $invoice->subtotal);

        // Tax 18% of 15000 = 2700
        $this->assertEquals(2700.00, (float) $invoice->tax_amount);
        $this->assertEquals(17700.00, (float) $invoice->total_amount);

        // Assert InvoiceItem created with traceability info
        $this->assertCount(1, $invoice->items);
        $item = $invoice->items->first();
        $this->assertEquals($this->serviceProduct->id, $item->product_id);
        $this->assertStringContainsString('Kubernetes Cluster Setup', $item->item_name);

        // Assert TimeLog marked as invoiced
        $log->refresh();
        $this->assertTrue($log->is_invoiced);
        $this->assertEquals($invoice->id, $log->invoice_id);

        // Assert InvoicePosted event was NOT dispatched upon draft creation
        Event::assertNotDispatched(InvoicePosted::class);
    }

    public function test_can_generate_draft_sales_invoice_for_completed_milestones(): void
    {
        $milestone = Milestone::create([
            'tenant_id'             => $this->tenant->id,
            'project_id'            => $this->project->id,
            'name'                  => 'Discovery Deliverable',
            'description'           => 'Architecture & Tech Spec Complete',
            'status'                => Milestone::STATUS_COMPLETED,
            'completion_percentage' => 100,
            'billing_amount'        => 75000.00,
            'is_invoiced'           => false,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.billing.store', $this->project), [
                'invoice_date'       => '2026-10-01',
                'due_date'           => '2026-10-31',
                'service_product_id' => $this->serviceProduct->id,
                'milestone_ids'      => [$milestone->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $invoice = Invoice::where('project_id', $this->project->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('Draft', $invoice->status);
        $this->assertEquals(75000.00, (float) $invoice->subtotal);

        $milestone->refresh();
        $this->assertTrue($milestone->is_invoiced);
        $this->assertEquals($invoice->id, $milestone->invoice_id);
    }

    public function test_double_billing_is_strictly_rejected(): void
    {
        $log = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => '2026-10-01',
            'hours'           => 5.0,
            'is_billable'     => true,
            'hourly_rate'     => 1000.00,
            'approval_status' => TimeLog::STATUS_APPROVED,
            'is_invoiced'     => true, // Already invoiced!
            'invoice_id'      => 999,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.billing.store', $this->project), [
                'invoice_date'       => '2026-10-01',
                'due_date'           => '2026-10-31',
                'service_product_id' => $this->serviceProduct->id,
                'time_log_ids'       => [$log->id],
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('invoices', [
            'project_id' => $this->project->id,
        ]);
    }

    public function test_posting_draft_invoice_via_sales_controller_fires_invoice_posted(): void
    {
        Event::fake([InvoicePosted::class]);

        $log = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => '2026-10-01',
            'hours'           => 4.0,
            'is_billable'     => true,
            'hourly_rate'     => 2000.00,
            'approval_status' => TimeLog::STATUS_APPROVED,
            'is_invoiced'     => false,
        ]);

        // Generate draft invoice via PM
        $billingService = app(\App\Domains\Projects\Services\ProjectBillingService::class);
        $invoice = $billingService->generateInvoice($this->project, [
            'invoice_date'       => '2026-10-01',
            'due_date'           => '2026-10-31',
            'service_product_id' => $this->serviceProduct->id,
            'time_log_ids'       => [$log->id],
        ]);

        $this->assertEquals('Draft', $invoice->status);
        Event::assertNotDispatched(InvoicePosted::class);

        // Now post the invoice through the existing Sales flow
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('sales.invoices.post', $invoice->id));

        $response->assertRedirect(route('sales.invoices.show', $invoice->id));

        $invoice->refresh();
        $this->assertEquals('Posted', $invoice->status);
        Event::assertDispatched(InvoicePosted::class);
    }

    public function test_unauthorized_user_cannot_generate_invoice(): void
    {
        $log = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => '2026-10-01',
            'hours'           => 5.0,
            'is_billable'     => true,
            'hourly_rate'     => 1000.00,
            'approval_status' => TimeLog::STATUS_APPROVED,
            'is_invoiced'     => false,
        ]);

        $response = $this->actingAs($this->unauthorizedUser)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.billing.store', $this->project), [
                'invoice_date'       => '2026-10-01',
                'due_date'           => '2026-10-31',
                'service_product_id' => $this->serviceProduct->id,
                'time_log_ids'       => [$log->id],
            ]);

        $response->assertForbidden();
    }

    public function test_multi_tenant_isolation_on_billing(): void
    {
        $otherProject = Project::create([
            'tenant_id'    => $this->otherTenant->id,
            'project_code' => 'OTHER-01',
            'name'         => 'Other Tenant Project',
            'start_date'   => '2026-10-01',
            'status'       => Project::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('projects.billing.index', $otherProject));

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }
}
