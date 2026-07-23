<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\Models\Project;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSortingTest extends TestCase
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
            'start_date' => now(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ], $overrides));
    }

    /** @test */
    public function clicking_a_column_sorts_ascending_and_descending(): void
    {
        $this->createProject(['project_code' => 'PRJ-0003', 'name' => 'Charlie']);
        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Alpha']);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Bravo']);

        $asc = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index', ['sort' => 'name', 'direction' => 'asc']));

        $asc->assertOk();
        $names = $asc->viewData('projects')->pluck('name')->all();
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $names);

        $desc = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index', ['sort' => 'name', 'direction' => 'desc']));

        $desc->assertOk();
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $desc->viewData('projects')->pluck('name')->all());
    }

    /** @test */
    public function sorting_by_client_or_owner_joins_the_related_table(): void
    {
        $customerA = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Zeta Corp']);
        $customerB = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Corp']);

        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'One', 'customer_id' => $customerA->id]);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Two', 'customer_id' => $customerB->id]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index', ['sort' => 'client', 'direction' => 'asc']));

        $response->assertOk();
        $codes = $response->viewData('projects')->pluck('project_code')->all();
        $this->assertSame(['PRJ-0002', 'PRJ-0001'], $codes);
    }

    /** @test */
    public function sorting_combines_safely_with_search_and_status_filters(): void
    {
        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Zulu Build', 'status' => Project::STATUS_ACTIVE]);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Alpha Build', 'status' => Project::STATUS_ACTIVE]);
        $this->createProject(['project_code' => 'PRJ-0003', 'name' => 'Beta Other', 'status' => Project::STATUS_DRAFT]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index', [
                'search' => 'Build',
                'status' => Project::STATUS_ACTIVE,
                'sort' => 'client',
                'direction' => 'desc',
            ]));

        $response->assertOk();
        $names = $response->viewData('projects')->pluck('name')->all();
        $this->assertCount(2, $names);
        $this->assertContains('Zulu Build', $names);
        $this->assertContains('Alpha Build', $names);
    }

    /** @test */
    public function an_unknown_sort_column_is_ignored_and_falls_back_to_default_order(): void
    {
        $this->createProject(['project_code' => 'PRJ-0001']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index', ['sort' => 'budget; DROP TABLE projects;']));

        $response->assertOk();
    }

    /** @test */
    public function pagination_preserves_the_current_sort_and_direction(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->createProject([
                'project_code' => sprintf('PRJ-%04d', $i),
                'name' => 'Project ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index', ['sort' => 'name', 'direction' => 'desc', 'page' => 2]));

        $response->assertOk();
        $projects = $response->viewData('projects');
        $this->assertSame(2, $projects->currentPage());
        $this->assertSame('Project 05', $projects->items()[0]->name);
        $response->assertSee('sort=name');
        $response->assertSee('direction=desc');
    }
}
