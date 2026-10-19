<?php

namespace Tests\Feature\Production;

use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkCenterSectionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private WorkCenter $workCenterA1;
    private WorkCenter $workCenterA2;
    private WorkCenter $workCenterB;
    private ProductionShift $shift1;
    private ProductionShift $shift2;
    private ProductionShift $shift3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name'   => 'Tenant A',
            'slug'   => 'tenant-a',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Admin A',
            'email'     => 'admina@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->tenantB = Tenant::create([
            'name'   => 'Tenant B',
            'slug'   => 'tenant-b',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Admin B',
            'email'     => 'adminb@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        // Shifts for Tenant A
        $this->shift1 = ProductionShift::create([
            'tenant_id'     => $this->tenantA->id,
            'name'          => 'Morning Shift',
            'code'          => 'SH-MORN',
            'start_time'    => '06:00',
            'end_time'      => '14:00',
            'break_minutes' => 30,
            'hourly_labor_rate' => 25.50,
            'active'        => true,
        ]);

        $this->shift2 = ProductionShift::create([
            'tenant_id'     => $this->tenantA->id,
            'name'          => 'Evening Shift',
            'code'          => 'SH-EVE',
            'start_time'    => '14:00',
            'end_time'      => '22:00',
            'break_minutes' => 30,
            'hourly_labor_rate' => 28.00,
            'active'        => true,
        ]);

        $this->shift3 = ProductionShift::create([
            'tenant_id'     => $this->tenantA->id,
            'name'          => 'Night Shift',
            'code'          => 'SH-NIGHT',
            'start_time'    => '22:00',
            'end_time'      => '06:00',
            'break_minutes' => 30,
            'hourly_labor_rate' => 32.00,
            'active'        => true,
        ]);

        // Work Centers for Tenant A
        $this->workCenterA1 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'CNC Milling Dept',
            'code'      => 'WC-CNC',
            'status'    => 'active',
        ]);
        $this->workCenterA1->shifts()->sync([
            $this->shift1->id => ['tenant_id' => $this->tenantA->id],
            $this->shift2->id => ['tenant_id' => $this->tenantA->id],
        ]);

        $this->workCenterA2 = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Assembly Dept',
            'code'      => 'WC-ASSY',
            'status'    => 'active',
        ]);
        $this->workCenterA2->shifts()->sync([
            $this->shift1->id => ['tenant_id' => $this->tenantA->id],
            $this->shift2->id => ['tenant_id' => $this->tenantA->id],
            $this->shift3->id => ['tenant_id' => $this->tenantA->id],
        ]);

        // Work Center for Tenant B
        $this->workCenterB = WorkCenter::create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Tenant B Work Center',
            'code'      => 'WC-TB',
            'status'    => 'active',
        ]);
    }

    public function test_section_crud_and_tenant_isolation(): void
    {
        // 1. Create section under Tenant A
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'Rough Milling Section',
                'code'             => 'SEC-ROUGH',
                'workers_required' => 2,
                'description'      => 'Initial material roughing',
                'status'           => 'active',
                'shifts'           => [$this->shift1->id],
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA1->id));
        $this->assertDatabaseHas('production_work_center_sections', [
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'code'             => 'SEC-ROUGH',
            'workers_required' => 2,
        ]);

        $section = WorkCenterSection::where('code', 'SEC-ROUGH')->first();
        $this->assertNotNull($section);
        $this->assertTrue($section->shifts->contains($this->shift1));

        // 2. Tenant B user cannot view Tenant A's section (isolated by TenantScope -> 404)
        $responseB = $this->actingAs($this->adminB)
            ->withHeader('X-Tenant', 'tenant-b')
            ->get(route('production.work-center-sections.show', $section->id));

        $responseB->assertStatus(404);

        // 3. Update section
        $updateResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-center-sections.update', $section->id), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'Rough Milling Updated',
                'code'             => 'SEC-ROUGH',
                'workers_required' => 3,
                'status'           => 'active',
                'shifts'           => [$this->shift1->id, $this->shift2->id],
            ]);

        $updateResponse->assertRedirect();
        $section->refresh();
        $this->assertEquals('Rough Milling Updated', $section->name);
        $this->assertEquals(3, $section->workers_required);
        $this->assertCount(2, $section->shifts);

        // 4. Delete section
        $deleteResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->delete(route('production.work-center-sections.destroy', $section->id));

        $deleteResponse->assertRedirect();
        $this->assertSoftDeleted('production_work_center_sections', ['id' => $section->id]);
    }

    public function test_section_code_uniqueness_within_work_center_and_tenant(): void
    {
        // Create Section 1 in workCenterA1
        WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Section Alpha',
            'code'             => 'SEC-ALPHA',
            'workers_required' => 1,
            'status'           => 'active',
        ]);

        // Duplicate code in SAME work center should fail
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'Section Alpha Duplicate',
                'code'             => 'SEC-ALPHA',
                'workers_required' => 1,
                'status'           => 'active',
                'shifts'           => [$this->shift1->id],
            ]);

        $response->assertSessionHasErrors(['code']);

        // Same code in DIFFERENT work center (workCenterA2) in SAME tenant should SUCCEED
        $responseDiffWc = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'   => $this->workCenterA2->id,
                'name'             => 'Section Alpha In WC2',
                'code'             => 'SEC-ALPHA',
                'workers_required' => 1,
                'status'           => 'active',
                'shifts'           => [$this->shift1->id],
            ]);

        $responseDiffWc->assertRedirect();
        $this->assertDatabaseHas('production_work_center_sections', [
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA2->id,
            'code'           => 'SEC-ALPHA',
        ]);
    }

    public function test_section_shift_assignment_must_be_subset_of_parent_work_center(): void
    {
        // workCenterA1 only has shift1 and shift2 assigned. shift3 is NOT assigned to workCenterA1.
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'Invalid Shift Section',
                'code'             => 'SEC-INV',
                'workers_required' => 1,
                'status'           => 'active',
                'shifts'           => [$this->shift3->id], // not on parent WC
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('production_work_center_sections', [
            'code' => 'SEC-INV',
        ]);

        // Creating section without any shifts must fail validation
        $responseNoShifts = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'No Shift Section',
                'code'             => 'SEC-NOSHIFT',
                'workers_required' => 1,
                'status'           => 'active',
                'shifts'           => [],
            ]);

        $responseNoShifts->assertSessionHasErrors(['shifts']);
    }

    public function test_work_center_shift_removal_blocked_when_section_has_only_that_shift(): void
    {
        // Create section with shift1 only
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Precision Section',
            'code'             => 'SEC-PREC',
            'workers_required' => 1,
            'status'           => 'active',
        ]);
        $section->shifts()->sync([$this->shift1->id => ['tenant_id' => $this->tenantA->id]]);

        // Try to update workCenterA1 to remove shift1 (only keep shift2)
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-centers.update', $this->workCenterA1->id), [
                'name'   => $this->workCenterA1->name,
                'code'   => $this->workCenterA1->code,
                'status' => 'active',
                'shifts' => [$this->shift2->id], // removing shift1
            ]);

        $response->assertSessionHasErrors(['shifts']);
        // Verify shift1 was NOT removed from work center
        $this->workCenterA1->refresh();
        $this->assertTrue($this->workCenterA1->shifts->contains($this->shift1));
        // Verify section still has shift1
        $section->refresh();
        $this->assertTrue($section->shifts->contains($this->shift1));
    }

    public function test_work_center_shift_removal_warns_when_sections_retain_other_shifts(): void
    {
        // Create section with BOTH shift1 and shift2
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Flexible Section',
            'code'             => 'SEC-FLEX',
            'workers_required' => 1,
            'status'           => 'active',
        ]);
        $section->shifts()->sync([
            $this->shift1->id => ['tenant_id' => $this->tenantA->id],
            $this->shift2->id => ['tenant_id' => $this->tenantA->id],
        ]);

        // Try to remove shift1 from WC WITHOUT confirmation
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-centers.update', $this->workCenterA1->id), [
                'name'   => $this->workCenterA1->name,
                'code'   => $this->workCenterA1->code,
                'status' => 'active',
                'shifts' => [$this->shift2->id], // removing shift1
            ]);

        // Should receive warning on confirm_section_shift_removal
        $response->assertSessionHasErrors(['confirm_section_shift_removal']);
        $this->workCenterA1->refresh();
        $this->assertTrue($this->workCenterA1->shifts->contains($this->shift1));
        $section->refresh();
        $this->assertTrue($section->shifts->contains($this->shift1));
    }

    public function test_work_center_shift_removal_confirmed_cascades_atomically_to_sections(): void
    {
        // Create section with BOTH shift1 and shift2
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Flexible Section',
            'code'             => 'SEC-FLEX',
            'workers_required' => 1,
            'status'           => 'active',
        ]);
        $section->shifts()->sync([
            $this->shift1->id => ['tenant_id' => $this->tenantA->id],
            $this->shift2->id => ['tenant_id' => $this->tenantA->id],
        ]);

        // Update WC with confirm_section_shift_removal = true
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-centers.update', $this->workCenterA1->id), [
                'name'                          => $this->workCenterA1->name,
                'code'                          => $this->workCenterA1->code,
                'status'                        => 'active',
                'shifts'                        => [$this->shift2->id], // removing shift1
                'confirm_section_shift_removal' => true,
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA1->id));

        // Shift1 removed from Work Center
        $this->workCenterA1->refresh();
        $this->assertFalse($this->workCenterA1->shifts->contains($this->shift1));
        $this->assertTrue($this->workCenterA1->shifts->contains($this->shift2));

        // Shift1 atomically detached from Section
        $section->refresh();
        $this->assertFalse($section->shifts->contains($this->shift1));
        $this->assertTrue($section->shifts->contains($this->shift2));
    }

    public function test_machine_assignment_to_section_synchronizes_work_center_id(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Lathe Section',
            'code'             => 'SEC-LATHE',
            'workers_required' => 1,
            'status'           => 'active',
        ]);

        // Create machine specifying section_id. Notice even if someone attempts to pass a different work_center_id, Section dictates it!
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.store'), [
                'section_id'     => $section->id,
                'work_center_id' => $this->workCenterA2->id, // Disregarded, section dictates WC
                'name'           => 'CNC Lathe 1',
                'code'           => 'MCH-LATHE-1',
                'status'         => 'active',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('production_machines', [
            'tenant_id'      => $this->tenantA->id,
            'section_id'     => $section->id,
            'work_center_id' => $this->workCenterA1->id, // Synchronized with section's WC!
            'code'           => 'MCH-LATHE-1',
        ]);

        $machine = Machine::where('code', 'MCH-LATHE-1')->first();
        $this->assertEquals($section->id, $machine->section_id);
        $this->assertEquals($this->workCenterA1->id, $machine->work_center_id);

        // Verify legacy query continues to find machine via work_center_id
        $this->assertTrue($this->workCenterA1->machines->contains($machine));

        // Now move machine to a section under workCenterA2
        $section2 = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA2->id,
            'name'             => 'Assembly Line 1',
            'code'             => 'SEC-AL1',
            'workers_required' => 2,
            'status'           => 'active',
        ]);

        $updateResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.machines.update', $machine->id), [
                'section_id' => $section2->id,
                'name'       => 'CNC Lathe 1 Moved',
                'code'       => 'MCH-LATHE-1',
                'status'     => 'active',
            ]);

        $updateResponse->assertRedirect();
        $machine->refresh();
        $this->assertEquals($section2->id, $machine->section_id);
        $this->assertEquals($this->workCenterA2->id, $machine->work_center_id); // Synchronized to WC2!
    }

    public function test_machine_cannot_be_assigned_to_inactive_or_cross_tenant_section(): void
    {
        $inactiveSection = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Inactive Section',
            'code'             => 'SEC-INACT',
            'workers_required' => 1,
            'status'           => 'inactive',
        ]);

        $responseInactive = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.store'), [
                'section_id' => $inactiveSection->id,
                'name'       => 'Machine Inactive',
                'code'       => 'MCH-INACT',
                'status'     => 'active',
            ]);

        $responseInactive->assertSessionHas('error');

        // Cross-tenant section
        $sectionB = WorkCenterSection::create([
            'tenant_id'        => $this->tenantB->id,
            'work_center_id'   => $this->workCenterB->id,
            'name'             => 'Tenant B Section',
            'code'             => 'SEC-TB',
            'workers_required' => 1,
            'status'           => 'active',
        ]);

        $responseCross = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.store'), [
                'section_id' => $sectionB->id, // Tenant B section
                'name'       => 'Machine Cross Tenant',
                'code'       => 'MCH-CROSS',
                'status'     => 'active',
            ]);

        $responseCross->assertSessionHasErrors(['section_id']);
    }

    public function test_section_deletion_blocked_when_machines_assigned(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Welding Section',
            'code'             => 'SEC-WELD',
            'workers_required' => 1,
            'status'           => 'active',
        ]);

        $machine = Machine::create([
            'tenant_id'      => $this->tenantA->id,
            'section_id'     => $section->id,
            'work_center_id' => $this->workCenterA1->id,
            'name'           => 'TIG Welder 1',
            'code'           => 'MCH-WELD-1',
            'status'         => 'active',
        ]);

        // Attempting to delete section should be blocked
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->delete(route('production.work-center-sections.destroy', $section->id));

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('production_work_center_sections', ['id' => $section->id]);

        // Delete the machine first
        $machine->delete();

        // Now delete section
        $deleteSuccess = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->delete(route('production.work-center-sections.destroy', $section->id));

        $deleteSuccess->assertRedirect();
        $this->assertSoftDeleted('production_work_center_sections', ['id' => $section->id]);
    }

    public function test_shift_hourly_labor_rate_persisted_via_convert_to_base(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.shifts.store'), [
                'name'              => 'Special Weekend Shift',
                'code'              => 'SH-WKND',
                'start_time'        => '08:00',
                'end_time'          => '16:00',
                'break_minutes'     => 45,
                'hourly_labor_rate' => '45.7500',
                'active'            => true,
            ]);

        $response->assertRedirect(route('production.shifts.index'));

        $shift = ProductionShift::where('code', 'SH-WKND')->first();
        $this->assertNotNull($shift);
        $this->assertEquals(45.75, (float) $shift->hourly_labor_rate);
    }

    public function test_work_center_show_page_renders_sections_and_modals(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Primary Assembly Section',
            'code'             => 'SEC-ASM-1',
            'workers_required' => 4,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.show', $this->workCenterA1->id));

        $response->assertStatus(200);
        $response->assertSee('Primary Assembly Section');
        $response->assertSee('SEC-ASM-1');
        $response->assertSee('createSectionModal');
        $response->assertSee('editSectionModal_' . $section->id);
        $response->assertSee('deleteSectionModal_' . $section->id);
    }

    public function test_machine_create_and_edit_page_renders_section_dropdown(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Machining Bay 1',
            'code'             => 'BAY-1',
            'workers_required' => 2,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        $createResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.machines.create'));

        $createResponse->assertStatus(200);
        $createResponse->assertSee('section_id');
        $createResponse->assertSee('Machining Bay 1 (BAY-1)');

        $machine = Machine::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $section->id,
            'name'           => 'CNC Mill Alpha',
            'code'           => 'CNC-01',
            'status'         => 'active',
        ]);

        $editResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.machines.edit', $machine->id));

        $editResponse->assertStatus(200);
        $editResponse->assertSee('section_id');
        $editResponse->assertSee('CNC Mill Alpha');
        $editResponse->assertSee('Machining Bay 1 (BAY-1)');
    }

    public function test_shift_views_render_hourly_labor_rate(): void
    {
        $createResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.shifts.create'));

        $createResponse->assertStatus(200);
        $createResponse->assertSee('hourly_labor_rate');

        $this->shift1->update(['hourly_labor_rate' => 25.50]);

        $editResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.shifts.edit', $this->shift1->id));

        $editResponse->assertStatus(200);
        $editResponse->assertSee('hourly_labor_rate');

        $indexResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.shifts.index'));

        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Hourly Labor Rate');
    }
}
