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

class ProjectInlineFieldSelectTest extends TestCase
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
    public function priority_can_be_updated_inline(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'priority', 'value' => Project::PRIORITY_CRITICAL]);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'priority', 'value' => Project::PRIORITY_CRITICAL]);
        $this->assertSame(Project::PRIORITY_CRITICAL, $project->fresh()->priority);
    }

    /** @test */
    public function priority_must_be_one_of_the_allowed_values(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'priority', 'value' => 'Not A Priority']);

        $response->assertStatus(422);
        $this->assertSame(Project::PRIORITY_MEDIUM, $project->fresh()->priority);
    }

    /** @test */
    public function priority_is_required(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'priority', 'value' => '']);

        $response->assertStatus(422);
        $this->assertSame(Project::PRIORITY_MEDIUM, $project->fresh()->priority);
    }
}
