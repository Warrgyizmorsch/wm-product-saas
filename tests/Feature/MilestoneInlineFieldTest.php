<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneInlineFieldTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create([
            'user_id' => $this->tenantOwner->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'ERP Development',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => '2026-01-01',
            'priority' => 'High',
            'status' => 'Active',
        ]);
    }

    private function createMilestone(array $overrides = []): Milestone
    {
        return Milestone::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Original Name',
            'status' => Milestone::STATUS_DRAFT,
            'start_date' => '2026-01-05',
            'due_date' => '2026-01-20',
            'completion_percentage' => 0,
        ], $overrides));
    }

    private function fieldUrl(Milestone $milestone): string
    {
        return route('projects.milestones.field', [$this->project, $milestone]);
    }

    /** @test */
    public function name_can_be_updated_inline(): void
    {
        $milestone = $this->createMilestone();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'name', 'value' => 'Renamed Milestone']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'name', 'value' => 'Renamed Milestone']);
        $this->assertSame('Renamed Milestone', $milestone->fresh()->name);
    }

    /** @test */
    public function name_is_required(): void
    {
        $milestone = $this->createMilestone();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'name', 'value' => '']);

        $response->assertStatus(422);
        $this->assertSame('Original Name', $milestone->fresh()->name);
    }

    /** @test */
    public function description_can_be_updated_and_cleared_inline(): void
    {
        $milestone = $this->createMilestone(['description' => 'Old description']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'description', 'value' => 'New description']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'description', 'value' => 'New description']);
        $this->assertSame('New description', $milestone->fresh()->description);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'description', 'value' => '']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'description', 'value' => null]);
        $this->assertNull($milestone->fresh()->description);
    }

    /** @test */
    public function status_can_be_updated_inline_using_full_milestone_statuses(): void
    {
        $milestone = $this->createMilestone();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'status', 'value' => Milestone::STATUS_CLOSED]);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'status', 'value' => Milestone::STATUS_CLOSED]);
        $this->assertSame(Milestone::STATUS_CLOSED, $milestone->fresh()->status);
    }

    /** @test */
    public function status_must_be_one_of_the_allowed_values(): void
    {
        $milestone = $this->createMilestone();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'status', 'value' => 'Not A Status']);

        $response->assertStatus(422);
        $this->assertSame(Milestone::STATUS_DRAFT, $milestone->fresh()->status);
    }

    /** @test */
    public function owner_can_be_assigned_and_cleared_inline(): void
    {
        $milestone = $this->createMilestone();
        $assignee = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Assignee',
            'email' => 'assignee@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'owner_id', 'value' => $assignee->id]);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'owner_id', 'value' => $assignee->id]);
        $this->assertSame($assignee->id, $milestone->fresh()->owner_id);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'owner_id', 'value' => '']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'owner_id', 'value' => null]);
        $this->assertNull($milestone->fresh()->owner_id);
    }

    /** @test */
    public function owner_must_belong_to_the_tenant(): void
    {
        $milestone = $this->createMilestone();

        $otherTenant = Tenant::create([
            'name' => 'Other Tenant', 'slug' => 'other-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);
        $outsider = User::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'owner_id', 'value' => $outsider->id]);

        $response->assertStatus(422);
        $this->assertNull($milestone->fresh()->owner_id);
    }

    /** @test */
    public function start_date_can_be_updated_inline(): void
    {
        $milestone = $this->createMilestone();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'start_date', 'value' => '2026-01-10']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'start_date', 'value' => '2026-01-10']);
        $this->assertSame('2026-01-10', $milestone->fresh()->start_date->format('Y-m-d'));
    }

    /** @test */
    public function start_date_cannot_move_past_the_due_date(): void
    {
        $milestone = $this->createMilestone();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'start_date', 'value' => '2026-02-01']);

        $response->assertStatus(422);
        $this->assertSame('2026-01-05', $milestone->fresh()->start_date->format('Y-m-d'));
    }

    /** @test */
    public function due_date_can_be_updated_inline(): void
    {
        $milestone = $this->createMilestone();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'due_date', 'value' => '2026-01-25']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'due_date', 'value' => '2026-01-25']);
        $this->assertSame('2026-01-25', $milestone->fresh()->due_date->format('Y-m-d'));
    }

    /** @test */
    public function due_date_cannot_move_before_the_start_date(): void
    {
        $milestone = $this->createMilestone();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'due_date', 'value' => '2026-01-01']);

        $response->assertStatus(422);
        $this->assertSame('2026-01-20', $milestone->fresh()->due_date->format('Y-m-d'));
    }

    /** @test */
    public function unknown_fields_are_rejected(): void
    {
        $milestone = $this->createMilestone();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'completion_percentage', 'value' => 50]);

        $response->assertStatus(422);
    }

    /** @test */
    public function users_without_manage_permission_cannot_inline_edit(): void
    {
        $milestone = $this->createMilestone();

        $viewer = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($viewer)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson($this->fieldUrl($milestone), ['field' => 'name', 'value' => 'Hijacked']);

        $response->assertForbidden();
        $this->assertSame('Original Name', $milestone->fresh()->name);
    }
}
