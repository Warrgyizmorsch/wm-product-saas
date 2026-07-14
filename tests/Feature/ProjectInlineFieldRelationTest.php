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

class ProjectInlineFieldRelationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $tenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);
        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant', 'slug' => 'other-tenant', 'status' => 'active', 'plan' => 'enterprise',
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
    public function client_can_be_updated_inline(): void
    {
        $project = $this->createProject();
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Co', 'status' => 'active']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'customer_id', 'value' => $customer->id]);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'customer_id', 'value' => $customer->id]);
        $this->assertSame($customer->id, $project->fresh()->customer_id);
    }

    /** @test */
    public function client_can_be_cleared_inline(): void
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Co', 'status' => 'active']);
        $project = $this->createProject(['customer_id' => $customer->id]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'customer_id', 'value' => '']);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'customer_id', 'value' => null]);
        $this->assertNull($project->fresh()->customer_id);
    }

    /** @test */
    public function client_from_another_tenant_is_rejected(): void
    {
        $project = $this->createProject();
        $foreignCustomer = Customer::create(['tenant_id' => $this->otherTenant->id, 'name' => 'Foreign Co', 'status' => 'active']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'customer_id', 'value' => $foreignCustomer->id]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        $this->assertNull($project->fresh()->customer_id);
    }

    /** @test */
    public function project_owner_can_be_updated_inline(): void
    {
        $project = $this->createProject();
        $newOwner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'New Owner', 'email' => 'new-owner@example.com', 'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'owner_id', 'value' => $newOwner->id]);

        $response->assertOk()->assertJson(['ok' => true, 'field' => 'owner_id', 'value' => $newOwner->id]);
        $this->assertSame($newOwner->id, $project->fresh()->owner_id);
    }

    /** @test */
    public function project_owner_is_required(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'owner_id', 'value' => '']);

        $response->assertStatus(422);
        $this->assertSame($this->tenantOwner->id, $project->fresh()->owner_id);
    }

    /** @test */
    public function project_owner_from_another_tenant_is_rejected(): void
    {
        $project = $this->createProject();
        $foreignUser = User::create([
            'tenant_id' => $this->otherTenant->id, 'name' => 'Foreign User', 'email' => 'foreign@example.com', 'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'owner_id', 'value' => $foreignUser->id]);

        $response->assertStatus(422);
        $this->assertSame($this->tenantOwner->id, $project->fresh()->owner_id);
    }

    /** @test */
    public function project_manager_can_be_updated_and_cleared_inline(): void
    {
        $manager = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Manager', 'email' => 'manager@example.com', 'password' => bcrypt('password'),
        ]);
        $project = $this->createProject();

        $set = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'manager_id', 'value' => $manager->id]);

        $set->assertOk()->assertJson(['ok' => true, 'field' => 'manager_id', 'value' => $manager->id]);
        $this->assertSame($manager->id, $project->fresh()->manager_id);

        $clear = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'manager_id', 'value' => '']);

        $clear->assertOk()->assertJson(['ok' => true, 'field' => 'manager_id', 'value' => null]);
        $this->assertNull($project->fresh()->manager_id);
    }
}
