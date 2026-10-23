<?php

namespace Tests\Feature\Production;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\Machine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $userB;
    private WorkCenter $workCenterA;

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
            'role' => 'admin',
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

        $this->workCenterA = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'WC Assembly',
            'code' => 'WC-ASSY',
            'status' => 'active',
        ]);
    }

    public function test_tenant_isolation_on_machines(): void
    {
        // Machine in Tenant A
        Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Machine Tenant A',
            'code' => 'MCH-A',
            'status' => 'active',
        ]);

        // Machine in Tenant B
        $workCenterB = WorkCenter::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'WC B',
            'code' => 'WC-B-TEST',
            'status' => 'active',
        ]);

        Machine::create([
            'tenant_id' => $this->tenantB->id,
            'work_center_id' => $workCenterB->id,
            'name' => 'Machine Tenant B',
            'code' => 'MCH-B',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.machines.index'));

        $response->assertStatus(200);
        $response->assertSee('Machine Tenant A');
        $response->assertDontSee('Machine Tenant B');
    }

    public function test_machine_belongs_to_work_center_validation(): void
    {
        // Try to link machine to a non-existent work center
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.store'), [
                'work_center_id' => 9999, // Invalid
                'name' => 'Test Machine',
                'code' => 'MCH-ERR',
                'status' => 'active',
            ]);

        $response->assertSessionHasErrors(['work_center_id']);
    }

    public function test_successful_machine_creation(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.store'), [
                'work_center_id' => $this->workCenterA->id,
                'name' => 'Press Machine 1',
                'code' => 'MCH-PRESS-1',
                'machine_type' => 'Press',
                'status' => 'active',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_machines', [
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'code' => 'MCH-PRESS-1',
        ]);
    }

    public function test_bulk_activate_machines(): void
    {
        $m1 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Machine 1',
            'code' => 'MCH-1',
            'status' => 'inactive',
        ]);
        $m2 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Machine 2',
            'code' => 'MCH-2',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.bulk-action'), [
                'action' => 'activate',
                'ids' => [$m1->id, $m2->id],
            ]);

        $response->assertRedirect();
        $this->assertEquals('active', $m1->refresh()->status);
        $this->assertEquals('active', $m2->refresh()->status);
    }

    public function test_bulk_deactivate_machines(): void
    {
        $m1 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Machine 1',
            'code' => 'MCH-1',
            'status' => 'active',
        ]);
        $m2 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Machine 2',
            'code' => 'MCH-2',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.bulk-action'), [
                'action' => 'deactivate',
                'ids' => [$m1->id, $m2->id],
            ]);

        $response->assertRedirect();
        $this->assertEquals('inactive', $m1->refresh()->status);
        $this->assertEquals('inactive', $m2->refresh()->status);
    }

    public function test_bulk_maintenance_machines(): void
    {
        $m1 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Machine 1',
            'code' => 'MCH-1',
            'status' => 'active',
        ]);
        $m2 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Machine 2',
            'code' => 'MCH-2',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.bulk-action'), [
                'action' => 'maintenance',
                'ids' => [$m1->id, $m2->id],
            ]);

        $response->assertRedirect();
        $this->assertEquals('maintenance', $m1->refresh()->status);
        $this->assertEquals('maintenance', $m2->refresh()->status);
    }

    public function test_bulk_delete_machines(): void
    {
        $m1 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Machine 1',
            'code' => 'MCH-1',
            'status' => 'active',
        ]);
        $m2 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Machine 2',
            'code' => 'MCH-2',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.bulk-action'), [
                'action' => 'delete',
                'ids' => [$m1->id, $m2->id],
            ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('production_machines', ['id' => $m1->id]);
        $this->assertSoftDeleted('production_machines', ['id' => $m2->id]);
    }

    public function test_machine_detail_page_renders_basic_details_and_redirects_breakdown_to_maintenance_dashboard(): void
    {
        $machine = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Stamping Press 01',
            'code' => 'STAMP-01',
            'machine_type' => 'Mechanical Press',
            'manufacturer' => 'Komatsu',
            'model_number' => 'OB-80',
            'capacity' => 120.5,
            'status' => 'active',
            'installation_date' => now()->subYears(2),
            'maintenance_status' => 'Standard operating check completed',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.mes.machines.show', $machine->id));

        $response->assertStatus(200);
        $response->assertSee('Stamping Press 01');
        $response->assertSee('STAMP-01');
        $response->assertSee('Mechanical Press');
        $response->assertSee('Komatsu');
        $response->assertSee('OB-80');
        $response->assertSee('120.50 Units/Hr');
        $response->assertSee('Standard operating check completed');

        // Verify "Report Breakdown" button redirects to maintenance dashboard
        $response->assertSee(route('production.maintenance.dashboard'));
        $response->assertSee('Report Breakdown');

        // Verify duplicate state override and downtime start forms are removed
        $response->assertDontSee(route('production.mes.machines.override-state'));
        $response->assertDontSee(route('production.mes.downtime.start'));
    }

    public function test_machine_detail_page_syncs_and_displays_pm_schedule_due_dates(): void
    {
        $machine = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name' => 'Grinding Station 01',
            'code' => 'GRIND-01',
            'status' => 'active',
        ]);

        \App\Domains\Production\Models\ProductionPmSchedule::create([
            'tenant_id' => $this->tenantA->id,
            'machine_id' => $machine->id,
            'name' => 'Bi-Weekly Dust Collector Filter Cleaning',
            'code' => 'PM-FIN-01-BW',
            'maintenance_type' => 'preventive',
            'frequency_type' => 'weeks',
            'frequency_value' => 2,
            'last_completed_date' => '2026-09-28',
            'next_due_date' => '2026-10-12',
            'priority' => 'medium',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.mes.machines.show', $machine->id));

        $response->assertStatus(200);
        $response->assertSee('2026-09-28');
        $response->assertSee('2026-10-12');
        $response->assertSee('PM-FIN-01-BW');
        $response->assertDontSee('Not scheduled');

        // Verify machine record in DB was synchronized
        $machine->refresh();
        $this->assertEquals('2026-10-12', $machine->next_maintenance_due_date->toDateString());
        $this->assertEquals('2026-09-28', $machine->last_maintenance_date->toDateString());
    }
}
