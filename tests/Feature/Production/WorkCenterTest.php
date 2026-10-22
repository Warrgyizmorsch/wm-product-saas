<?php

namespace Tests\Feature\Production;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Production\Models\WorkCenter;
use Database\Seeders\RbacSeeder;
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

        $this->seed(RbacSeeder::class);

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
                'status' => 'active',
                'capacity_per_hour' => 100,
                'efficiency_percentage' => 100.0,
                'cost_per_hour' => 50.00,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_work_centers', [
            'tenant_id' => $this->tenantA->id,
            'code' => 'WC-LASER',
        ]);
    }

    public function test_work_center_creation_with_shifts(): void
    {
        $shift = \App\Domains\Production\Models\ProductionShift::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Morning Shift',
            'code' => 'MORN',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break_minutes' => 30,
            'active' => true,
        ]);

        $response = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-centers.store'), [
                'name' => 'CNC Station',
                'code' => 'WC-CNC',
                'work_center_type' => 'machining',
                'status' => 'active',
                'efficiency_percentage' => 100.0,
                'cost_per_hour' => 50.00,
            ]);

        $response->assertRedirect();
        
        $wc = WorkCenter::where('code', 'WC-CNC')->firstOrFail();
        $this->assertCount(0, $wc->getEffectiveShifts());

        // Create section and assign shift
        $section = \App\Domains\Production\Models\WorkCenterSection::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $wc->id,
            'name' => 'CNC Milling Section',
            'code' => 'SEC-CNC-1',
            'status' => 'active',
        ]);
        $section->shifts()->attach($shift->id, [
            'tenant_id' => $this->tenantA->id,
            'active' => true,
        ]);

        $effectiveShifts = $wc->getEffectiveShifts();
        $this->assertCount(1, $effectiveShifts);
        $this->assertEquals('Morning Shift', $effectiveShifts->first()->name);
        $this->assertDatabaseHas('production_section_shifts', [
            'section_id' => $section->id,
            'shift_id' => $shift->id,
            'tenant_id' => $this->tenantA->id,
            'active' => true,
        ]);
    }

    public function test_work_center_update_with_shifts(): void
    {
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Assembly Area',
            'code' => 'WC-ASSY',
            'status' => 'active',
            'efficiency_percentage' => 100.0,
            'cost_per_hour' => 20.00,
        ]);

        $shift = \App\Domains\Production\Models\ProductionShift::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Night Shift',
            'code' => 'NIGHT',
            'start_time' => '16:00:00',
            'end_time' => '00:00:00',
            'break_minutes' => 45,
            'active' => true,
        ]);

        $response = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-centers.update', $wc->id), [
                'name' => 'Assembly Area Updated',
                'code' => 'WC-ASSY',
                'status' => 'active',
                'efficiency_percentage' => 95.0,
                'cost_per_hour' => 25.00,
            ]);

        $response->assertRedirect();
        
        $wc->refresh();
        $this->assertEquals('Assembly Area Updated', $wc->name);
        $this->assertCount(0, $wc->getEffectiveShifts());

        // Assign shift to section
        $section = \App\Domains\Production\Models\WorkCenterSection::create([
            'tenant_id' => $this->tenantA->id,
            'work_center_id' => $wc->id,
            'name' => 'Assembly Section',
            'code' => 'SEC-ASSY-1',
            'status' => 'active',
        ]);
        $section->shifts()->attach($shift->id, [
            'tenant_id' => $this->tenantA->id,
            'active' => true,
        ]);

        $effectiveShifts = $wc->getEffectiveShifts();
        $this->assertCount(1, $effectiveShifts);
        $this->assertEquals('Night Shift', $effectiveShifts->first()->name);
    }

    public function test_bulk_activate_work_centers(): void
    {
        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'WC 1',
            'code' => 'WC-1',
            'status' => 'inactive',
        ]);
        $wc2 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'WC 2',
            'code' => 'WC-2',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-centers.bulk-action'), [
                'action' => 'activate',
                'ids' => [$wc1->id, $wc2->id],
            ]);

        $response->assertRedirect();
        $this->assertEquals('active', $wc1->refresh()->status);
        $this->assertEquals('active', $wc2->refresh()->status);
    }

    public function test_bulk_deactivate_work_centers(): void
    {
        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'WC 1',
            'code' => 'WC-1',
            'status' => 'active',
        ]);
        $wc2 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'WC 2',
            'code' => 'WC-2',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-centers.bulk-action'), [
                'action' => 'deactivate',
                'ids' => [$wc1->id, $wc2->id],
            ]);

        $response->assertRedirect();
        $this->assertEquals('inactive', $wc1->refresh()->status);
        $this->assertEquals('inactive', $wc2->refresh()->status);
    }

    public function test_bulk_delete_work_centers(): void
    {
        $wc1 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'WC 1',
            'code' => 'WC-1',
            'status' => 'active',
        ]);
        $wc2 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'WC 2',
            'code' => 'WC-2',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-centers.bulk-action'), [
                'action' => 'delete',
                'ids' => [$wc1->id, $wc2->id],
            ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('production_work_centers', ['id' => $wc1->id]);
        $this->assertSoftDeleted('production_work_centers', ['id' => $wc2->id]);
    }

    public function test_create_and_edit_forms_do_not_contain_capacity_efficiency_and_cost_fields(): void
    {
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Station',
            'code' => 'WC-TEST-FORM',
            'status' => 'active',
        ]);

        // Check Create form
        $createResponse = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.create'));

        $createResponse->assertStatus(200);
        $createResponse->assertDontSee('name="capacity_per_hour"', false);
        $createResponse->assertDontSee('name="efficiency_percentage"', false);
        $createResponse->assertDontSee('name="cost_per_hour"', false);
        $createResponse->assertSee('name="overhead_rate"', false);

        // Check Edit form
        $editResponse = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.edit', $wc->id));

        $editResponse->assertStatus(200);
        $editResponse->assertDontSee('name="capacity_per_hour"', false);
        $editResponse->assertDontSee('name="efficiency_percentage"', false);
        $editResponse->assertDontSee('name="cost_per_hour"', false);
        $editResponse->assertSee('name="overhead_rate"', false);
    }

    public function test_manual_values_cannot_override_work_center_fields(): void
    {
        // 1. On store, submitting manual values does not save manual capacity or cost
        $response = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-centers.store'), [
                'name' => 'Manual Override Attempt WC',
                'code' => 'WC-OVERRIDE',
                'status' => 'active',
                'capacity_per_hour' => 999.00,
                'efficiency_percentage' => 45.00,
                'cost_per_hour' => 888.00,
                'overhead_rate' => 12.50,
            ]);

        $response->assertRedirect();

        $wc = WorkCenter::where('code', 'WC-OVERRIDE')->firstOrFail();
        // capacity_per_hour should be null (not 999)
        $this->assertNull($wc->capacity_per_hour);
        // cost_per_hour should be 0.0000 (not 888)
        $this->assertEquals(0.0, (float) $wc->cost_per_hour);
        // overhead_rate should be saved correctly
        $this->assertEquals(12.50, (float) $wc->overhead_rate);

        // 2. Set derived values on the work center
        $wc->update([
            'capacity_per_hour' => 75.0,
            'cost_per_hour' => 40.0,
        ]);

        // 3. On update, submitting manual values does not overwrite existing derived values
        $updateResponse = $this->actingAs($this->engineerA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-centers.update', $wc->id), [
                'name' => 'Manual Override Attempt WC Updated',
                'code' => 'WC-OVERRIDE',
                'status' => 'active',
                'capacity_per_hour' => 123.00,
                'cost_per_hour' => 999.00,
            ]);

        $updateResponse->assertRedirect();

        $wc->refresh();
        $this->assertEquals('Manual Override Attempt WC Updated', $wc->name);
        $this->assertEquals(75.0, (float) $wc->capacity_per_hour);
        $this->assertEquals(40.0, (float) $wc->cost_per_hour);
    }

    public function test_work_center_detail_page_initial_display_for_newly_created_work_center(): void
    {
        // Newly created work center with no section or assignment data
        $wc = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Brand New WC',
            'code' => 'WC-NEW-EMPTY',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.show', $wc->id));

        $response->assertStatus(200);

        // Efficiency should display "Cannot be determined"
        $response->assertSee('Cannot be determined');

        // It must display Cannot be determined instead of presenting 0% or misleading percentage
        $response->assertSee('Cannot be determined');
        $response->assertDontSee('>0%<', false);
        $response->assertDontSee('>100%<', false);

        // Operating Cost Structure: single Labor Cost / Hr field is displayed, redundant Cost Per Hour is removed
        $response->assertSee(__('production.wc_labor_cost_per_hour'));
        $response->assertSee(__('production.overhead_cost_rate'));
        $response->assertDontSee('Direct Labor / Machine rate');

        // Index page: displays Work Center Labor Cost / Hr as single labor cost column
        $indexResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee(__('production.wc_labor_cost_per_hour'));
    }
}

