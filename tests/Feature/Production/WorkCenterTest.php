<?php

namespace Tests\Feature\Production;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Production\Models\WorkCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkCenterTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $engineerA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin A',
            'email' => 'admina@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin', // maps to all permissions
        ]);

        $this->engineerA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Engineer A',
            'email' => 'engineera@example.com',
            'password' => bcrypt('password'),
            'role' => 'production_engineer', // can create/update
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'User B',
            'email' => 'userb@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_tenant_isolation_on_work_centers(): void
    {
        // Create work center in Tenant A
        WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'WC Tenant A',
            'code' => 'WC-A',
            'status' => 'active',
        ]);

        // Create work center in Tenant B
        WorkCenter::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'WC Tenant B',
            'code' => 'WC-B',
            'status' => 'active',
        ]);

        // Login as Tenant A user and fetch list
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.index'));

        $response->assertStatus(200);
        $response->assertSee('WC Tenant A');
        $response->assertDontSee('WC Tenant B');
    }

    public function test_create_work_center_validation_and_uniqueness(): void
    {
        // Try creating with existing code
        WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Original WC',
            'code' => 'WC-DUPLICATE',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-centers.store'), [
                'name' => 'New Duplicate WC',
                'code' => 'WC-DUPLICATE',
                'work_center_type' => 'assembly',
                'status' => 'active',
                'efficiency_percentage' => 95.0,
                'cost_per_hour' => 15.00,
            ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_successful_work_center_creation(): void
    {
        $response = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-centers.store'), [
                'name' => 'Laser Cut Station',
                'code' => 'WC-LASER',
                'work_center_type' => 'machining',
                'department_name' => 'Fabrication',
                'status' => 'active',
                'capacity_per_hour' => 100,
                'efficiency_percentage' => 100.0,
                'cost_per_hour' => 50.00,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_work_centers', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'WC-LASER',
            'department_name' => 'Fabrication',
        ]);
    }
}
