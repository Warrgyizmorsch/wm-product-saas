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

class ProjectInlineFieldDateTest extends TestCase
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
            'end_date' => '2026-06-30',
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ], $overrides));
    }

    /** @test */
    public function start_date_can_be_updated_inline(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'start_date', 'value' => '2026-02-01']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'start_date', 'value' => '2026-02-01']);
        $this->assertSame('2026-02-01', $project->fresh()->start_date->format('Y-m-d'));
    }

    /** @test */
    public function end_date_can_be_cleared_inline(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'end_date', 'value' => '']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'end_date', 'value' => null]);
        $this->assertNull($project->fresh()->end_date);
    }

    /** @test */
    public function start_date_cannot_move_past_existing_end_date(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'start_date', 'value' => '2026-12-01']);

        $response->assertStatus(422);
        $this->assertSame('2026-01-01', $project->fresh()->start_date->format('Y-m-d'));
    }

    /** @test */
    public function end_date_cannot_move_before_existing_start_date(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'end_date', 'value' => '2025-12-01']);

        $response->assertStatus(422);
        $this->assertSame('2026-06-30', $project->fresh()->end_date->format('Y-m-d'));
    }

    /** @test */
    public function start_date_is_required(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'start_date', 'value' => '']);

        $response->assertStatus(422);
        $this->assertNotNull($project->fresh()->start_date);
    }

    /** @test */
    public function invalid_date_string_is_rejected(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'start_date', 'value' => 'not-a-date']);

        $response->assertStatus(422);
    }
}
