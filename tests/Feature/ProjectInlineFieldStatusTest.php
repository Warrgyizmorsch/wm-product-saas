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
    public function status_cannot_be_set_to_closed_via_inline_edit(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_COMPLETED]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_CLOSED]);

        $response->assertStatus(422);
        $this->assertSame(Project::STATUS_COMPLETED, $project->fresh()->status);
    }

    /** @test */
    public function unsetting_status_to_the_same_value_is_a_no_op_success(): void
    {
        $project = $this->createProject(['status' => Project::STATUS_ACTIVE]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'status', 'value' => Project::STATUS_ACTIVE]);

        $response->assertOk();
        $this->assertSame(Project::STATUS_ACTIVE, $project->fresh()->status);
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
