<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\ProjectReview;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectReviewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $tenant2;
    private User $tenantOwner;
    private User $manager;
    private User $developer;
    private User $otherTenantUser;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Tenant::create([
            'name'   => 'Primary Workspace',
            'slug'   => 'primary-workspace',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->tenant2 = Tenant::create([
            'name'   => 'Secondary Workspace',
            'slug'   => 'secondary-workspace',
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
        UserRole::create(['user_id' => $this->tenantOwner->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant->id]);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Project Manager',
            'email'     => 'pm@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->manager->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant->id]);

        $this->developer = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Developer Dev',
            'email'     => 'dev@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->developer->id, 'role_id' => $readOnlyRole->id, 'tenant_id' => $this->tenant->id]);

        $this->otherTenantUser = User::create([
            'tenant_id' => $this->tenant2->id,
            'name'      => 'Other Owner',
            'email'     => 'other@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->otherTenantUser->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant2->id]);

        $this->project = Project::create([
            'tenant_id'    => $this->tenant->id,
            'project_code' => 'PRJ-REV-01',
            'name'         => 'Review Test Project',
            'owner_id'     => $this->tenantOwner->id,
            'manager_id'   => $this->manager->id,
            'start_date'   => now(),
            'priority'     => 'High',
            'status'       => 'Active',
            'budget_amount'=> 10000.00,
            'budget_hours' => 200.00,
        ]);

        ProjectMember::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'user_id'      => $this->manager->id,
            'project_role' => 'Manager',
            'is_active'    => true,
        ]);

        ProjectMember::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'user_id'      => $this->developer->id,
            'project_role' => 'Developer',
            'is_active'    => true,
        ]);
    }

    /** @test */
    public function review_creation_is_blocked_when_milestone_count_is_zero(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'primary-workspace')
            ->post(route('projects.reviews.store', $this->project), [
                'review_date' => now()->toDateString(),
                'comments'    => 'Attempting review without milestones',
            ]);

        $response->assertSessionHasErrors('review_date');
        $this->assertDatabaseCount('project_reviews', 0);
    }

    /** @test */
    public function review_creation_is_blocked_when_incomplete_milestones_exist(): void
    {
        Milestone::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'name'        => 'Milestone 1',
            'status'      => Milestone::STATUS_COMPLETED,
            'start_date'  => now()->subDays(5),
            'due_date'    => now()->addDays(5),
        ]);

        Milestone::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'name'        => 'Milestone 2',
            'status'      => Milestone::STATUS_ACTIVE,
            'start_date'  => now()->subDays(2),
            'due_date'    => now()->addDays(10),
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'primary-workspace')
            ->post(route('projects.reviews.store', $this->project), [
                'review_date' => now()->toDateString(),
                'comments'    => 'Attempting review with incomplete milestones',
            ]);

        $response->assertSessionHasErrors('review_date');
        $this->assertDatabaseCount('project_reviews', 0);
    }

    /** @test */
    public function review_creation_succeeds_and_always_starts_as_pending_when_all_milestones_are_completed(): void
    {
        Milestone::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'name'        => 'Milestone 1',
            'status'      => Milestone::STATUS_COMPLETED,
            'start_date'  => now()->subDays(10),
            'due_date'    => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'primary-workspace')
            ->post(route('projects.reviews.store', $this->project), [
                'review_date'   => now()->toDateString(),
                'reviewer_id'   => $this->manager->id,
                'reviewer_name' => 'John Client',
                'comments'      => 'Client UAT kickoff',
                'status'        => ProjectReview::STATUS_APPROVED, // Attempt to directly create as Approved
            ]);

        $response->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));
        $this->assertDatabaseCount('project_reviews', 1);

        $review = ProjectReview::first();
        $this->assertEquals(ProjectReview::STATUS_PENDING, $review->status);
        $this->assertEquals('John Client', $review->reviewer_name);
        $this->assertEquals($this->manager->id, $review->reviewer_id);
    }

    /** @test */
    public function cannot_create_second_pending_review_for_the_same_project(): void
    {
        Milestone::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'name'        => 'Milestone 1',
            'status'      => Milestone::STATUS_COMPLETED,
            'start_date'  => now()->subDays(10),
            'due_date'    => now()->subDays(5),
        ]);

        ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'review_date' => now()->toDateString(),
            'status'      => ProjectReview::STATUS_PENDING,
            'created_by'  => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'primary-workspace')
            ->post(route('projects.reviews.store', $this->project), [
                'review_date' => now()->toDateString(),
                'comments'    => 'Attempting second pending review',
            ]);

        $response->assertSessionHasErrors('review_date');
        $this->assertDatabaseCount('project_reviews', 1);
    }

    /** @test */
    public function pending_review_can_be_signed_off_as_approved_with_evidence(): void
    {
        $review = ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'review_date' => now()->toDateString(),
            'status'      => ProjectReview::STATUS_PENDING,
            'created_by'  => $this->tenantOwner->id,
        ]);

        $file = UploadedFile::fake()->create('uat_signoff.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'primary-workspace')
            ->post(route('projects.reviews.signoff', [$this->project, $review]), [
                'status'        => ProjectReview::STATUS_APPROVED,
                'sign_off_ref'  => 'CLIENT-PO-9921',
                'comments'      => 'Client accepted all deliverables without caveats.',
                'evidence_file' => $file,
            ]);

        $response->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));

        $review->refresh();
        $this->assertEquals(ProjectReview::STATUS_APPROVED, $review->status);
        $this->assertEquals('CLIENT-PO-9921', $review->sign_off_ref);
        $this->assertStringContainsString('Client accepted', $review->comments);

        // Verify polymorphic ProjectDocument was attached
        $this->assertCount(1, $review->documents);
        $doc = $review->documents->first();
        $this->assertEquals($review->id, $doc->attachable_id);
        $this->assertEquals(ProjectReview::class, $doc->attachable_type);
        Storage::disk('local')->assertExists($doc->file_path);

        // Verify activity log recorded
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.review_approved',
        ]);
    }

    /** @test */
    public function pending_review_can_be_signed_off_as_rework_required(): void
    {
        $review = ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'review_date' => now()->toDateString(),
            'status'      => ProjectReview::STATUS_PENDING,
            'created_by'  => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'primary-workspace')
            ->post(route('projects.reviews.signoff', [$this->project, $review]), [
                'status'   => ProjectReview::STATUS_REWORK_REQUIRED,
                'comments' => 'Performance tests failed on staging cluster.',
            ]);

        $response->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));

        $review->refresh();
        $this->assertEquals(ProjectReview::STATUS_REWORK_REQUIRED, $review->status);

        // Verify activity log recorded
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.review_rework_required',
        ]);
    }

    /** @test */
    public function non_pending_review_cannot_be_signed_off_again(): void
    {
        $review = ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'review_date' => now()->toDateString(),
            'status'      => ProjectReview::STATUS_APPROVED,
            'created_by'  => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'primary-workspace')
            ->post(route('projects.reviews.signoff', [$this->project, $review]), [
                'status'   => ProjectReview::STATUS_REWORK_REQUIRED,
                'comments' => 'Attempting to overwrite approved review',
            ]);

        $response->assertSessionHasErrors('status');
        $review->refresh();
        $this->assertEquals(ProjectReview::STATUS_APPROVED, $review->status);
    }

    /** @test */
    public function historical_reviews_are_preserved_when_new_review_cycle_is_started(): void
    {
        Milestone::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'name'        => 'Milestone 1',
            'status'      => Milestone::STATUS_COMPLETED,
            'start_date'  => now()->subDays(10),
            'due_date'    => now()->subDays(5),
        ]);

        $historicalReview = ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'review_date' => now()->subMonth()->toDateString(),
            'status'      => ProjectReview::STATUS_REWORK_REQUIRED,
            'comments'    => 'First cycle rework required',
            'created_by'  => $this->tenantOwner->id,
        ]);

        // Start cycle 2
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'primary-workspace')
            ->post(route('projects.reviews.store', $this->project), [
                'review_date' => now()->toDateString(),
                'comments'    => 'Second UAT cycle kickoff',
            ]);

        $response->assertRedirect(route('projects.show', ['project' => $this->project, 'tab' => 'reviews']));
        $this->assertDatabaseCount('project_reviews', 2);

        $this->assertDatabaseHas('project_reviews', [
            'id'     => $historicalReview->id,
            'status' => ProjectReview::STATUS_REWORK_REQUIRED,
        ]);

        $latest = ProjectReview::latest('id')->first();
        $this->assertEquals(ProjectReview::STATUS_PENDING, $latest->status);
    }

    /** @test */
    public function tenant_isolation_prevents_cross_tenant_access_to_reviews(): void
    {
        $review = ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'review_date' => now()->toDateString(),
            'status'      => ProjectReview::STATUS_PENDING,
            'created_by'  => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->otherTenantUser)
            ->withHeader('X-Tenant', 'secondary-workspace')
            ->post(route('projects.reviews.signoff', [$this->project, $review]), [
                'status' => ProjectReview::STATUS_APPROVED,
            ]);

        $this->assertTrue(in_array($response->status(), [403, 404], true));
        $review->refresh();
        $this->assertEquals(ProjectReview::STATUS_PENDING, $review->status);
    }

    /** @test */
    public function unauthorized_user_without_signoff_permission_cannot_sign_off(): void
    {
        $review = ProjectReview::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'review_date' => now()->toDateString(),
            'status'      => ProjectReview::STATUS_PENDING,
            'created_by'  => $this->tenantOwner->id,
        ]);

        // Developer role has team_member role without projects.reviews.signoff permission
        $response = $this->actingAs($this->developer)
            ->withHeader('X-Tenant', 'primary-workspace')
            ->post(route('projects.reviews.signoff', [$this->project, $review]), [
                'status' => ProjectReview::STATUS_APPROVED,
            ]);

        $response->assertForbidden();
        $review->refresh();
        $this->assertEquals(ProjectReview::STATUS_PENDING, $review->status);
    }
}
