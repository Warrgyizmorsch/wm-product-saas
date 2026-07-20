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

class MilestoneHeroSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function workspace_page_renders_with_inline_edit_controls_for_managers(): void
    {
        $tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);

        $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create(['user_id' => $owner->id, 'role_id' => $role->id, 'tenant_id' => $tenant->id]);

        $project = Project::create([
            'tenant_id' => $tenant->id, 'project_code' => 'PRJ-0001', 'name' => 'ERP Development',
            'owner_id' => $owner->id, 'start_date' => '2026-01-01', 'priority' => 'High', 'status' => 'Active',
        ]);

        $milestone = Milestone::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'owner_id' => $owner->id,
            'name' => 'Design Phase', 'status' => Milestone::STATUS_ACTIVE,
            'start_date' => '2026-01-05', 'due_date' => '2026-01-20', 'completion_percentage' => 10,
        ]);

        $response = $this->actingAs($owner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.milestones.show', [$project, $milestone]));

        $response->assertOk();
        $response->assertSee('inline-edit/index.js', false);
        $response->assertSee('data-field="name"', false);
        $response->assertSee('data-field="description"', false);
        $response->assertSee('data-field="status"', false);
        $response->assertSee('data-field="owner_id"', false);
        $response->assertSee('data-field="start_date"', false);
        $response->assertSee('data-field="due_date"', false);
    }
}
