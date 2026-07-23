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

class ProjectFilteringTest extends TestCase
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

    private function filter(array $query)
    {
        return $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index', $query));
    }

    /** @test */
    public function search_filters_by_name_or_code(): void
    {
        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Website Revamp']);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Mobile App']);

        $response = $this->filter(['search' => 'Website']);

        $response->assertOk();
        $names = $response->viewData('projects')->pluck('name')->all();
        $this->assertSame(['Website Revamp'], $names);
    }

    /** @test */
    public function client_filter_returns_only_projects_for_that_customer(): void
    {
        $customerA = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Corp']);
        $customerB = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Globex Corp']);

        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'For Acme', 'customer_id' => $customerA->id]);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'For Globex', 'customer_id' => $customerB->id]);

        $response = $this->filter(['client_id' => $customerA->id]);

        $response->assertOk();
        $names = $response->viewData('projects')->pluck('name')->all();
        $this->assertSame(['For Acme'], $names);
    }

    /** @test */
    public function owner_filter_returns_only_projects_for_that_owner(): void
    {
        $otherOwner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Other Owner', 'email' => 'other@example.com', 'password' => bcrypt('password'),
        ]);

        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Mine', 'owner_id' => $this->tenantOwner->id]);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Theirs', 'owner_id' => $otherOwner->id]);

        $response = $this->filter(['owner_id' => $otherOwner->id]);

        $response->assertOk();
        $names = $response->viewData('projects')->pluck('name')->all();
        $this->assertSame(['Theirs'], $names);
    }

    /** @test */
    public function status_filter_returns_only_matching_status(): void
    {
        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Active One', 'status' => Project::STATUS_ACTIVE]);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Draft One', 'status' => Project::STATUS_DRAFT]);

        $response = $this->filter(['status' => Project::STATUS_ACTIVE]);

        $response->assertOk();
        $names = $response->viewData('projects')->pluck('name')->all();
        $this->assertSame(['Active One'], $names);
    }

    /** @test */
    public function priority_filter_returns_only_matching_priority(): void
    {
        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Critical One', 'priority' => Project::PRIORITY_CRITICAL]);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Low One', 'priority' => Project::PRIORITY_LOW]);

        $response = $this->filter(['priority' => Project::PRIORITY_CRITICAL]);

        $response->assertOk();
        $names = $response->viewData('projects')->pluck('name')->all();
        $this->assertSame(['Critical One'], $names);
    }

    /** @test */
    public function start_date_filter_returns_projects_starting_on_or_after_the_given_date(): void
    {
        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Early', 'start_date' => '2026-01-01']);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Late', 'start_date' => '2026-03-01']);

        $response = $this->filter(['start_date' => '2026-02-01']);

        $response->assertOk();
        $names = $response->viewData('projects')->pluck('name')->all();
        $this->assertSame(['Late'], $names);
    }

    /** @test */
    public function end_date_filter_returns_projects_ending_on_or_before_the_given_date(): void
    {
        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Ends Early', 'start_date' => '2026-01-01', 'end_date' => '2026-01-15']);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Ends Late', 'start_date' => '2026-01-01', 'end_date' => '2026-04-15']);

        $response = $this->filter(['end_date' => '2026-02-01']);

        $response->assertOk();
        $names = $response->viewData('projects')->pluck('name')->all();
        $this->assertSame(['Ends Early'], $names);
    }

    /** @test */
    public function multiple_filters_combine_with_and_semantics(): void
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Corp']);

        $this->createProject([
            'project_code' => 'PRJ-0001', 'name' => 'Match', 'customer_id' => $customer->id,
            'status' => Project::STATUS_ACTIVE, 'priority' => Project::PRIORITY_HIGH,
        ]);
        $this->createProject([
            'project_code' => 'PRJ-0002', 'name' => 'Wrong Status', 'customer_id' => $customer->id,
            'status' => Project::STATUS_DRAFT, 'priority' => Project::PRIORITY_HIGH,
        ]);
        $this->createProject([
            'project_code' => 'PRJ-0003', 'name' => 'Wrong Client', 'status' => Project::STATUS_ACTIVE,
            'priority' => Project::PRIORITY_HIGH,
        ]);

        $response = $this->filter([
            'client_id' => $customer->id,
            'status' => Project::STATUS_ACTIVE,
            'priority' => Project::PRIORITY_HIGH,
        ]);

        $response->assertOk();
        $names = $response->viewData('projects')->pluck('name')->all();
        $this->assertSame(['Match'], $names);
    }

    /** @test */
    public function filtering_combines_with_sorting(): void
    {
        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Zulu', 'status' => Project::STATUS_ACTIVE]);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Alpha', 'status' => Project::STATUS_ACTIVE]);
        $this->createProject(['project_code' => 'PRJ-0003', 'name' => 'Middle', 'status' => Project::STATUS_DRAFT]);

        $response = $this->filter(['status' => Project::STATUS_ACTIVE, 'sort' => 'name', 'direction' => 'asc']);

        $response->assertOk();
        $names = $response->viewData('projects')->pluck('name')->all();
        $this->assertSame(['Alpha', 'Zulu'], $names);
    }

    /** @test */
    public function filtering_combines_with_pagination(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->createProject([
                'project_code' => sprintf('PRJ-%04d', $i),
                'name' => 'Active ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'status' => Project::STATUS_ACTIVE,
            ]);
        }
        $this->createProject(['project_code' => 'PRJ-9999', 'name' => 'Draft One', 'status' => Project::STATUS_DRAFT]);

        $response = $this->filter(['status' => Project::STATUS_ACTIVE, 'sort' => 'name', 'direction' => 'asc', 'page' => 2]);

        $response->assertOk();
        $projects = $response->viewData('projects');
        $this->assertSame(2, $projects->currentPage());
        $this->assertSame(15, $projects->total());
        $response->assertSee('status=' . Project::STATUS_ACTIVE);
    }

    /** @test */
    public function reset_clears_every_filter(): void
    {
        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'One']);
        $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Two']);

        $response = $this->filter([]);

        $response->assertOk();
        $this->assertSame(2, $response->viewData('projects')->total());
    }

    /** @test */
    public function unknown_or_malformed_filter_values_are_ignored_rather_than_erroring(): void
    {
        $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Only Project']);

        $badPriority = $this->filter(['priority' => 'Not A Real Priority']);
        $badPriority->assertOk();
        $this->assertSame(0, $badPriority->viewData('projects')->total());

        $badClient = $this->filter(['client_id' => 999999]);
        $badClient->assertOk();
        $this->assertSame(0, $badClient->viewData('projects')->total());

        $badDate = $this->filter(['start_date' => 'not-a-date']);
        $badDate->assertOk();
        $this->assertSame(1, $badDate->viewData('projects')->total());
    }
}
