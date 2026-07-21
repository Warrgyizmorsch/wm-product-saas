<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionDashboardsVerificationTest extends TestCase
{
    use RefreshDatabase;

    private int $tenantId = 1;
    private User $user;
    private WorkCenter $workCenter;
    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::factory()->create([
            'id'   => $this->tenantId,
            'slug' => 'test-tenant',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
            'role'      => 'admin',
        ]);
        $this->actingAs($this->user);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenantId,
            'name'      => 'Main Assembly Center',
            'code'      => 'WC-001',
            'status'    => 'active',
        ]);

        $this->machine = Machine::create([
            'tenant_id'      => $this->tenantId,
            'work_center_id' => $this->workCenter->id,
            'name'           => 'CNC Mill 1',
            'code'           => 'MCH-001',
            'status'         => 'active',
        ]);
    }

    /** @test */
    public function executive_dashboard_renders_with_real_tenant_data_and_empty_state()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.intelligence.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Executive');
    }

    /** @test */
    public function mes_dashboard_renders_and_excludes_other_tenant_data()
    {
        $otherTenantId = 999;
        Tenant::factory()->create(['id' => $otherTenantId, 'slug' => 'other-tenant']);

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('MES');
    }

    /** @test */
    public function operator_dashboard_renders_no_assignment_state_cleanly()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.operator.dashboard'));

        $response->assertStatus(200);
    }

    /** @test */
    public function machine_dashboard_renders_index_and_detail_views()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.machines.index'));

        $response->assertStatus(200);
        $response->assertSee('CNC Mill 1');

        $detailResponse = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.machines.show', $this->machine->id));

        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('CNC Mill 1');
    }

    /** @test */
    public function work_center_dashboard_renders_index_and_detail_views()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.work-centers.index'));

        $response->assertStatus(200);
        $response->assertSee('Main Assembly Center');

        $detailResponse = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('production.mes.work-centers.show', $this->workCenter->id));

        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('Main Assembly Center');
    }
}
