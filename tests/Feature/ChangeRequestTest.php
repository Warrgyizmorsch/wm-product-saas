<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\ChangeRequest;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\ProjectReview;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $tenant2;
    private User $tenantOwner;
    private User $projectManager;
    private User $teamMember;
    private User $otherTenantUser;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Primary CR Workspace',
            'slug'   => 'cr-workspace',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->tenant2 = Tenant::create([
            'name'   => 'Secondary CR Workspace',
            'slug'   => 'cr-workspace-2',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        $readOnlyRole = Role::query()->whereNull('tenant_id')->where('slug', 'read_only')->firstOrFail();

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Owner User',
            'email'     => 'owner@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->tenantOwner->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant->id]);

        $this->projectManager = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'PM User',
            'email'     => 'pm@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->projectManager->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant->id]);

        $this->teamMember = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Dev Member',
            'email'     => 'member@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->teamMember->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant->id]);

        $this->otherTenantUser = User::create([
            'tenant_id' => $this->tenant2->id,
            'name'      => 'Other Tenant User',
            'email'     => 'other@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->otherTenantUser->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant2->id]);

        $this->project = Project::create([
            'tenant_id'    => $this->tenant->id,
            'project_code' => 'PRJ-CR-01',
            'name'         => 'Change Request Project',
            'owner_id'     => $this->tenantOwner->id,
            'manager_id'   => $this->projectManager->id,
            'start_date'   => now(),
            'priority'     => 'High',
            'status'       => 'Active',
            'budget_amount'=> 50000.00,
            'budget_hours' => 500.00,
        ]);

        ProjectMember::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'user_id'      => $this->projectManager->id,
            'project_role' => 'Manager',
            'is_active'    => true,
        ]);

        ProjectMember::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'user_id'      => $this->teamMember->id,
            'project_role' => 'Developer',
            'is_active'    => true,
        ]);
    }

    /** @test */
    public function cr_numbering_increments_sequentially_per_project(): void
    {
        $response1 = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.store', $this->project), [
                'title'                => 'Scope Change 1',
                'description'          => 'First addition',
                'requested_by'         => $this->teamMember->id,
                'impact_schedule_days' => 5,
                'impact_budget_amount' => 5000.00,
                'impact_budget_hours'  => 40.00,
            ]);

        $response1->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));
        $this->assertDatabaseHas('project_change_requests', [
            'project_id' => $this->project->id,
            'cr_number'  => 'PRJ-CR-01-CR-001',
        ]);

        $response2 = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.store', $this->project), [
                'title'                => 'Scope Change 2',
                'description'          => 'Second addition',
                'requested_by'         => $this->teamMember->id,
                'impact_schedule_days' => 2,
                'impact_budget_amount' => 2000.00,
                'impact_budget_hours'  => 16.00,
            ]);

        $response2->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));
        $this->assertDatabaseHas('project_change_requests', [
            'project_id' => $this->project->id,
            'cr_number'  => 'PRJ-CR-01-CR-002',
        ]);
    }

    /** @test */
    public function standalone_cr_creation_succeeds_with_null_project_review_id(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.store', $this->project), [
                'title'                => 'Standalone Client Change',
                'description'          => 'Client asked for extra report generation module',
                'requested_by'         => $this->teamMember->id,
                'project_review_id'    => null,
                'impact_schedule_days' => 7,
                'impact_budget_amount' => 8000.00,
                'impact_budget_hours'  => 60.00,
            ]);

        $response->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));

        $cr = ChangeRequest::first();
        $this->assertNull($cr->project_review_id);
        $this->assertEquals(ChangeRequest::STATUS_PENDING, $cr->status);
        $this->assertEquals(8000.00, $cr->impact_budget_amount);
    }

    /** @test */
    public function uat_originated_cr_requires_rework_required_review(): void
    {
        $review = ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'review_date' => now()->toDateString(),
            'status'      => ProjectReview::STATUS_REWORK_REQUIRED,
            'comments'    => 'UAT failed for data export',
            'created_by'  => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.store', $this->project), [
                'title'                => 'Fix Data Export UAT',
                'description'          => 'Refactor export memory consumption',
                'requested_by'         => $this->teamMember->id,
                'project_review_id'    => $review->id,
                'impact_schedule_days' => 3,
                'impact_budget_amount' => 3000.00,
                'impact_budget_hours'  => 24.00,
            ]);

        $response->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));

        $cr = ChangeRequest::first();
        $this->assertEquals($review->id, $cr->project_review_id);
        $this->assertEquals(ChangeRequest::STATUS_PENDING, $cr->status);
    }

    /** @test */
    public function cr_cannot_link_to_review_from_another_project(): void
    {
        $otherProject = Project::create([
            'tenant_id'    => $this->tenant->id,
            'project_code' => 'PRJ-CR-02',
            'name'         => 'Other Project',
            'owner_id'     => $this->tenantOwner->id,
            'start_date'   => now(),
            'priority'     => 'High',
            'status'       => 'Active',
        ]);

        $otherReview = ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $otherProject->id,
            'review_date' => now()->toDateString(),
            'status'      => ProjectReview::STATUS_REWORK_REQUIRED,
            'created_by'  => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.store', $this->project), [
                'title'             => 'Cross Project CR Attempt',
                'description'       => 'Should fail',
                'requested_by'      => $this->teamMember->id,
                'project_review_id' => $otherReview->id,
            ]);

        $response->assertSessionHasErrors('project_review_id');
        $this->assertDatabaseCount('project_change_requests', 0);
    }

    /** @test */
    public function cr_cannot_link_to_non_rework_required_review(): void
    {
        $approvedReview = ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'review_date' => now()->toDateString(),
            'status'      => ProjectReview::STATUS_APPROVED,
            'created_by'  => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.store', $this->project), [
                'title'             => 'CR on Approved Review Attempt',
                'description'       => 'Should fail',
                'requested_by'      => $this->teamMember->id,
                'project_review_id' => $approvedReview->id,
            ]);

        $response->assertSessionHasErrors('project_review_id');
        $this->assertDatabaseCount('project_change_requests', 0);
    }

    /** @test */
    public function duplicate_active_cr_for_same_rework_review_is_rejected(): void
    {
        $review = ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'review_date' => now()->toDateString(),
            'status'      => ProjectReview::STATUS_REWORK_REQUIRED,
            'created_by'  => $this->tenantOwner->id,
        ]);

        // First CR linked to rework review
        ChangeRequest::create([
            'tenant_id'            => $this->tenant->id,
            'project_id'           => $this->project->id,
            'project_review_id'    => $review->id,
            'cr_number'            => 'PRJ-CR-01-CR-001',
            'title'                => 'CR 1 for Review',
            'description'          => 'Fix item 1',
            'requested_by'         => $this->teamMember->id,
            'impact_schedule_days' => 2,
            'impact_budget_amount' => 1000.00,
            'impact_budget_hours'  => 10.00,
            'status'               => ChangeRequest::STATUS_PENDING,
            'created_by'           => $this->teamMember->id,
        ]);

        // Second CR attempt linked to same rework review
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.store', $this->project), [
                'title'             => 'CR 2 for Same Review',
                'description'       => 'Duplicate active link',
                'requested_by'      => $this->teamMember->id,
                'project_review_id' => $review->id,
            ]);

        $response->assertSessionHasErrors('project_review_id');
        $this->assertDatabaseCount('project_change_requests', 1);
    }

    /** @test */
    public function separation_of_duties_prevents_non_lead_requester_from_approving_own_cr(): void
    {
        $cr = ChangeRequest::create([
            'tenant_id'            => $this->tenant->id,
            'project_id'           => $this->project->id,
            'cr_number'            => 'PRJ-CR-01-CR-001',
            'title'                => 'Dev Self Approval Attempt',
            'description'          => 'Should be blocked',
            'requested_by'         => $this->teamMember->id,
            'impact_schedule_days' => 3,
            'impact_budget_amount' => 2000.00,
            'impact_budget_hours'  => 15.00,
            'status'               => ChangeRequest::STATUS_PENDING,
            'created_by'           => $this->teamMember->id,
        ]);

        // teamMember attempts to approve own CR
        $response = $this->actingAs($this->teamMember)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.approve', [$this->project, $cr]));

        $response->assertSessionHasErrors('approved_by');
        $cr->refresh();
        $this->assertEquals(ChangeRequest::STATUS_PENDING, $cr->status);
    }

    /** @test */
    public function authorized_project_manager_can_approve_cr_with_budget_rollup_and_audit(): void
    {
        $initialBudgetAmount = (float) $this->project->budget_amount;
        $initialBudgetHours = (float) $this->project->budget_hours;

        $cr = ChangeRequest::create([
            'tenant_id'            => $this->tenant->id,
            'project_id'           => $this->project->id,
            'cr_number'            => 'PRJ-CR-01-CR-001',
            'title'                => 'Scope Expansion Approved',
            'description'          => 'Add reporting module',
            'requested_by'         => $this->teamMember->id,
            'impact_schedule_days' => 5,
            'impact_budget_amount' => 12500.50,
            'impact_budget_hours'  => 75.50,
            'status'               => ChangeRequest::STATUS_PENDING,
            'created_by'           => $this->teamMember->id,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.approve', [$this->project, $cr]));

        $response->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));

        $cr->refresh();
        $this->assertEquals(ChangeRequest::STATUS_APPROVED, $cr->status);
        $this->assertEquals($this->projectManager->id, $cr->approved_by);
        $this->assertNotNull($cr->approved_at);

        // Verify project budget increments
        $this->project->refresh();
        $this->assertEquals($initialBudgetAmount + 12500.50, (float) $this->project->budget_amount);
        $this->assertEquals($initialBudgetHours + 75.50, (float) $this->project->budget_hours);

        // Verify activity log with pre/post budget metadata
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.change_request_approved',
        ]);

        $log = \App\Domains\Projects\Models\ActivityLog::where('project_id', $this->project->id)
            ->where('event_type', 'project.change_request_approved')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($initialBudgetAmount, (float) $log->metadata['previous_budget_amount']);
        $this->assertEquals($initialBudgetAmount + 12500.50, (float) $log->metadata['new_budget_amount']);
        $this->assertEquals($initialBudgetHours, (float) $log->metadata['previous_budget_hours']);
        $this->assertEquals($initialBudgetHours + 75.50, (float) $log->metadata['new_budget_hours']);
    }

    /** @test */
    public function already_approved_cr_cannot_be_approved_again(): void
    {
        $cr = ChangeRequest::create([
            'tenant_id'            => $this->tenant->id,
            'project_id'           => $this->project->id,
            'cr_number'            => 'PRJ-CR-01-CR-001',
            'title'                => 'Already Approved',
            'description'          => 'Test idempotency',
            'requested_by'         => $this->teamMember->id,
            'impact_schedule_days' => 2,
            'impact_budget_amount' => 1000.00,
            'impact_budget_hours'  => 10.00,
            'status'               => ChangeRequest::STATUS_APPROVED,
            'approved_by'          => $this->projectManager->id,
            'approved_at'          => now(),
            'created_by'           => $this->teamMember->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.approve', [$this->project, $cr]));

        $response->assertSessionHasErrors('status');
    }

    /** @test */
    public function pending_cr_can_be_rejected_with_mandatory_remarks(): void
    {
        $cr = ChangeRequest::create([
            'tenant_id'            => $this->tenant->id,
            'project_id'           => $this->project->id,
            'cr_number'            => 'PRJ-CR-01-CR-001',
            'title'                => 'Unnecessary Scope',
            'description'          => 'Feature out of budget',
            'requested_by'         => $this->teamMember->id,
            'impact_schedule_days' => 5,
            'impact_budget_amount' => 5000.00,
            'impact_budget_hours'  => 40.00,
            'status'               => ChangeRequest::STATUS_PENDING,
            'created_by'           => $this->teamMember->id,
        ]);

        // Attempt rejection without remarks
        $failResponse = $this->actingAs($this->projectManager)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.reject', [$this->project, $cr]), [
                'rejection_remarks' => '',
            ]);

        $failResponse->assertSessionHasErrors('rejection_remarks');
        $cr->refresh();
        $this->assertEquals(ChangeRequest::STATUS_PENDING, $cr->status);

        // Rejection with remarks
        $successResponse = $this->actingAs($this->projectManager)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.reject', [$this->project, $cr]), [
                'rejection_remarks' => 'Client declined to fund this scope addition.',
            ]);

        $successResponse->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));

        $cr->refresh();
        $this->assertEquals(ChangeRequest::STATUS_REJECTED, $cr->status);
        $this->assertEquals('Client declined to fund this scope addition.', $cr->rejection_remarks);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.change_request_rejected',
        ]);
    }

    /** @test */
    public function approved_cr_can_transition_to_implemented(): void
    {
        $cr = ChangeRequest::create([
            'tenant_id'            => $this->tenant->id,
            'project_id'           => $this->project->id,
            'cr_number'            => 'PRJ-CR-01-CR-001',
            'title'                => 'Approved Feature',
            'description'          => 'Implementation complete',
            'requested_by'         => $this->teamMember->id,
            'impact_schedule_days' => 2,
            'impact_budget_amount' => 1500.00,
            'impact_budget_hours'  => 12.00,
            'status'               => ChangeRequest::STATUS_APPROVED,
            'approved_by'          => $this->projectManager->id,
            'approved_at'          => now(),
            'created_by'           => $this->teamMember->id,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.implement', [$this->project, $cr]));

        $response->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));

        $cr->refresh();
        $this->assertEquals(ChangeRequest::STATUS_IMPLEMENTED, $cr->status);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.change_request_implemented',
        ]);
    }

    /** @test */
    public function non_approved_cr_cannot_transition_to_implemented(): void
    {
        $cr = ChangeRequest::create([
            'tenant_id'            => $this->tenant->id,
            'project_id'           => $this->project->id,
            'cr_number'            => 'PRJ-CR-01-CR-001',
            'title'                => 'Pending Feature',
            'description'          => 'Cannot implement before approval',
            'requested_by'         => $this->teamMember->id,
            'impact_schedule_days' => 2,
            'impact_budget_amount' => 1500.00,
            'impact_budget_hours'  => 12.00,
            'status'               => ChangeRequest::STATUS_PENDING,
            'created_by'           => $this->teamMember->id,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withHeader('X-Tenant', 'cr-workspace')
            ->post(route('projects.change-requests.implement', [$this->project, $cr]));

        $response->assertSessionHasErrors('status');
        $cr->refresh();
        $this->assertEquals(ChangeRequest::STATUS_PENDING, $cr->status);
    }

    /** @test */
    public function tenant_isolation_prevents_cross_tenant_access_to_change_requests(): void
    {
        $cr = ChangeRequest::create([
            'tenant_id'            => $this->tenant->id,
            'project_id'           => $this->project->id,
            'cr_number'            => 'PRJ-CR-01-CR-001',
            'title'                => 'Tenant A CR',
            'description'          => 'Cross tenant check',
            'requested_by'         => $this->teamMember->id,
            'impact_schedule_days' => 2,
            'impact_budget_amount' => 1000.00,
            'impact_budget_hours'  => 10.00,
            'status'               => ChangeRequest::STATUS_PENDING,
            'created_by'           => $this->teamMember->id,
        ]);

        $response = $this->actingAs($this->otherTenantUser)
            ->withHeader('X-Tenant', 'cr-workspace-2')
            ->post(route('projects.change-requests.approve', [$this->project, $cr]));

        $this->assertTrue(in_array($response->status(), [403, 404], true));
        $cr->refresh();
        $this->assertEquals(ChangeRequest::STATUS_PENDING, $cr->status);
    }
}
