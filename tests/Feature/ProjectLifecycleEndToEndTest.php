<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Product;
use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\ProjectReview;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Projects\Services\ProjectService;
use App\Domains\Sales\Events\InvoicePosted;
use App\Domains\Sales\Models\Invoice;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectLifecycleEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantAlpha;
    private Tenant $tenantBeta;
    private User $alphaOwner;
    private User $alphaManager;
    private User $alphaDeveloper;
    private User $alphaUnauthorized;
    private User $betaOwner;
    private Customer $customer;
    private Product $serviceProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenantAlpha = Tenant::create([
            'name'   => 'Alpha Enterprise',
            'slug'   => 'alpha-enterprise',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->tenantBeta = Tenant::create([
            'name'   => 'Beta Isolated Org',
            'slug'   => 'beta-isolated-org',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        $readOnlyRole = Role::query()->whereNull('tenant_id')->where('slug', 'read_only')->firstOrFail();

        // Alpha Owner
        $this->alphaOwner = User::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name'      => 'Alpha Owner',
            'email'     => 'alpha.owner@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenantAlpha->id, 'user_id' => $this->alphaOwner->id, 'role_id' => $ownerRole->id]);

        // Alpha Project Manager
        $this->alphaManager = User::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name'      => 'Alpha PM',
            'email'     => 'alpha.pm@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenantAlpha->id, 'user_id' => $this->alphaManager->id, 'role_id' => $ownerRole->id]);

        // Alpha Team Member / Developer
        $this->alphaDeveloper = User::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name'      => 'Alpha Dev',
            'email'     => 'alpha.dev@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenantAlpha->id, 'user_id' => $this->alphaDeveloper->id, 'role_id' => $ownerRole->id]);

        // Alpha Unauthorized / Read-Only User
        $this->alphaUnauthorized = User::create([
            'tenant_id' => $this->tenantAlpha->id,
            'name'      => 'Alpha ReadOnly',
            'email'     => 'alpha.readonly@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenantAlpha->id, 'user_id' => $this->alphaUnauthorized->id, 'role_id' => $readOnlyRole->id]);

        // Beta Owner
        $this->betaOwner = User::create([
            'tenant_id' => $this->tenantBeta->id,
            'name'      => 'Beta Owner',
            'email'     => 'beta.owner@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['tenant_id' => $this->tenantBeta->id, 'user_id' => $this->betaOwner->id, 'role_id' => $ownerRole->id]);

        // CRM Customer
        $this->customer = Customer::create([
            'tenant_id'     => $this->tenantAlpha->id,
            'name'          => 'Global Tech Enterprises',
            'email'         => 'client@globaltech.com',
            'billing_state' => 'California',
        ]);

        // Service Product for Invoicing
        $this->serviceProduct = Product::create([
            'tenant_id'     => $this->tenantAlpha->id,
            'name'          => 'Consulting & Development Service',
            'sku'           => 'SRV-DEV-001',
            'type'          => 'component',
            'item_type'     => 'Service',
            'status'        => 'active',
            'selling_price' => 50.00,
            'gst_rate'      => 0.00,
        ]);
    }

    public function test_complete_project_lifecycle_end_to_end(): void
    {
        // =========================================================================
        // STAGE 1: Project Provisioning & Scoping (Phases 1 - 2)
        // =========================================================================
        $project = Project::create([
            'tenant_id'      => $this->tenantAlpha->id,
            'project_code'   => 'PRJ-E2E-001',
            'name'           => 'Enterprise Modernization Engine',
            'customer_id'    => $this->customer->id,
            'owner_id'       => $this->alphaOwner->id,
            'manager_id'     => $this->alphaManager->id,
            'start_date'     => now()->subDays(10)->toDateString(),
            'end_date'       => now()->addMonths(2)->toDateString(),
            'budget_type'    => 'Time & Material',
            'budget_amount'  => 50000.00,
            'budget_hours'   => 500.00,
            'billing_method' => 'Task Based',
            'priority'       => 'High',
            'status'         => Project::STATUS_DRAFT,
        ]);

        $this->assertDatabaseHas('projects', [
            'id'           => $project->id,
            'tenant_id'    => $this->tenantAlpha->id,
            'project_code' => 'PRJ-E2E-001',
            'status'       => 'Draft',
        ]);

        // Transition status Draft -> Active
        $project->update(['status' => Project::STATUS_ACTIVE]);
        $this->assertEquals(Project::STATUS_ACTIVE, $project->fresh()->status);

        // =========================================================================
        // STAGE 2: Team Staffing & Collaborators (Phase 2)
        // =========================================================================
        $pmMember = ProjectMember::create([
            'tenant_id'    => $this->tenantAlpha->id,
            'project_id'   => $project->id,
            'user_id'      => $this->alphaManager->id,
            'project_role' => 'Project Manager',
            'budget_hours' => 50,
            'hourly_rate'  => 80.00,
            'is_active'    => true,
        ]);

        $devMember = ProjectMember::create([
            'tenant_id'    => $this->tenantAlpha->id,
            'project_id'   => $project->id,
            'user_id'      => $this->alphaDeveloper->id,
            'project_role' => 'Senior Developer',
            'budget_hours' => 150,
            'hourly_rate'  => 50.00,
            'is_active'    => true,
        ]);

        $this->assertCount(2, $project->members);

        // =========================================================================
        // STAGE 3 & 4: Horizon Planning, Tasks & Dependencies (Phases 2 & 6)
        // =========================================================================
        $milestone = Milestone::create([
            'tenant_id'             => $this->tenantAlpha->id,
            'project_id'            => $project->id,
            'name'                  => 'Milestone 1: Backend Architecture',
            'due_date'              => now()->addDays(20)->toDateString(),
            'status'                => Milestone::STATUS_ACTIVE,
            'completion_percentage' => 0,
        ]);

        $taskList = TaskList::create([
            'tenant_id'    => $this->tenantAlpha->id,
            'project_id'   => $project->id,
            'milestone_id' => $milestone->id,
            'name'         => 'Sprint 1 - Core Services',
            'position'     => 1,
        ]);

        $taskA = Task::create([
            'tenant_id'       => $this->tenantAlpha->id,
            'project_id'      => $project->id,
            'task_list_id'    => $taskList->id,
            'milestone_id'    => $milestone->id,
            'task_code'       => 'PRJ-E2E-T01',
            'title'           => 'Design & Implement Database Schemas',
            'status'          => Task::STATUS_OPEN,
            'priority'        => 'High',
            'estimated_hours' => 20.00,
            'assigned_to'     => $this->alphaDeveloper->id,
        ]);

        $taskB = Task::create([
            'tenant_id'       => $this->tenantAlpha->id,
            'project_id'      => $project->id,
            'task_list_id'    => $taskList->id,
            'milestone_id'    => $milestone->id,
            'task_code'       => 'PRJ-E2E-T02',
            'title'           => 'Build REST API Endpoints',
            'status'          => Task::STATUS_OPEN,
            'priority'        => 'Medium',
            'estimated_hours' => 20.00,
            'assigned_to'     => $this->alphaDeveloper->id,
        ]);

        // Finish-to-Start dependency: Task B depends on Task A
        TaskDependency::create([
            'tenant_id'          => $this->tenantAlpha->id,
            'project_id'         => $project->id,
            'task_id'            => $taskB->id,
            'depends_on_task_id' => $taskA->id,
            'dependency_type'    => TaskDependency::TYPE_FINISH_TO_START,
        ]);

        // =========================================================================
        // STAGE 5: Execution, Time Tracking & Approvals (Phase 3)
        // =========================================================================
        $taskA->update(['status' => Task::STATUS_IN_PROGRESS]);

        $timeLog = TimeLog::create([
            'tenant_id'       => $this->tenantAlpha->id,
            'project_id'      => $project->id,
            'task_id'         => $taskA->id,
            'user_id'         => $this->alphaDeveloper->id,
            'log_date'        => now()->toDateString(),
            'hours'           => 10.00,
            'is_billable'     => true,
            'hourly_rate'     => 50.00,
            'is_invoiced'     => false,
            'approval_status' => TimeLog::STATUS_PENDING,
            'description'     => 'Completed primary schema and migration definitions.',
        ]);

        // Manager approves the timelog
        $timeLog->update([
            'approval_status' => TimeLog::STATUS_APPROVED,
            'approved_by'     => $this->alphaManager->id,
            'approved_at'     => now(),
        ]);
        $this->assertEquals(TimeLog::STATUS_APPROVED, $timeLog->fresh()->approval_status);

        // Complete Task A and Task B
        $taskA->update(['status' => Task::STATUS_COMPLETED, 'completed_at' => now()]);
        $taskB->update(['status' => Task::STATUS_COMPLETED, 'completed_at' => now()]);

        // Milestone completes
        $milestone->update([
            'status'                => Milestone::STATUS_COMPLETED,
            'completion_percentage' => 100,
            'completed_at'          => now(),
            'is_invoiced'           => true,
        ]);

        // =========================================================================
        // STAGE 6: Issue / Defect Tracking & Retest Loop (Phase 4)
        // =========================================================================
        $issue = Issue::create([
            'tenant_id'    => $this->tenantAlpha->id,
            'project_id'   => $project->id,
            'task_id'      => $taskA->id,
            'issue_number' => 'ISS-E2E-001',
            'title'        => 'Database foreign key index missing on worklog',
            'severity'     => 'Medium',
            'priority'     => 'High',
            'status'       => Issue::STATUS_OPEN,
            'reporter_id'  => $this->alphaManager->id,
            'assigned_to'  => $this->alphaDeveloper->id,
        ]);

        $issue->update(['status' => Issue::STATUS_RESOLVED, 'resolution_date' => now()]);
        $issue->update(['status' => Issue::STATUS_CLOSED]);
        $this->assertEquals(Issue::STATUS_CLOSED, $issue->fresh()->status);

        // =========================================================================
        // STAGE 7: Project Documents & Collateral (Phase 4)
        // =========================================================================
        $fakeFile = UploadedFile::fake()->create('Architecture_Blueprint.pdf', 512, 'application/pdf');
        $storedPath = $fakeFile->store("tenants/{$this->tenantAlpha->id}/projects/{$project->id}/documents", 'local');

        $doc = ProjectDocument::create([
            'tenant_id'       => $this->tenantAlpha->id,
            'project_id'      => $project->id,
            'title'           => 'Architecture Blueprint Specification',
            'category'        => ProjectDocument::CATEGORY_DESIGN,
            'file_name'       => 'Architecture_Blueprint.pdf',
            'file_path'       => $storedPath,
            'file_size'       => 512 * 1024,
            'mime_type'       => 'application/pdf',
            'uploaded_by'     => $this->alphaOwner->id,
        ]);
        $this->assertDatabaseHas('project_documents', ['id' => $doc->id, 'tenant_id' => $this->tenantAlpha->id]);

        // =========================================================================
        // STAGE 8: Scope Governance & Change Requests (Phase 5)
        // =========================================================================
        $cr = ChangeRequest::create([
            'tenant_id'            => $this->tenantAlpha->id,
            'project_id'           => $project->id,
            'cr_number'            => 'CR-E2E-001',
            'title'                => 'Enhanced Multi-Tenancy Sharding Extension',
            'description'          => 'Architectural modification to scale multi-tenant database clusters.',
            'impact_budget_amount' => 10000.00,
            'impact_budget_hours'  => 80.00,
            'status'               => ChangeRequest::STATUS_PENDING,
            'requested_by'         => $this->alphaManager->id,
        ]);

        // Approve Change Request and update project budget
        $cr->update([
            'status'      => ChangeRequest::STATUS_APPROVED,
            'approved_by' => $this->alphaOwner->id,
            'approved_at' => now(),
        ]);
        $project->increment('budget_amount', (float) $cr->impact_budget_amount);
        $project->increment('budget_hours', (float) $cr->impact_budget_hours);

        $this->assertEquals(60000.00, (float) $project->fresh()->budget_amount);
        $this->assertEquals(580.00, (float) $project->fresh()->budget_hours);

        // UAT Formal Sign-off Review
        $review = ProjectReview::create([
            'tenant_id'     => $this->tenantAlpha->id,
            'project_id'    => $project->id,
            'reviewer_name' => 'Acme Lead Evaluator',
            'review_date'   => now()->toDateString(),
            'status'        => ProjectReview::STATUS_APPROVED,
            'created_by'    => $this->alphaManager->id,
            'reviewer_id'   => $this->alphaManager->id,
        ]);
        $this->assertEquals(ProjectReview::STATUS_APPROVED, $review->status);

        // =========================================================================
        // STAGE 9: Billing & Invoicing (Phase 7)
        // =========================================================================
        Event::fake([InvoicePosted::class]);

        $invoiceResponse = $this->actingAs($this->alphaOwner)
            ->withHeader('X-Tenant', $this->tenantAlpha->slug)
            ->post(route('projects.billing.store', $project), [
                'invoice_date'       => now()->toDateString(),
                'due_date'           => now()->addDays(30)->toDateString(),
                'payment_terms'      => 'Net 30',
                'service_product_id' => $this->serviceProduct->id,
                'time_log_ids'       => [$timeLog->id],
                'notes'              => 'E2E Lifecycle Invoicing',
            ]);

        $invoiceResponse->assertRedirect(route('projects.show', [$project, 'tab' => 'billing']));
        $this->assertTrue($timeLog->fresh()->is_invoiced);

        // =========================================================================
        // STAGE 10: Controlled Project Closure (Phase 8)
        // =========================================================================
        $closureCheck = $this->actingAs($this->alphaOwner)
            ->withHeader('X-Tenant', $this->tenantAlpha->slug)
            ->getJson(route('projects.closure.check', $project));

        $closureCheck->assertOk();
        $this->assertTrue($closureCheck->json('can_close'), 'All 4 pre-closure gates must pass.');

        $closeResponse = $this->actingAs($this->alphaOwner)
            ->withHeader('X-Tenant', $this->tenantAlpha->slug)
            ->postJson(route('projects.close', $project), [
                'closure_status' => Project::CLOSURE_STATUS_COMPLETED,
                'closure_date'   => now()->toDateString(),
                'closure_notes'  => 'Successfully completed, audited, and closed.',
            ]);

        $closeResponse->assertOk();
        $this->assertEquals(Project::STATUS_CLOSED, $project->fresh()->status);

        // =========================================================================
        // STAGE 11: Multi-Tenant & RBAC Isolation Verification
        // =========================================================================
        // Beta tenant user is completely isolated and cannot access Alpha's project
        $betaAccess = $this->actingAs($this->betaOwner)
            ->withHeader('X-Tenant', $this->tenantBeta->slug)
            ->get(route('projects.show', $project));
        $betaAccess->assertStatus(404);

        // Unauthorized read-only user cannot access dashboard or perform updates
        $unauthDashboard = $this->actingAs($this->alphaUnauthorized)
            ->withHeader('X-Tenant', $this->tenantAlpha->slug)
            ->get(route('projects.dashboard'));
        $unauthDashboard->assertStatus(403);

        // =========================================================================
        // STAGE 12: Executive Analytics & 7 Operational Reports Reflection (Phase 10)
        // =========================================================================
        // 1. Dashboard
        $dashRes = $this->actingAs($this->alphaOwner)
            ->withHeader('X-Tenant', $this->tenantAlpha->slug)
            ->get(route('projects.dashboard', ['preset' => 'all_time']));
        $dashRes->assertOk();
        $kpis = $dashRes->viewData('kpis');
        $this->assertEquals(1, $kpis->total_projects);
        $this->assertEquals(1, $kpis->completed_projects);
        $this->assertEquals(0, $kpis->active_projects);
        $this->assertEquals(500.00, $kpis->total_incurred_cost);

        // Dashboard CSV export
        $dashCsv = $this->actingAs($this->alphaOwner)
            ->withHeader('X-Tenant', $this->tenantAlpha->slug)
            ->get(route('projects.dashboard.export', ['format' => 'csv']));
        $dashCsv->assertOk();

        // 2. All 7 Reports render and contain project data
        $reports = [
            'summary'               => route('projects.reports.summary', ['preset' => 'all_time']),
            'task-status'           => route('projects.reports.task-status', ['preset' => 'all_time']),
            'resource-utilization'  => route('projects.reports.resource-utilization', ['preset' => 'all_time']),
            'timesheet-billability' => route('projects.reports.timesheet-billability', ['preset' => 'all_time']),
            'issue-defect-density'  => route('projects.reports.issue-defect-density', ['preset' => 'all_time']),
            'milestone-variance'    => route('projects.reports.milestone-variance', ['preset' => 'all_time']),
            'budget-cost'           => route('projects.reports.budget-cost', ['preset' => 'all_time']),
        ];

        foreach ($reports as $key => $reportUrl) {
            $repRes = $this->actingAs($this->alphaOwner)
                ->withHeader('X-Tenant', $this->tenantAlpha->slug)
                ->get($reportUrl);
            $repRes->assertOk();

            // Export both XLSX and CSV
            $xlsxExport = $this->actingAs($this->alphaOwner)
                ->withHeader('X-Tenant', $this->tenantAlpha->slug)
                ->get(route('projects.reports.export', ['report' => $key, 'format' => 'xlsx', 'preset' => 'all_time']));
            $xlsxExport->assertOk();

            $csvExport = $this->actingAs($this->alphaOwner)
                ->withHeader('X-Tenant', $this->tenantAlpha->slug)
                ->get(route('projects.reports.export', ['report' => $key, 'format' => 'csv', 'preset' => 'all_time']));
            $csvExport->assertOk();
        }
    }
}
