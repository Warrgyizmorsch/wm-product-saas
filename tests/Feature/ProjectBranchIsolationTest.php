<?php

namespace Tests\Feature;

use App\Core\Branch\BranchContext;
use App\Core\Company\CompanyContext;
use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\MilestoneService;
use App\Domains\Projects\Services\ProjectService;
use App\Domains\Projects\Services\TaskListService;
use App\Domains\Projects\Services\TaskService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectBranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Company $company;
    private Branch $branch1;
    private Branch $branch2;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Isolation Tenant',
            'slug' => 'isolation-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);
        app(TenantContext::class)->set($this->tenant);

        $this->seed(RbacSeeder::class);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Main Company',
            'code' => 'MC01',
            'is_default' => true,
        ]);
        app(CompanyContext::class)->set($this->company);

        $this->branch1 = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Branch One',
            'code' => 'BR01',
            'is_default' => true,
        ]);

        $this->branch2 = Branch::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Branch Two',
            'code' => 'BR02',
            'is_default' => false,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Project Manager',
            'email' => 'pm@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->withHeader('X-Tenant', $this->tenant->slug);
    }

    public function test_projects_can_be_created_in_different_branches_without_code_collision(): void
    {
        $projectService = app(ProjectService::class);

        // 1. Create project in Branch 1
        app(BranchContext::class)->set($this->branch1);
        $project1 = $projectService->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch1->id,
            'name' => 'Branch 1 Project Alpha',
            'owner_id' => $this->user->id,
            'start_date' => now()->toDateString(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ]);

        $this->assertEquals('PRJ-0001', $project1->project_code);
        $this->assertEquals($this->branch1->id, $project1->branch_id);

        // 2. Switch to Branch 2 and create project
        app(BranchContext::class)->set($this->branch2);
        $project2 = $projectService->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch2->id,
            'name' => 'Branch 2 Project Beta',
            'owner_id' => $this->user->id,
            'start_date' => now()->toDateString(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ]);

        // Branch 2 should get its own clean sequence without unique constraint collision!
        $this->assertEquals('PRJ-0001', $project2->project_code);
        $this->assertEquals($this->branch2->id, $project2->branch_id);

        // 3. Create another project in Branch 2 -> should increment to PRJ-0002
        $project3 = $projectService->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch2->id,
            'name' => 'Branch 2 Project Gamma',
            'owner_id' => $this->user->id,
            'start_date' => now()->toDateString(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ]);

        $this->assertEquals('PRJ-0002', $project3->project_code);
        $this->assertEquals($this->branch2->id, $project3->branch_id);

        // 4. Verify branch scoping: in Branch 2, only Branch 2 projects are visible
        $branch2Projects = Project::query()->pluck('project_code')->all();
        $this->assertContains('PRJ-0001', $branch2Projects);
        $this->assertContains('PRJ-0002', $branch2Projects);
        $this->assertCount(2, $branch2Projects);

        // Switch to Branch 1: only Branch 1 projects are visible
        app(BranchContext::class)->set($this->branch1);
        $branch1Projects = Project::query()->pluck('project_code')->all();
        $this->assertEquals(['PRJ-0001'], $branch1Projects);
    }

    public function test_child_entities_inherit_branch_and_company_from_parent_project(): void
    {
        $projectService = app(ProjectService::class);
        $taskListService = app(TaskListService::class);
        $milestoneService = app(MilestoneService::class);
        $taskService = app(TaskService::class);

        app(BranchContext::class)->set($this->branch2);

        $project = $projectService->create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch2->id,
            'name' => 'Branch 2 Integrated Project',
            'owner_id' => $this->user->id,
            'start_date' => now()->toDateString(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_ACTIVE,
        ]);

        $taskList = $taskListService->create($project, [
            'name' => 'Branch 2 Task List',
        ]);
        $this->assertEquals($this->branch2->id, $taskList->branch_id);
        $this->assertEquals($this->company->id, $taskList->company_id);

        $milestone = $milestoneService->create($project, [
            'name' => 'Branch 2 Milestone',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);
        $this->assertEquals($this->branch2->id, $milestone->branch_id);
        $this->assertEquals($this->company->id, $milestone->company_id);

        $task = $taskService->create($project, [
            'task_list_id' => $taskList->id,
            'milestone_id' => $milestone->id,
            'title' => 'Branch 2 Task 1',
        ]);
        $this->assertEquals($this->branch2->id, $task->branch_id);
        $this->assertEquals($this->company->id, $task->company_id);
    }

    public function test_http_store_project_in_different_branch_succeeds(): void
    {
        $this->actingAs($this->user);

        // First project in Branch 1
        session([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch1->id,
        ]);

        $response1 = $this->post(route('projects.store'), [
            'name' => 'HTTP Branch 1 Project',
            'owner_id' => $this->user->id,
            'start_date' => now()->toDateString(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ]);
        $response1->assertRedirect();

        $p1 = Project::withoutGlobalScopes()->where('name', 'HTTP Branch 1 Project')->firstOrFail();
        $this->assertEquals('PRJ-0001', $p1->project_code);
        $this->assertEquals($this->branch1->id, $p1->branch_id);

        // Second project in Branch 2
        session([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'branch_id' => $this->branch2->id,
        ]);

        $response2 = $this->post(route('projects.store'), [
            'name' => 'HTTP Branch 2 Project',
            'owner_id' => $this->user->id,
            'start_date' => now()->toDateString(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ]);
        $response2->assertRedirect();

        $p2 = Project::withoutGlobalScopes()->where('name', 'HTTP Branch 2 Project')->firstOrFail();
        $this->assertEquals('PRJ-0001', $p2->project_code);
        $this->assertEquals($this->branch2->id, $p2->branch_id);

        // GET /projects/PRJ-0001 while in Branch 2 sees Branch 2 project!
        $showResponse2 = $this->get('/projects/PRJ-0001');
        $showResponse2->assertOk();
        $showResponse2->assertSee('HTTP Branch 2 Project');
        $showResponse2->assertDontSee('HTTP Branch 1 Project');

        // Switch to Branch 1 session: GET /projects/PRJ-0001 sees Branch 1 project!
        session(['branch_id' => $this->branch1->id]);
        $showResponse1 = $this->get('/projects/PRJ-0001');
        $showResponse1->assertOk();
        $showResponse1->assertSee('HTTP Branch 1 Project');
        $showResponse1->assertDontSee('HTTP Branch 2 Project');
    }
}
