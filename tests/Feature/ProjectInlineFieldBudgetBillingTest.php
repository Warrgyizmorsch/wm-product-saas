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

class ProjectInlineFieldBudgetBillingTest extends TestCase
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
    public function budget_type_can_be_updated_inline(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'budget_type', 'value' => 'Fixed']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'budget_type', 'value' => 'Fixed']);
        $this->assertSame('Fixed', $project->fresh()->budget_type);
    }

    /** @test */
    public function budget_type_can_be_cleared_inline(): void
    {
        $project = $this->createProject(['budget_type' => 'Fixed']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'budget_type', 'value' => '']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'budget_type', 'value' => null]);
        $this->assertNull($project->fresh()->budget_type);
    }

    /** @test */
    public function budget_type_must_be_one_of_the_allowed_values(): void
    {
        $project = $this->createProject(['budget_type' => 'Fixed']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'budget_type', 'value' => 'Not A Budget Type']);

        $response->assertStatus(422);
        $this->assertSame('Fixed', $project->fresh()->budget_type);
    }

    /** @test */
    public function billing_method_can_be_updated_inline(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'billing_method', 'value' => 'Milestone Based']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'billing_method', 'value' => 'Milestone Based']);
        $this->assertSame('Milestone Based', $project->fresh()->billing_method);
    }

    /** @test */
    public function billing_method_can_be_cleared_inline(): void
    {
        $project = $this->createProject(['billing_method' => 'Milestone Based']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'billing_method', 'value' => '']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'billing_method', 'value' => null]);
        $this->assertNull($project->fresh()->billing_method);
    }

    /** @test */
    public function billing_method_must_be_one_of_the_allowed_values(): void
    {
        $project = $this->createProject(['billing_method' => 'Milestone Based']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'billing_method', 'value' => 'Not A Method']);

        $response->assertStatus(422);
        $this->assertSame('Milestone Based', $project->fresh()->billing_method);
    }
}
