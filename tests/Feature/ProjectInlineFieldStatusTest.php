<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Project;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectInlineFieldStatusTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);
        $this->seed(RbacSeeder::class);

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password'),
        ]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create(['user_id' => $this->tenantOwner->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
    }

    private function createProject(array $overrides = []): Project
    {
        return Project::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'Original Name',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => '2026-01-01',
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ], $overrides));
    }

    /** @test */
    public function status_can_be_updated_along_an_allowed_transition(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_DRAFT]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_ACTIVE]);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'status', 'value' => Project::STATUS_ACTIVE]);
        $this->assertSame(Project::STATUS_ACTIVE, $project->fresh()->status);
    }

    /**
     * Regression test for the activity-log inconsistency between inline and
     * full-form status changes: inline edits used to persist via the generic
     * updateField() path and log a plain 'project.updated' entry instead of
     * 'project.status_changed', silently hiding status changes from the
     * activity timeline. Both entry points must now share the same
     * ProjectService::changeStatus() workflow and produce the same event
     * type with the same old/new metadata.
     */
    /** @test */
    public function inline_status_change_writes_a_status_changed_activity_log_with_metadata(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_DRAFT]);

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_ACTIVE]);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'event_type' => 'project.status_changed',
        ]);

        $log = \App\Domains\Projects\Models\ActivityLog::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('event_type', 'project.status_changed')
            ->firstOrFail();

        $this->assertSame(['old' => Project::STATUS_DRAFT, 'new' => Project::STATUS_ACTIVE], $log->metadata);

        // Not the generic fallback the bug used to produce.
        $this->assertDatabaseMissing('project_activity_logs', [
            'project_id' => $project->id,
            'event_type' => 'project.updated',
        ]);
    }

    /** @test */
    public function inline_same_status_resubmit_writes_no_activity_log(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_ACTIVE]);

        $before = \App\Domains\Projects\Models\ActivityLog::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->count();

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_ACTIVE]);

        $after = \App\Domains\Projects\Models\ActivityLog::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->count();

        $this->assertSame($before, $after);
    }

    /** @test */
    public function status_cannot_skip_to_a_disallowed_transition(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_DRAFT]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_COMPLETED]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        $this->assertSame(Project::STATUS_DRAFT, $project->fresh()->status);
    }

    /** @test */
    public function status_can_be_closed_from_completed_via_inline_edit(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_COMPLETED]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_CLOSED]);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'status', 'value' => Project::STATUS_CLOSED]);
        $this->assertSame(Project::STATUS_CLOSED, $project->fresh()->status);
    }

    /** @test */
    public function status_cannot_skip_completed_straight_to_closed(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_ACTIVE]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_CLOSED]);

        $response->assertStatus(422);
        $this->assertSame(Project::STATUS_ACTIVE, $project->fresh()->status);
    }

    /** @test */
    public function status_cannot_move_from_draft_to_closed(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_DRAFT]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_CLOSED]);

        $response->assertStatus(422);
        $this->assertSame(Project::STATUS_DRAFT, $project->fresh()->status);
    }

    /** @test */
    public function status_cannot_move_from_on_hold_to_completed(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_ON_HOLD]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_COMPLETED]);

        $response->assertStatus(422);
        $this->assertSame(Project::STATUS_ON_HOLD, $project->fresh()->status);
    }

    /**
     * @test
     * @dataProvider cancellableStatusProvider
     */
    public function status_can_be_cancelled_from_draft_active_or_on_hold(string $fromStatus): void
    {
        $project = $this->createProject(['status' => $fromStatus]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_CANCELLED]);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'status', 'value' => Project::STATUS_CANCELLED]);
        $this->assertSame(Project::STATUS_CANCELLED, $project->fresh()->status);
    }

    public static function cancellableStatusProvider(): array
    {
        return [
            'from draft' => [Project::STATUS_DRAFT],
            'from active' => [Project::STATUS_ACTIVE],
            'from on hold' => [Project::STATUS_ON_HOLD],
        ];
    }

    /** @test */
    public function status_cannot_be_cancelled_from_completed(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_COMPLETED]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_CANCELLED]);

        $response->assertStatus(422);
        $this->assertSame(Project::STATUS_COMPLETED, $project->fresh()->status);
    }

    /**
     * @test
     * @dataProvider terminalStatusProvider
     */
    public function terminal_statuses_reject_any_further_transition(string $terminalStatus): void
    {
        $project = $this->createProject(['status' => $terminalStatus]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_ACTIVE]);

        $response->assertStatus(422);
        $this->assertSame($terminalStatus, $project->fresh()->status);
    }

    public static function terminalStatusProvider(): array
    {
        return [
            'closed' => [Project::STATUS_CLOSED],
            'cancelled' => [Project::STATUS_CANCELLED],
        ];
    }

    /**
     * @test
     * @dataProvider sameStatusProvider
     */
    public function unsetting_status_to_the_same_value_is_a_no_op_success(string $status): void
    {
        $project = $this->createProject(['status' => $status]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => $status]);

        $response->assertOk();
        $this->assertSame($status, $project->fresh()->status);
    }

    public static function sameStatusProvider(): array
    {
        return [
            'active' => [Project::STATUS_ACTIVE],
            'closed' => [Project::STATUS_CLOSED],
            'cancelled' => [Project::STATUS_CANCELLED],
        ];
    }

    /** @test */
    public function unknown_field_returns_a_normalized_validation_error_shape(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'not_a_real_field', 'value' => 'x']);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        $errors = $response->json('errors');
        $this->assertNotEmpty($errors);
        $this->assertIsArray(reset($errors));
    }
}
