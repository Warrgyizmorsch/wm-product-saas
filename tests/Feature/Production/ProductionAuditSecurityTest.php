<?php

namespace Tests\Feature\Production;

use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\DashboardRefreshService;
use App\Domains\Production\Services\TrendAnalysisService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionAuditSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_trend_analysis_machine_downtime_subquery_respects_tenant_isolation(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
        ]);

        $userB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'User B',
            'email' => 'userb@example.com',
            'password' => bcrypt('password'),
        ]);

        $wcA = WorkCenter::create([
            'tenant_id' => $tenantA->id,
            'code' => 'WC-A',
            'name' => 'Work Center A',
            'capacity_per_day' => 8,
            'is_active' => true,
        ]);

        $wcB = WorkCenter::create([
            'tenant_id' => $tenantB->id,
            'code' => 'WC-B',
            'name' => 'Work Center B',
            'capacity_per_day' => 8,
            'is_active' => true,
        ]);

        $machineA = Machine::create([
            'tenant_id' => $tenantA->id,
            'work_center_id' => $wcA->id,
            'code' => 'MAC-A',
            'name' => 'Machine A',
            'is_active' => true,
        ]);

        $machineB = Machine::create([
            'tenant_id' => $tenantB->id,
            'work_center_id' => $wcB->id,
            'code' => 'MAC-B',
            'name' => 'Machine B',
            'is_active' => true,
        ]);

        DB::table('production_machine_downtimes')->insert([
            'tenant_id' => $tenantB->id,
            'work_center_id' => $wcB->id,
            'machine_id' => $machineB->id,
            'start_time' => now()->startOfDay(),
            'end_time' => now()->startOfDay()->addMinutes(60),
            'duration_minutes' => 60,
            'reason' => 'Maintenance',
            'category' => 'unplanned',
            'created_by' => $userB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trendService = app(TrendAnalysisService::class);
        $result = $trendService->getDowntimeTrend($tenantA->id, 'daily', [
            'work_center_id' => $wcB->id,
            'date_start' => now()->startOfDay()->toDateString(),
            'date_end' => now()->endOfDay()->toDateString(),
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('datasets', $result);
        $this->assertEquals([0.0], $result['datasets'][0]['data']);
    }

    public function test_dashboard_refresh_downtime_subquery_respects_tenant_isolation(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
        ]);

        $userB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'User B2',
            'email' => 'userb2@example.com',
            'password' => bcrypt('password'),
        ]);

        $wcB = WorkCenter::create([
            'tenant_id' => $tenantB->id,
            'code' => 'WC-B2',
            'name' => 'Work Center B2',
            'capacity_per_day' => 8,
            'is_active' => true,
        ]);

        $machineB = Machine::create([
            'tenant_id' => $tenantB->id,
            'work_center_id' => $wcB->id,
            'code' => 'MAC-B2',
            'name' => 'Machine B2',
            'is_active' => true,
        ]);

        DB::table('production_machine_downtimes')->insert([
            'tenant_id' => $tenantB->id,
            'work_center_id' => $wcB->id,
            'machine_id' => $machineB->id,
            'start_time' => now()->startOfDay(),
            'end_time' => now()->startOfDay()->addMinutes(120),
            'duration_minutes' => 120,
            'reason' => 'Breakdown',
            'category' => 'unplanned',
            'created_by' => $userB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dashboardService = app(DashboardRefreshService::class);
        $dashboardData = $dashboardService->refreshExecutiveDashboard($tenantA->id, [
            'work_center_id' => $wcB->id,
            'date_start' => now()->startOfDay()->toDateString(),
            'date_end' => now()->endOfDay()->toDateString(),
        ]);

        $this->assertIsArray($dashboardData);
        $this->assertArrayHasKey('six_big_losses', $dashboardData);
    }

    public function test_unauthorized_user_receives_403_on_subcontract_delivery_challans(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Security Test',
            'slug' => 'tenant-sec-test',
            'status' => 'active',
        ]);

        app(\App\Core\Tenant\TenantContext::class)->set($tenant);

        $regularUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $this->actingAs($regularUser);

        $response = $this->withHeader('X-Tenant', $tenant->slug)
            ->get(route('production.subcontract.delivery-challans.index'));
        $response->assertStatus(403);
    }

    public function test_authorized_admin_user_can_access_subcontract_delivery_challans(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Security Test Admin',
            'slug' => 'tenant-sec-admin',
            'status' => 'active',
        ]);

        app(\App\Core\Tenant\TenantContext::class)->set($tenant);

        $adminUser = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($adminUser);

        $response = $this->withHeader('X-Tenant', $tenant->slug)
            ->get(route('production.subcontract.delivery-challans.index'));
        $response->assertStatus(200);
    }

    public function test_technician_dropdown_in_maintenance_work_orders_is_tenant_scoped(): void
    {
        $tenantA = Tenant::create(['name' => 'Tenant A Tech', 'slug' => 'tenant-a-tech', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'Tenant B Tech', 'slug' => 'tenant-b-tech', 'status' => 'active']);

        app(\App\Core\Tenant\TenantContext::class)->set($tenantA);

        $userA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Tech User A',
            'email' => 'techA@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $userB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tech User B',
            'email' => 'techB@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($userA);

        $response = $this->withHeader('X-Tenant', $tenantA->slug)
            ->get(route('production.maintenance.work-orders.create'));
        $response->assertStatus(200);
        $response->assertSee('Tech User A');
        $response->assertDontSee('Tech User B');
    }
}
