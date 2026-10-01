<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectReview;
use App\Domains\Projects\Models\SubTask;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Projects\Services\ProjectService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectClosureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;
    private User $unauthorizedUser;
    private Customer $customer;
    private Project $project;
    private TaskList $taskList;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Closure Workspace',
            'slug'   => 'closure-workspace',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->withHeader('X-Tenant', $this->tenant->slug);
        session(['tenant_id' => $this->tenant->id]);

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
            'name'          => 'Client Alpha Corp',
            'email'         => 'alpha@example.com',
            'billing_state' => 'Maharashtra',
        ]);

        $this->project = Project::create([
            'tenant_id'      => $this->tenant->id,
            'customer_id'    => $this->customer->id,
            'owner_id'       => $this->tenantOwner->id,
            'project_code'   => 'PRJ-CLOSE-001',
            'name'           => 'ERP Controlled Closure Project',
            'status'         => Project::STATUS_ACTIVE,
            'start_date'     => '2026-10-01',
            'end_date'       => '2026-12-31',
            'budget_type'    => 'Fixed',
            'billing_method' => 'Project Based',
        ]);

        $this->taskList = TaskList::create([
            'tenant_id'  => $this->tenant->id,
            'project_id' => $this->project->id,
            'name'       => 'Default Phase',
            'sort_order' => 1,
        ]);
    }

    public function test_closure_fails_when_user_lacks_permission(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.close', $this->project), [
                'closure_status' => Project::CLOSURE_STATUS_COMPLETED,
                'closure_date'   => now()->toDateString(),
            ]);

        $response->assertStatus(403);
    }

    public function test_gate_1_blocks_when_tasks_open(): void
    {
        $task = Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'TSK-001',
            'title'        => 'Open Deliverable Task',
            'status'       => Task::STATUS_IN_PROGRESS,
            'sort_order'   => 1,
        ]);

        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertFalse($checkRes->json('can_close'));
        $this->assertFalse($checkRes->json('gates.tasks.passed'));

        $closeRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('projects.close', $this->project), [
                'closure_status' => Project::CLOSURE_STATUS_COMPLETED,
                'closure_date'   => now()->toDateString(),
            ]);

        $closeRes->assertStatus(422);
    }

    public function test_gate_1_blocks_when_subtasks_open(): void
    {
        $task = Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'TSK-002',
            'title'        => 'Completed Task With Open Subtask',
            'status'       => Task::STATUS_COMPLETED,
            'sort_order'   => 1,
        ]);

        SubTask::create([
            'tenant_id'    => $this->tenant->id,
            'task_id'      => $task->id,
            'title'        => 'Unfinished Subtask',
            'is_completed' => false,
            'sort_order'   => 1,
        ]);

        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertFalse($checkRes->json('gates.tasks.passed'));
        $this->assertFalse($checkRes->json('can_close'));
    }

    public function test_gate_2_blocks_when_issues_open(): void
    {
        $issue = Issue::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'issue_number' => 'ISS-001',
            'title'        => 'Production Blocker Issue',
            'type'         => 'Bug',
            'severity'     => 'Critical',
            'status'       => Issue::STATUS_OPEN,
            'reporter_id'  => $this->tenantOwner->id,
        ]);

        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertFalse($checkRes->json('gates.issues.passed'));

        // Resolve the issue
        $issue->update(['status' => Issue::STATUS_RESOLVED]);

        $checkResAfter = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $this->assertTrue($checkResAfter->json('gates.issues.passed'));
    }

    public function test_gate_3_blocks_when_reviews_pending(): void
    {
        ProjectReview::create([
            'tenant_id'     => $this->tenant->id,
            'project_id'    => $this->project->id,
            'reviewer_name' => 'Internal Reviewer',
            'review_date'   => now()->toDateString(),
            'status'        => ProjectReview::STATUS_PENDING,
            'created_by'    => $this->tenantOwner->id,
        ]);

        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertFalse($checkRes->json('gates.reviews.passed'));
    }

    public function test_gate_3_blocks_when_milestone_exists_without_approved_uat(): void
    {
        // Project has a milestone
        Milestone::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'name'        => 'Milestone 1',
            'status'      => Milestone::STATUS_COMPLETED,
            'is_invoiced' => true,
        ]);

        // No review exists yet
        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertFalse($checkRes->json('gates.reviews.passed'));
    }

    public function test_gate_3_passes_with_historical_rework_once_approved_review_exists(): void
    {
        // Milestone exists
        Milestone::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'name'        => 'Milestone 1',
            'status'      => Milestone::STATUS_COMPLETED,
            'is_invoiced' => true,
        ]);

        // Historical review was Rework Required
        ProjectReview::create([
            'tenant_id'     => $this->tenant->id,
            'project_id'    => $this->project->id,
            'reviewer_name' => 'Client Signoff Person',
            'review_date'   => now()->subDays(5)->toDateString(),
            'status'        => ProjectReview::STATUS_REWORK_REQUIRED,
            'created_by'    => $this->tenantOwner->id,
            'reviewer_id'   => $this->tenantOwner->id,
        ]);

        // Second review was Approved
        ProjectReview::create([
            'tenant_id'     => $this->tenant->id,
            'project_id'    => $this->project->id,
            'reviewer_name' => 'Client Signoff Person',
            'review_date'   => now()->toDateString(),
            'status'        => ProjectReview::STATUS_APPROVED,
            'created_by'    => $this->tenantOwner->id,
            'reviewer_id'   => $this->tenantOwner->id,
        ]);

        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertTrue($checkRes->json('gates.reviews.passed'));
    }

    public function test_gate_4_blocks_when_billable_time_logs_uninvoiced(): void
    {
        $task = Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'TSK-003',
            'title'        => 'Task 1',
            'status'       => Task::STATUS_COMPLETED,
            'sort_order'   => 1,
        ]);

        TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => now()->toDateString(),
            'hours'           => 5.0,
            'is_billable'     => true,
            'approval_status' => TimeLog::STATUS_APPROVED,
            'is_invoiced'     => false,
        ]);

        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertFalse($checkRes->json('gates.billing.passed'));
    }

    public function test_gate_4_blocks_when_time_logs_pending_approval(): void
    {
        $task = Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'TSK-004',
            'title'        => 'Task 1',
            'status'       => Task::STATUS_COMPLETED,
            'sort_order'   => 1,
        ]);

        TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => now()->toDateString(),
            'hours'           => 2.5,
            'is_billable'     => false,
            'approval_status' => TimeLog::STATUS_PENDING,
            'is_invoiced'     => false,
        ]);

        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertFalse($checkRes->json('gates.billing.passed'));
    }

    public function test_gate_4_passes_with_rejected_or_non_billable_approved_time_logs(): void
    {
        $task = Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'TSK-005',
            'title'        => 'Task 1',
            'status'       => Task::STATUS_COMPLETED,
            'sort_order'   => 1,
        ]);

        // Approved non-billable time log
        TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => now()->toDateString(),
            'hours'           => 2.0,
            'is_billable'     => false,
            'approval_status' => TimeLog::STATUS_APPROVED,
            'is_invoiced'     => false,
        ]);

        // Rejected billable time log
        TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => now()->toDateString(),
            'hours'           => 4.0,
            'is_billable'     => true,
            'approval_status' => TimeLog::STATUS_REJECTED,
            'is_invoiced'     => false,
        ]);

        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertTrue($checkRes->json('gates.billing.passed'));
    }

    public function test_gate_5_blocks_when_milestones_open_or_uninvoiced(): void
    {
        // Incomplete milestone
        $milestone = Milestone::create([
            'tenant_id'      => $this->tenant->id,
            'project_id'     => $this->project->id,
            'name'           => 'Sprint 1 Delivery',
            'status'         => Milestone::STATUS_ACTIVE,
            'billing_amount' => 5000.00,
            'is_invoiced'    => false,
        ]);

        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertFalse($checkRes->json('gates.milestones.passed'));
        $this->assertFalse($checkRes->json('can_close'));

        // Completed but unbilled
        $milestone->update(['status' => Milestone::STATUS_COMPLETED]);

        $checkRes2 = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $this->assertTrue($checkRes2->json('gates.milestones.passed'));
        $this->assertFalse($checkRes2->json('gates.billing.passed'));
        $this->assertFalse($checkRes2->json('can_close'));

        // Approved review to satisfy Gate 3 for milestone project
        ProjectReview::create([
            'tenant_id'     => $this->tenant->id,
            'project_id'    => $this->project->id,
            'reviewer_name' => 'Client Signoff Person',
            'review_date'   => now()->toDateString(),
            'status'        => ProjectReview::STATUS_APPROVED,
            'created_by'    => $this->tenantOwner->id,
        ]);

        // Invoiced
        $milestone->update(['is_invoiced' => true]);

        $checkRes3 = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $this->assertTrue($checkRes3->json('gates.milestones.passed'));
        $this->assertTrue($checkRes3->json('gates.billing.passed'));
        $this->assertTrue($checkRes3->json('can_close'));
    }

    public function test_closure_succeeds_when_all_gates_pass(): void
    {
        // 1. Task completed
        $task = Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'TSK-006',
            'title'        => 'Implementation Task',
            'status'       => Task::STATUS_COMPLETED,
            'sort_order'   => 1,
        ]);

        SubTask::create([
            'tenant_id'    => $this->tenant->id,
            'task_id'      => $task->id,
            'title'        => 'Subtask 1',
            'is_completed' => true,
            'sort_order'   => 1,
        ]);

        // 2. Resolved issue
        Issue::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'issue_number' => 'ISS-002',
            'title'        => 'QA Issue',
            'type'         => 'Bug',
            'severity'     => 'Minor',
            'status'       => Issue::STATUS_CLOSED,
            'reporter_id'  => $this->tenantOwner->id,
        ]);

        // 3. Approved UAT review
        ProjectReview::create([
            'tenant_id'     => $this->tenant->id,
            'project_id'    => $this->project->id,
            'reviewer_name' => 'Client Signoff Person',
            'review_date'   => now()->toDateString(),
            'status'        => ProjectReview::STATUS_APPROVED,
            'created_by'    => $this->tenantOwner->id,
            'reviewer_id'   => $this->tenantOwner->id,
        ]);

        // 4. Milestone completed and invoiced
        Milestone::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'name'        => 'Delivery Milestone',
            'status'      => Milestone::STATUS_COMPLETED,
            'is_invoiced' => true,
        ]);

        // 5. Invoiced billable time log
        TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $task->id,
            'user_id'         => $this->tenantOwner->id,
            'log_date'        => now()->toDateString(),
            'hours'           => 10.0,
            'is_billable'     => true,
            'approval_status' => TimeLog::STATUS_APPROVED,
            'is_invoiced'     => true,
        ]);

        $checkRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->getJson(route('projects.closure.check', $this->project));

        $checkRes->assertOk();
        $this->assertTrue($checkRes->json('can_close'));
        $this->assertTrue($checkRes->json('gates.tasks.passed'));
        $this->assertTrue($checkRes->json('gates.issues.passed'));
        $this->assertTrue($checkRes->json('gates.reviews.passed'));
        $this->assertTrue($checkRes->json('gates.billing.passed'));
        $this->assertTrue($checkRes->json('gates.milestones.passed'));

        // Execute closure
        $closeRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->postJson(route('projects.close', $this->project), [
                'closure_status'      => Project::CLOSURE_STATUS_COMPLETED,
                'closure_date'        => now()->toDateString(),
                'client_approval_ref' => 'REF-CLIENT-ACCEPT-99',
                'final_remarks'       => 'Delivered on time and within scope.',
            ]);

        $closeRes->assertOk();
        $closeRes->assertJson(['success' => true]);

        $this->project->refresh();
        $this->assertEquals(Project::STATUS_CLOSED, $this->project->status);
        $this->assertEquals(Project::CLOSURE_STATUS_COMPLETED, $this->project->closure_status);
        $this->assertEquals('REF-CLIENT-ACCEPT-99', $this->project->client_approval_ref);
        $this->assertEquals('Delivered on time and within scope.', $this->project->final_remarks);
        $this->assertEquals($this->tenantOwner->id, $this->project->closed_by);
        $this->assertTrue($this->project->isClosed());
    }

    public function test_closed_project_enforces_read_only_immutability_on_child_domains(): void
    {
        // Force closed state
        $this->project->update([
            'status'         => Project::STATUS_CLOSED,
            'closure_status' => Project::CLOSURE_STATUS_COMPLETED,
            'closure_date'   => now()->toDateString(),
            'closed_by'      => $this->tenantOwner->id,
        ]);

        $this->assertTrue($this->project->isClosed());

        // 1. Task creation is blocked
        $taskStoreRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.tasks.store', $this->project), [
                'title'        => 'Late Task',
                'task_list_id' => $this->taskList->id,
            ]);
        $taskStoreRes->assertStatus(403);

        // 2. Existing task update/deletion is blocked
        $task = Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'TSK-007',
            'title'        => 'Existing Task',
            'status'       => Task::STATUS_COMPLETED,
            'sort_order'   => 1,
        ]);

        $taskUpdateRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'title' => 'Updated Title',
            ]);
        $taskUpdateRes->assertStatus(403);

        $taskDeleteRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->delete(route('projects.tasks.destroy', [$this->project, $task]));
        $taskDeleteRes->assertStatus(403);

        // 3. Time log creation is blocked
        $timeLogStoreRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.tasks.timelogs.store', [$this->project, $task]), [
                'log_date' => now()->toDateString(),
                'hours'    => 2.0,
            ]);
        $timeLogStoreRes->assertStatus(403);

        // 4. Milestone creation is blocked
        $milestoneStoreRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.milestones.store', $this->project), [
                'name' => 'Late Milestone',
            ]);
        $milestoneStoreRes->assertStatus(403);

        // 5. Issue creation is blocked
        $issueStoreRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.issues.store', $this->project), [
                'title'    => 'Late Issue',
                'severity' => 'Minor',
                'priority' => 'Low',
            ]);
        $issueStoreRes->assertStatus(403);

        // 6. Project review creation is blocked
        $reviewStoreRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.reviews.store', $this->project), [
                'review_date'   => now()->toDateString(),
                'reviewer_name' => 'Late Reviewer',
            ]);
        $reviewStoreRes->assertStatus(403);

        // 7. Change request creation is blocked
        $crStoreRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(route('projects.change-requests.store', $this->project), [
                'title'       => 'Late CR',
                'description' => 'Some change',
            ]);
        $crStoreRes->assertStatus(403);

        // 8. Project deletion is blocked
        $projectDeleteRes = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->delete(route('projects.destroy', $this->project));
        $projectDeleteRes->assertStatus(403);
    }

    public function test_state_machine_transition_to_closed_enforces_gates(): void
    {
        $this->project->update(['status' => Project::STATUS_COMPLETED]);

        // Open task exists
        Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'TSK-008',
            'title'        => 'Unfinished Work',
            'status'       => Task::STATUS_OPEN,
            'sort_order'   => 1,
        ]);

        $projectService = app(ProjectService::class);

        $caughtException = false;
        try {
            $projectService->changeStatus($this->project, Project::STATUS_CLOSED);
        } catch (ValidationException $e) {
            $caughtException = true;
            $this->assertArrayHasKey('closure', $e->errors());
            $this->assertStringContainsString('tasks', strtolower($e->errors()['closure'][0]));
        }
        $this->assertTrue($caughtException, 'ProjectService::changeStatus() did not throw ValidationException for closure gates.');
    }

    public function test_full_form_update_cannot_bypass_closure_gates(): void
    {
        $this->project->update([
            'status'   => Project::STATUS_COMPLETED,
            'priority' => Project::PRIORITY_HIGH,
        ]);
        app(\App\Domains\Projects\Services\ProjectMemberService::class)->ensureCollaborator($this->project, $this->tenantOwner->id);

        Task::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code'    => 'TSK-009',
            'title'        => 'Unfinished Work Blocking Full Update',
            'status'       => Task::STATUS_OPEN,
            'sort_order'   => 1,
        ]);

        // 1. Direct ProjectService::update() throws ValidationException for closure gates
        $projectService = app(ProjectService::class);
        $caughtException = false;
        try {
            $projectService->update($this->project, [
                'name'        => $this->project->name,
                'status'      => Project::STATUS_CLOSED,
                'priority'    => Project::PRIORITY_HIGH,
                'owner_id'    => $this->tenantOwner->id,
                'start_date'  => $this->project->start_date->toDateString(),
            ]);
        } catch (ValidationException $e) {
            $caughtException = true;
            $this->assertArrayHasKey('closure', $e->errors());
            $this->assertStringContainsString('tasks', strtolower($e->errors()['closure'][0]));
        }
        $this->assertTrue($caughtException, 'ProjectService::update() did not throw ValidationException when closure gates failed.');

        // 2. HTTP PUT /projects/{project} cannot bypass closure gates
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->put(route('projects.update', $this->project), [
                'name'        => $this->project->name,
                'status'      => Project::STATUS_CLOSED,
                'priority'    => Project::PRIORITY_HIGH,
                'owner_id'    => $this->tenantOwner->id,
                'start_date'  => $this->project->start_date->toDateString(),
            ]);

        $response->assertSessionHasErrors('closure');
        $this->assertEquals(Project::STATUS_COMPLETED, $this->project->fresh()->status);
    }

    public function test_phase_8_localization_parity_across_languages(): void
    {
        $keys = [
            'close_project',
            'project_closure',
            'close_project_description',
            'closure_status',
            'closure_status_completed',
            'closure_status_terminated',
            'closure_status_handed_over',
            'closure_date',
            'client_approval_ref',
            'final_remarks',
            'confirm_close_project',
            'closure_checklist',
            'gate_tasks',
            'gate_issues',
            'gate_reviews',
            'gate_billing',
            'gate_milestones',
            'gates_passed',
            'gates_blocked',
            'project_is_closed',
            'project_is_closed_description',
            'closure_success_message',
            'closed_by',
            'closed_on',
            'checking_gates',
            'gate_passed',
            'gate_blocked_label',
            'closure_blocked_by_open_tasks',
            'closure_blocked_by_open_issues',
            'closure_blocked_by_pending_reviews',
            'closure_blocked_by_pending_change_requests',
            'closure_blocked_by_missing_uat',
            'closure_blocked_by_unbilled_timelogs',
            'closure_blocked_by_pending_timelogs',
            'closure_blocked_by_unbilled_milestones',
            'closure_blocked_by_open_milestones',
            'project_already_closed',
        ];

        foreach (['en', 'hi', 'bg'] as $locale) {
            foreach ($keys as $key) {
                $translation = __("projects.{$key}", ['count' => 5, 'hours' => 10], $locale);
                $this->assertNotEmpty($translation, "Missing translation for projects.{$key} in {$locale}");
                $this->assertNotEquals("projects.{$key}", $translation, "Untranslated key projects.{$key} in {$locale}");
            }
        }
    }
}
