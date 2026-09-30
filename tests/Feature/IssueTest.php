<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IssueTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;
    private User $developer;
    private User $qaTester;
    private User $externalUser;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Testing Workspace',
            'slug'   => 'testing-workspace',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Project Owner',
            'email'     => 'owner@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $this->tenantOwner->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->developer = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Developer Dev',
            'email'     => 'dev@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $this->developer->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->qaTester = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'QA Tester',
            'email'     => 'qa@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $this->qaTester->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->externalUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'External User',
            'email'     => 'external@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $this->externalUser->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->project = Project::create([
            'tenant_id'    => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name'         => 'Issue Tracking Project',
            'owner_id'     => $this->tenantOwner->id,
            'start_date'   => now(),
            'priority'     => 'High',
            'status'       => 'Active',
        ]);

        ProjectMember::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'user_id'      => $this->developer->id,
            'project_role' => 'Developer',
            'is_active'    => true,
        ]);

        ProjectMember::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'user_id'      => $this->qaTester->id,
            'project_role' => 'QA Specialist',
            'is_active'    => true,
        ]);

        $taskList = TaskList::create([
            'tenant_id'  => $this->tenant->id,
            'project_id' => $this->project->id,
            'name'       => 'Development Sprint',
            'position'   => 1,
        ]);

        $this->task = Task::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_list_id'    => $taskList->id,
            'task_code'       => 'PRJ-0001-T-001',
            'title'           => 'Checkout API',
            'status'          => Task::STATUS_IN_PROGRESS,
            'priority'        => 'High',
        ]);

        $this->withHeaders(['X-Tenant' => $this->tenant->slug]);
    }

    public function test_user_can_report_issue(): void
    {
        $response = $this->actingAs($this->qaTester)
            ->post(route('projects.issues.store', $this->project), [
                'title'               => 'Payment Gateway Timeout',
                'description'         => 'Gateway times out after 30 seconds on Razorpay callback.',
                'severity'            => Issue::SEVERITY_CRITICAL,
                'priority'            => Issue::PRIORITY_CRITICAL,
                'task_id'             => $this->task->id,
                'assignee_id'         => $this->developer->id,
                'steps_to_reproduce'  => '1. Add item to cart. 2. Checkout. 3. Emulate network delay.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('project_issues', [
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'task_id'      => $this->task->id,
            'issue_number' => 'PRJ-0001-ISS-001',
            'title'        => 'Payment Gateway Timeout',
            'severity'     => Issue::SEVERITY_CRITICAL,
            'priority'     => Issue::PRIORITY_CRITICAL,
            'status'       => Issue::STATUS_ASSIGNED,
            'assignee_id'  => $this->developer->id,
            'reporter_id'  => $this->qaTester->id,
        ]);
    }

    public function test_assignee_must_be_active_collaborator(): void
    {
        $response = $this->actingAs($this->qaTester)
            ->post(route('projects.issues.store', $this->project), [
                'title'       => 'UI Alignment Bug',
                'severity'    => Issue::SEVERITY_MINOR,
                'priority'    => Issue::PRIORITY_LOW,
                'assignee_id' => $this->externalUser->id,
            ]);

        $response->assertSessionHasErrors('assignee_id');
    }

    public function test_user_can_view_issue_workspace(): void
    {
        $issue = Issue::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'issue_number' => 'PRJ-0001-ISS-001',
            'title'        => 'Login CSRF Token Mismatch',
            'description'  => 'Occurs occasionally on page refresh.',
            'reporter_id'  => $this->qaTester->id,
            'assignee_id'  => $this->developer->id,
            'severity'     => Issue::SEVERITY_MAJOR,
            'priority'     => Issue::PRIORITY_HIGH,
            'status'       => Issue::STATUS_ASSIGNED,
        ]);

        $response = $this->actingAs($this->developer)
            ->get(route('projects.issues.show', [$this->project, $issue]));

        $response->assertOk();
        $response->assertSee('PRJ-0001-ISS-001');
        $response->assertSee('Login CSRF Token Mismatch');
    }

    public function test_resolving_issue_requires_resolution_notes(): void
    {
        $issue = Issue::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'issue_number' => 'PRJ-0001-ISS-001',
            'title'        => 'Bug to resolve',
            'reporter_id'  => $this->qaTester->id,
            'assignee_id'  => $this->developer->id,
            'severity'     => Issue::SEVERITY_MAJOR,
            'priority'     => Issue::PRIORITY_MEDIUM,
            'status'       => Issue::STATUS_IN_PROGRESS,
        ]);

        // Attempting to resolve without resolution notes should fail
        $response = $this->actingAs($this->developer)
            ->put(route('projects.issues.update', [$this->project, $issue]), [
                'status'           => Issue::STATUS_RESOLVED,
                'resolution_notes' => '',
            ]);

        $response->assertSessionHasErrors('resolution_notes');

        // Providing resolution notes succeeds
        $validResponse = $this->actingAs($this->developer)
            ->put(route('projects.issues.update', [$this->project, $issue]), [
                'status'           => Issue::STATUS_RESOLVED,
                'resolution_notes' => 'Fixed by adding CSRF token regeneration on refresh.',
            ]);

        $validResponse->assertRedirect();
        $this->assertEquals(Issue::STATUS_RESOLVED, $issue->fresh()->status);
        $this->assertNotNull($issue->fresh()->resolution_date);
    }

    public function test_qa_retest_pass_closes_issue(): void
    {
        $issue = Issue::create([
            'tenant_id'        => $this->tenant->id,
            'project_id'       => $this->project->id,
            'issue_number'     => 'PRJ-0001-ISS-001',
            'title'            => 'Bug awaiting retest',
            'reporter_id'      => $this->qaTester->id,
            'assignee_id'      => $this->developer->id,
            'severity'         => Issue::SEVERITY_MAJOR,
            'priority'         => Issue::PRIORITY_MEDIUM,
            'status'           => Issue::STATUS_RESOLVED,
            'resolution_date'  => now(),
            'resolution_notes' => 'Fixed in commit 1234abc',
        ]);

        $response = $this->actingAs($this->qaTester)
            ->post(route('projects.issues.retest', [$this->project, $issue]), [
                'passed'           => true,
                'resolution_notes' => 'Verified on build 1.4.2. Bug no longer reproduces.',
            ]);

        $response->assertRedirect();
        $this->assertEquals(Issue::STATUS_CLOSED, $issue->fresh()->status);
        $this->assertEquals('Verified on build 1.4.2. Bug no longer reproduces.', $issue->fresh()->retest_notes);
    }

    public function test_qa_retest_fail_reopens_issue(): void
    {
        $issue = Issue::create([
            'tenant_id'        => $this->tenant->id,
            'project_id'       => $this->project->id,
            'issue_number'     => 'PRJ-0001-ISS-001',
            'title'            => 'Bug with faulty fix',
            'reporter_id'      => $this->qaTester->id,
            'assignee_id'      => $this->developer->id,
            'severity'         => Issue::SEVERITY_MAJOR,
            'priority'         => Issue::PRIORITY_MEDIUM,
            'status'           => Issue::STATUS_RESOLVED,
            'resolution_date'  => now(),
            'resolution_notes' => 'Attempted fix in commit abc',
        ]);

        $response = $this->actingAs($this->qaTester)
            ->post(route('projects.issues.retest', [$this->project, $issue]), [
                'passed'           => false,
                'resolution_notes' => 'Failed on retry: edge case with special characters still causes 500.',
            ]);

        $response->assertRedirect();
        $this->assertEquals(Issue::STATUS_IN_PROGRESS, $issue->fresh()->status);
        $this->assertNull($issue->fresh()->resolution_date);
    }

    public function test_assignee_cannot_retest_own_resolved_issue(): void
    {
        $issue = Issue::create([
            'tenant_id'        => $this->tenant->id,
            'project_id'       => $this->project->id,
            'issue_number'     => 'PRJ-0001-ISS-001',
            'title'            => 'Self fix attempt',
            'reporter_id'      => $this->qaTester->id,
            'assignee_id'      => $this->developer->id,
            'severity'         => Issue::SEVERITY_MAJOR,
            'priority'         => Issue::PRIORITY_MEDIUM,
            'status'           => Issue::STATUS_RESOLVED,
            'resolution_date'  => now(),
            'resolution_notes' => 'Developer says it is fixed.',
        ]);

        // Non-manager assignee developer tries to retest and pass their own fix
        $response = $this->actingAs($this->developer)
            ->post(route('projects.issues.retest', [$this->project, $issue]), [
                'passed'           => true,
                'resolution_notes' => 'I verified it myself.',
            ]);

        $response->assertSessionHasErrors('retest');
        $this->assertEquals(Issue::STATUS_RESOLVED, $issue->fresh()->status);
    }

    public function test_inline_field_update_on_issue(): void
    {
        $issue = Issue::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'issue_number' => 'PRJ-0001-ISS-001',
            'title'        => 'Original Title',
            'reporter_id'  => $this->qaTester->id,
            'severity'     => Issue::SEVERITY_MINOR,
            'priority'     => Issue::PRIORITY_LOW,
            'status'       => Issue::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->patchJson(route('projects.issues.field', [$this->project, $issue]), [
                'field' => 'severity',
                'value' => Issue::SEVERITY_CRITICAL,
            ]);

        $response->assertOk();
        $this->assertEquals(Issue::SEVERITY_CRITICAL, $issue->fresh()->severity);
    }

    public function test_delete_issue(): void
    {
        $issue = Issue::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'issue_number' => 'PRJ-0001-ISS-001',
            'title'        => 'Temporary Bug',
            'reporter_id'  => $this->qaTester->id,
            'severity'     => Issue::SEVERITY_MINOR,
            'priority'     => Issue::PRIORITY_LOW,
            'status'       => Issue::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->delete(route('projects.issues.destroy', [$this->project, $issue]));

        $response->assertRedirect();
        $this->assertSoftDeleted('project_issues', ['id' => $issue->id]);
    }

    public function test_steps_to_reproduce_persistence(): void
    {
        $payload = [
            'title'              => 'Steps to reproduce test',
            'severity'           => Issue::SEVERITY_CRITICAL,
            'priority'           => Issue::PRIORITY_HIGH,
            'steps_to_reproduce' => "1. Go to page\n2. Click button\n3. Crash",
        ];

        $response = $this->actingAs($this->tenantOwner)
            ->post(route('projects.issues.store', $this->project), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_issues', [
            'project_id'         => $this->project->id,
            'title'              => 'Steps to reproduce test',
            'steps_to_reproduce' => "1. Go to page\n2. Click button\n3. Crash",
        ]);

        $issue = Issue::query()->where('title', 'Steps to reproduce test')->firstOrFail();
        $this->assertEquals("1. Go to page\n2. Click button\n3. Crash", $issue->steps_to_reproduce);

        $this->actingAs($this->tenantOwner)
            ->put(route('projects.issues.update', [$this->project, $issue]), [
                'steps_to_reproduce' => 'Updated steps to reproduce',
            ]);

        $this->assertEquals('Updated steps to reproduce', $issue->fresh()->steps_to_reproduce);
    }

    public function test_approved_severity_values_title_case(): void
    {
        foreach ([Issue::SEVERITY_MINOR, Issue::SEVERITY_MAJOR, Issue::SEVERITY_CRITICAL] as $validSev) {
            $resp = $this->actingAs($this->tenantOwner)
                ->post(route('projects.issues.store', $this->project), [
                    'title'    => "Issue for {$validSev}",
                    'severity' => $validSev,
                    'priority' => Issue::PRIORITY_MEDIUM,
                ]);
            $resp->assertSessionDoesntHaveErrors('severity');
        }

        foreach (['Moderate', 'moderate', 'minor', 'critical', 'Extreme'] as $invalidSev) {
            $resp = $this->actingAs($this->tenantOwner)
                ->post(route('projects.issues.store', $this->project), [
                    'title'    => "Issue for {$invalidSev}",
                    'severity' => $invalidSev,
                    'priority' => Issue::PRIORITY_MEDIUM,
                ]);
            $resp->assertSessionHasErrors('severity');
        }
    }

    public function test_granular_resolve_permission(): void
    {
        $issue = Issue::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'issue_number' => 'PRJ-0001-ISS-098',
            'title'        => 'Permission Issue',
            'reporter_id'  => $this->qaTester->id,
            'severity'     => Issue::SEVERITY_MAJOR,
            'priority'     => Issue::PRIORITY_MEDIUM,
            'status'       => Issue::STATUS_OPEN,
        ]);

        $readOnlyRole = Role::query()->whereNull('tenant_id')->where('slug', 'read_only')->firstOrFail();
        $restrictedUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Restricted Tester',
            'email'     => 'restricted@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $restrictedUser->id,
            'role_id'   => $readOnlyRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($restrictedUser)
            ->put(route('projects.issues.update', [$this->project, $issue]), [
                'status'           => Issue::STATUS_RESOLVED,
                'resolution_notes' => 'Unauthorized resolution attempt',
            ]);
        $response->assertForbidden();

        $resolveRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Resolver Role',
            'slug'      => 'resolver_role',
        ]);
        $resolvePerm = Permission::query()->where('name', 'projects.issues.resolve')->firstOrFail();
        RolePermission::create([
            'role_id'       => $resolveRole->id,
            'permission_id' => $resolvePerm->id,
            'scope'         => RolePermission::SCOPE_TENANT,
        ]);
        UserRole::create([
            'user_id'   => $restrictedUser->id,
            'role_id'   => $resolveRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $successResponse = $this->actingAs($restrictedUser)
            ->put(route('projects.issues.update', [$this->project, $issue]), [
                'status'           => Issue::STATUS_RESOLVED,
                'resolution_notes' => 'Authorized resolution',
            ]);
        $successResponse->assertRedirect();
        $this->assertEquals(Issue::STATUS_RESOLVED, $issue->fresh()->status);
    }

    public function test_granular_retest_permission(): void
    {
        $issue = Issue::create([
            'tenant_id'        => $this->tenant->id,
            'project_id'       => $this->project->id,
            'issue_number'     => 'PRJ-0001-ISS-099',
            'title'            => 'Retest Permission Issue',
            'reporter_id'      => $this->qaTester->id,
            'severity'         => Issue::SEVERITY_MAJOR,
            'priority'         => Issue::PRIORITY_MEDIUM,
            'status'           => Issue::STATUS_RESOLVED,
            'resolution_date'  => now(),
            'resolution_notes' => 'Fixed ready for test',
        ]);

        $readOnlyRole = Role::query()->whereNull('tenant_id')->where('slug', 'read_only')->firstOrFail();
        $restrictedUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'QA Restricted',
            'email'     => 'qarestricted@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $restrictedUser->id,
            'role_id'   => $readOnlyRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($restrictedUser)
            ->post(route('projects.issues.retest', [$this->project, $issue]), [
                'passed'           => true,
                'resolution_notes' => 'Unauthorized retest',
            ]);
        $response->assertForbidden();

        $retestRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'QA Tester Role',
            'slug'      => 'qa_tester_role',
        ]);
        $retestPerm = Permission::query()->where('name', 'projects.issues.retest')->firstOrFail();
        RolePermission::create([
            'role_id'       => $retestRole->id,
            'permission_id' => $retestPerm->id,
            'scope'         => RolePermission::SCOPE_TENANT,
        ]);
        UserRole::create([
            'user_id'   => $restrictedUser->id,
            'role_id'   => $retestRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $successResponse = $this->actingAs($restrictedUser)
            ->post(route('projects.issues.retest', [$this->project, $issue]), [
                'passed'           => true,
                'resolution_notes' => 'Verified passed',
            ]);
        $successResponse->assertRedirect();
        $this->assertEquals(Issue::STATUS_CLOSED, $issue->fresh()->status);
    }

    public function test_activity_event_naming(): void
    {
        $this->actingAs($this->tenantOwner)
            ->post(route('projects.issues.store', $this->project), [
                'title'    => 'Event naming issue',
                'severity' => Issue::SEVERITY_MAJOR,
                'priority' => Issue::PRIORITY_MEDIUM,
            ]);
        $issue = Issue::query()->where('title', 'Event naming issue')->firstOrFail();

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.issue_created',
        ]);

        $this->actingAs($this->tenantOwner)
            ->put(route('projects.issues.update', [$this->project, $issue]), [
                'status'           => Issue::STATUS_RESOLVED,
                'resolution_notes' => 'Resolved with fix',
            ]);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.issue_resolved',
        ]);

        $this->actingAs($this->tenantOwner)
            ->post(route('projects.issues.retest', [$this->project, $issue]), [
                'passed'           => true,
                'resolution_notes' => 'Retest verified',
            ]);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.issue_retested',
        ]);

        $this->actingAs($this->tenantOwner)
            ->delete(route('projects.issues.destroy', [$this->project, $issue]));

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.issue_deleted',
        ]);
    }
}
