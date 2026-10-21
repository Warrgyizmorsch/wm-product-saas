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
            'active'        => true,
        ]);

        $this->shift2 = ProductionShift::create([
            'tenant_id'     => $this->tenantA->id,
            'name'          => 'Evening Shift',
            'code'          => 'SH-EVE',
            'start_time'    => '14:00',
            'end_time'      => '22:00',
            'break_minutes' => 30,
            'active'        => true,
        ]);

        $this->shift3 = ProductionShift::create([
            'tenant_id'     => $this->tenantA->id,
            'name'          => 'Night Shift',
            'code'          => 'SH-NIGHT',
            'start_time'    => '22:00',
            'end_time'      => '06:00',
            'break_minutes' => 30,
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
                'shift_workers'    => [$this->shift1->id => 2],
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
                'shift_workers'    => [
                    $this->shift1->id => 3,
                    $this->shift2->id => 3,
                ],
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
                'shift_workers'    => [$this->shift1->id => 1],
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
                'shift_workers'    => [$this->shift1->id => 1],
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
                'shift_workers'    => [$this->shift3->id => 1],
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

    public function test_shift_created_without_hourly_labor_rate(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.shifts.store'), [
                'name'              => 'Special Weekend Shift',
                'code'              => 'SH-WKND',
                'start_time'        => '08:00',
                'end_time'          => '16:00',
                'break_minutes'     => 45,
                'active'            => true,
            ]);

        $response->assertRedirect(route('production.shifts.index'));

        $shift = ProductionShift::where('code', 'SH-WKND')->first();
        $this->assertNotNull($shift);
        $this->assertNull($shift->hourly_labor_rate);
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

        // 1. Check empty state when no eligible machines exist (and section has 0 machines)
        $responseEmpty = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.show', $this->workCenterA1->id));

        $responseEmpty->assertStatus(200);
        $responseEmpty->assertSee(__('production.no_eligible_machines_available'));
        // Section machine badge popover says 'No machines assigned' when count is 0
        $responseEmpty->assertSee(__('production.no_machines_assigned'));
        $responseEmpty->assertSee('deleteSectionModal_' . $section->id);

        // 2. Create an assigned machine and an unassigned eligible machine
        $machine = Machine::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $section->id,
            'name'           => 'Drill Press Alpha',
            'code'           => 'DP-01',
            'status'         => 'active',
        ]);

        $unassignedMachine = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Standalone Miller',
            'code'      => 'MILL-STAND',
            'status'    => 'active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.show', $this->workCenterA1->id));

        $response->assertStatus(200);
        $response->assertSee('Primary Assembly Section');
        $response->assertSee('SEC-ASM-1');
        $response->assertSee('createSectionModal');
        $response->assertSee('editSectionModal_' . $section->id);
        // Policy hides delete modal when machines are assigned
        $response->assertDontSee('deleteSectionModal_' . $section->id);

        // Requirement 10: Work Center details page no longer displays Assigned Equipment & Machines table or Add Machine action
        $response->assertDontSee('Assigned Equipment & Machines');
        $response->assertDontSee(route('production.machines.create', ['section_id' => $section->id]));

        // Requirement 11: Create and Edit Section modals display the Assigned Machines selector
        $response->assertSee('create_section_machine_ids');
        $response->assertSee('edit_section_machine_ids_' . $section->id);
        $response->assertSee('Assigned Machines');

        // Popover on hover displays assigned machine code and name
        $response->assertSee('data-bs-toggle="popover"', false);
        $response->assertSee('Drill Press Alpha');
        $response->assertSee('DP-01');
    }

    public function test_machine_create_and_edit_page_no_longer_renders_section_assignment(): void
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
        // Requirement 9: No Section Assignment dropdown
        $createResponse->assertDontSee('name="section_id"', false);
        $createResponse->assertDontSee('Section Assignment');

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
        // Requirement 9: No Section Assignment dropdown on edit
        $editResponse->assertDontSee('name="section_id"', false);
        $editResponse->assertDontSee('Section Assignment');
    }

    public function test_create_section_with_selected_eligible_machines(): void
    {
        // Two unassigned machines in Tenant A
        $m1 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Machine One',
            'code'      => 'MC-001',
            'status'    => 'active',
        ]);
        $m2 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Machine Two',
            'code'      => 'MC-002',
            'status'    => 'active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'Milling Cell',
                'code'             => 'SEC-MILL-1',
                'workers_required' => 2,
                'status'           => 'active',
                'shifts'           => [$this->shift1->id],
                'shift_workers'    => [$this->shift1->id => 2],
                'sync_machines'    => '1',
                'machine_ids'      => [$m1->id, $m2->id],
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA1->id));

        $section = WorkCenterSection::where('code', 'SEC-MILL-1')->first();
        $this->assertNotNull($section);

        $m1->refresh();
        $m2->refresh();
        // Requirement 1 & 7: Synchronizing section_id and work_center_id from section's parent work center
        $this->assertEquals($section->id, $m1->section_id);
        $this->assertEquals($this->workCenterA1->id, $m1->work_center_id);
        $this->assertEquals($section->id, $m2->section_id);
        $this->assertEquals($this->workCenterA1->id, $m2->work_center_id);
    }

    public function test_edit_section_to_add_and_remove_machines(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Cell A',
            'code'             => 'SEC-CA',
            'workers_required' => 1,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        $m1 = Machine::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $section->id,
            'name'           => 'Initial Machine',
            'code'           => 'MC-INIT',
            'status'         => 'active',
        ]);

        $m2 = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'New Machine',
            'code'      => 'MC-NEW',
            'status'    => 'active',
        ]);

        // Edit section: remove $m1, add $m2
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-center-sections.update', $section->id), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'Cell A Updated',
                'code'             => 'SEC-CA',
                'workers_required' => 1,
                'status'           => 'active',
                'shifts'           => [$this->shift1->id],
                'shift_workers'    => [$this->shift1->id => 1],
                'sync_machines'    => '1',
                'machine_ids'      => [$m2->id],
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA1->id));

        $m1->refresh();
        $m2->refresh();

        // Requirement 8: m1 removed from section has section_id = null, work_center_id = null
        $this->assertNull($m1->section_id);
        $this->assertNull($m1->work_center_id);

        // Requirement 2: m2 added to section
        $this->assertEquals($section->id, $m2->section_id);
        $this->assertEquals($this->workCenterA1->id, $m2->work_center_id);
    }

    public function test_preserving_existing_machine_assignments_when_omitted(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Preserve Cell',
            'code'             => 'SEC-PRES',
            'workers_required' => 1,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        $m1 = Machine::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA1->id,
            'section_id'     => $section->id,
            'name'           => 'Preserved Machine',
            'code'           => 'MC-PRES',
            'status'         => 'active',
        ]);

        // Update unrelated fields without sync_machines or machine_ids
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-center-sections.update', $section->id), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'Preserve Cell Renamed',
                'code'             => 'SEC-PRES',
                'workers_required' => 3,
                'status'           => 'active',
                'shifts'           => [$this->shift1->id],
                'shift_workers'    => [$this->shift1->id => 3],
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA1->id));

        $m1->refresh();
        // Requirement 3: Preserving existing machine assignments
        $this->assertEquals($section->id, $m1->section_id);
        $this->assertEquals($this->workCenterA1->id, $m1->work_center_id);
    }

    public function test_rejecting_machines_assigned_to_another_section(): void
    {
        $section1 = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Section 1',
            'code'             => 'SEC-S1',
            'workers_required' => 1,
            'status'           => 'active',
        ]);
        $section1->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        $otherSection = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA2->id,
            'name'             => 'Section 2',
            'code'             => 'SEC-S2',
            'workers_required' => 1,
            'status'           => 'active',
        ]);
        $otherSection->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        // Machine belongs to otherSection
        $machine = Machine::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA2->id,
            'section_id'     => $otherSection->id,
            'name'           => 'Other Section Machine',
            'code'           => 'MC-OTHER',
            'status'         => 'active',
        ]);

        // Attempting to assign $machine to section1 must fail validation
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-center-sections.update', $section1->id), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'Section 1',
                'code'             => 'SEC-S1',
                'workers_required' => 1,
                'status'           => 'active',
                'shifts'           => [$this->shift1->id],
                'shift_workers'    => [$this->shift1->id => 1],
                'sync_machines'    => '1',
                'machine_ids'      => [$machine->id],
            ]);

        // Requirement 4: Rejecting machines assigned to another Section
        $response->assertSessionHasErrors(['machine_ids']);
        $machine->refresh();
        $this->assertEquals($otherSection->id, $machine->section_id);
    }

    public function test_rejecting_cross_tenant_machine_ids(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Section A',
            'code'             => 'SEC-SA',
            'workers_required' => 1,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        // Machine in Tenant B
        $machineTenantB = Machine::create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Tenant B Machine',
            'code'      => 'MC-TB-01',
            'status'    => 'active',
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'Section Cross',
                'code'             => 'SEC-CROSS',
                'workers_required' => 1,
                'status'           => 'active',
                'shifts'           => [$this->shift1->id],
                'shift_workers'    => [$this->shift1->id => 1],
                'sync_machines'    => '1',
                'machine_ids'      => [$machineTenantB->id],
            ]);

        // Requirement 5: Rejecting cross-tenant machine IDs
        $response->assertSessionHasErrors(['machine_ids.0']);
    }

    public function test_rejecting_decommissioned_machines(): void
    {
        $decomMachine = Machine::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Decommissioned Machine',
            'code'      => 'MC-DECOM',
            'status'    => Machine::STATUS_DECOMMISSIONED,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'   => $this->workCenterA1->id,
                'name'             => 'Section Decom',
                'code'             => 'SEC-DEC',
                'workers_required' => 1,
                'status'           => 'active',
                'shifts'           => [$this->shift1->id],
                'shift_workers'    => [$this->shift1->id => 1],
                'sync_machines'    => '1',
                'machine_ids'      => [$decomMachine->id],
            ]);

        // Requirement 6: Rejecting decommissioned machines
        $response->assertSessionHasErrors(['machine_ids']);
    }

    public function test_machine_create_and_edit_without_section_id_supports_unassigned_workflow(): void
    {
        // 1. Create machine without section_id
        $createResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.store'), [
                'name'   => 'Standalone Lathe',
                'code'   => 'MCH-STAND',
                'status' => 'active',
            ]);

        $createResponse->assertRedirect();
        $this->assertDatabaseHas('production_machines', [
            'tenant_id'      => $this->tenantA->id,
            'name'           => 'Standalone Lathe',
            'code'           => 'MCH-STAND',
            'section_id'     => null,
            'work_center_id' => null,
        ]);

        $machine = Machine::where('code', 'MCH-STAND')->first();
        $this->assertNull($machine->section_id);
        $this->assertNull($machine->work_center_id);

        // Assign to a section
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA1->id,
            'name'             => 'Section Lathes',
            'code'             => 'SEC-LATHES',
            'workers_required' => 1,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        $machine->update([
            'section_id'     => $section->id,
            'work_center_id' => $this->workCenterA1->id,
        ]);

        // 2. Edit machine form without section_id preserves existing section_id and work_center_id
        $editResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.machines.update', $machine->id), [
                'name'   => 'Standalone Lathe Renamed',
                'code'   => 'MCH-STAND',
                'status' => 'active',
            ]);

        $editResponse->assertRedirect();
        $machine->refresh();
        $this->assertEquals('Standalone Lathe Renamed', $machine->name);
        $this->assertEquals($section->id, $machine->section_id);
        $this->assertEquals($this->workCenterA1->id, $machine->work_center_id);
    }

    public function test_shift_views_do_not_render_hourly_labor_rate(): void
    {
        $createResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.shifts.create'));

        $createResponse->assertStatus(200);
        $createResponse->assertDontSee('hourly_labor_rate');

        $editResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.shifts.edit', $this->shift1->id));

        $editResponse->assertStatus(200);
        $editResponse->assertDontSee('hourly_labor_rate');

        $indexResponse = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.shifts.index'));

        $indexResponse->assertStatus(200);
        $indexResponse->assertDontSee('Hourly Labor Rate');
    }

    public function test_create_section_with_manual_production_disabled_persists_defaults(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'             => $this->workCenterA1->id,
                'name'                       => 'Standard Machine Section',
                'code'                       => 'SEC-STD-MCH',
                'workers_required'           => 2,
                'status'                     => 'active',
                'shifts'                     => [$this->shift1->id],
                'shift_workers'              => [$this->shift1->id => 2],
                'supports_manual_production' => 0,
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA1->id));
        $this->assertDatabaseHas('production_work_center_sections', [
            'tenant_id'                  => $this->tenantA->id,
            'work_center_id'             => $this->workCenterA1->id,
            'code'                       => 'SEC-STD-MCH',
            'supports_manual_production' => false,
            'manual_capacity_per_hour'   => null,
        ]);
    }

    public function test_create_section_with_manual_production_enabled_and_valid_capacity(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'             => $this->workCenterA1->id,
                'name'                       => 'Manual Assembly Line',
                'code'                       => 'SEC-MAN-ASY',
                'workers_required'           => 3,
                'status'                     => 'active',
                'shifts'                     => [$this->shift1->id],
                'shift_workers'              => [$this->shift1->id => 3],
                'supports_manual_production' => 1,
                'manual_capacity_per_hour'   => 12.50,
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA1->id));
        $this->assertDatabaseHas('production_work_center_sections', [
            'tenant_id'                  => $this->tenantA->id,
            'work_center_id'             => $this->workCenterA1->id,
            'code'                       => 'SEC-MAN-ASY',
            'supports_manual_production' => true,
            'manual_capacity_per_hour'   => 12.5000,
        ]);

        $section = WorkCenterSection::where('code', 'SEC-MAN-ASY')->first();
        $this->assertTrue($section->supportsManualProduction());
        $this->assertEquals(12.5, (float) $section->manual_capacity_per_hour);
    }

    public function test_create_section_rejects_missing_zero_negative_nonnumeric_capacity_when_manual_enabled(): void
    {
        // 1. Missing capacity when manual production is enabled
        $resMissing = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'             => $this->workCenterA1->id,
                'name'                       => 'Invalid Manual Section',
                'code'                       => 'SEC-INV-1',
                'workers_required'           => 1,
                'status'                     => 'active',
                'shifts'                     => [$this->shift1->id],
                'shift_workers'              => [$this->shift1->id => 1],
                'supports_manual_production' => 1,
            ]);
        $resMissing->assertSessionHasErrors(['manual_capacity_per_hour']);

        // 2. Zero capacity
        $resZero = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'             => $this->workCenterA1->id,
                'name'                       => 'Invalid Manual Section',
                'code'                       => 'SEC-INV-2',
                'workers_required'           => 1,
                'status'                     => 'active',
                'shifts'                     => [$this->shift1->id],
                'shift_workers'              => [$this->shift1->id => 1],
                'supports_manual_production' => 1,
                'manual_capacity_per_hour'   => 0,
            ]);
        $resZero->assertSessionHasErrors(['manual_capacity_per_hour']);

        // 3. Negative capacity
        $resNeg = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'             => $this->workCenterA1->id,
                'name'                       => 'Invalid Manual Section',
                'code'                       => 'SEC-INV-3',
                'workers_required'           => 1,
                'status'                     => 'active',
                'shifts'                     => [$this->shift1->id],
                'shift_workers'              => [$this->shift1->id => 1],
                'supports_manual_production' => 1,
                'manual_capacity_per_hour'   => -5.25,
            ]);
        $resNeg->assertSessionHasErrors(['manual_capacity_per_hour']);

        // 4. Non-numeric capacity
        $resNonNum = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'             => $this->workCenterA1->id,
                'name'                       => 'Invalid Manual Section',
                'code'                       => 'SEC-INV-4',
                'workers_required'           => 1,
                'status'                     => 'active',
                'shifts'                     => [$this->shift1->id],
                'shift_workers'              => [$this->shift1->id => 1],
                'supports_manual_production' => 1,
                'manual_capacity_per_hour'   => 'ten-units',
            ]);
        $resNonNum->assertSessionHasErrors(['manual_capacity_per_hour']);
    }

    public function test_update_section_enables_manual_production(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'                  => $this->tenantA->id,
            'work_center_id'             => $this->workCenterA1->id,
            'name'                       => 'Packaging Section',
            'code'                       => 'SEC-PKG',
            'workers_required'           => 1,
            'status'                     => 'active',
            'supports_manual_production' => false,
            'manual_capacity_per_hour'   => null,
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-center-sections.update', $section->id), [
                'work_center_id'             => $this->workCenterA1->id,
                'name'                       => 'Packaging Section (Manual Converted)',
                'code'                       => 'SEC-PKG',
                'workers_required'           => 2,
                'status'                     => 'active',
                'shifts'                     => [$this->shift1->id],
                'shift_workers'              => [$this->shift1->id => 2],
                'supports_manual_production' => 1,
                'manual_capacity_per_hour'   => 25.0,
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA1->id));
        $section->refresh();
        $this->assertTrue($section->supports_manual_production);
        $this->assertEquals(25.0, (float) $section->manual_capacity_per_hour);
    }

    public function test_update_section_disables_manual_production_and_clears_stored_capacity(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'                  => $this->tenantA->id,
            'work_center_id'             => $this->workCenterA1->id,
            'name'                       => 'Custom Finishing Line',
            'code'                       => 'SEC-FIN-MAN',
            'workers_required'           => 3,
            'status'                     => 'active',
            'supports_manual_production' => true,
            'manual_capacity_per_hour'   => 18.0,
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-center-sections.update', $section->id), [
                'work_center_id'             => $this->workCenterA1->id,
                'name'                       => 'Custom Finishing Line (Converted to Automated)',
                'code'                       => 'SEC-FIN-MAN',
                'workers_required'           => 1,
                'status'                     => 'active',
                'shifts'                     => [$this->shift1->id],
                'shift_workers'              => [$this->shift1->id => 1],
                'supports_manual_production' => 0,
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA1->id));
        $section->refresh();
        $this->assertFalse($section->supports_manual_production);
        $this->assertNull($section->manual_capacity_per_hour);
    }

    public function test_stale_capacity_is_cleared_when_manual_production_is_disabled(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'                  => $this->tenantA->id,
            'work_center_id'             => $this->workCenterA1->id,
            'name'                       => 'Assembly Bench',
            'code'                       => 'SEC-BNCH',
            'workers_required'           => 2,
            'status'                     => 'active',
            'supports_manual_production' => false,
            'manual_capacity_per_hour'   => null,
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        // Submit supports_manual_production = 0, but provide a stale capacity value
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-center-sections.update', $section->id), [
                'work_center_id'             => $this->workCenterA1->id,
                'name'                       => 'Assembly Bench',
                'code'                       => 'SEC-BNCH',
                'workers_required'           => 2,
                'status'                     => 'active',
                'shifts'                     => [$this->shift1->id],
                'shift_workers'              => [$this->shift1->id => 2],
                'supports_manual_production' => 0,
                'manual_capacity_per_hour'   => 99.99, // Stale value submitted!
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA1->id));
        $section->refresh();
        $this->assertFalse($section->supports_manual_production);
        $this->assertNull($section->manual_capacity_per_hour);
    }

    public function test_by_work_center_api_returns_manual_production_metadata(): void
    {
        $sectionManual = WorkCenterSection::create([
            'tenant_id'                  => $this->tenantA->id,
            'work_center_id'             => $this->workCenterA1->id,
            'name'                       => 'Manual Section API',
            'code'                       => 'SEC-API-MAN',
            'workers_required'           => 2,
            'status'                     => 'active',
            'supports_manual_production' => true,
            'manual_capacity_per_hour'   => 15.0,
        ]);
        $sectionManual->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->getJson(route('production.work-center-sections.by-work-center', $this->workCenterA1->id));

        $response->assertStatus(200);
        $data = collect($response->json());
        $item = $data->firstWhere('id', $sectionManual->id);

        $this->assertNotNull($item);
        $this->assertTrue($item['supports_manual_production']);
        $this->assertEquals(15.0, (float) $item['manual_capacity_per_hour']);
    }

    public function test_work_center_show_page_renders_manual_production_elements(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'                  => $this->tenantA->id,
            'work_center_id'             => $this->workCenterA1->id,
            'name'                       => 'Bench Polishing Section',
            'code'                       => 'SEC-POLISH',
            'workers_required'           => 2,
            'status'                     => 'active',
            'supports_manual_production' => true,
            'manual_capacity_per_hour'   => 14.50,
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.show', $this->workCenterA1->id));

        $response->assertStatus(200);
        // Table badge
        $response->assertSee('Manual Production (14.50 units/hr)');
        // Form input and toggle
        $response->assertSee('manual-production-toggle');
        $response->assertSee('manual-capacity-input');
        $response->assertSee('Manual Capacity Per Hour');
    }

    public function test_create_section_with_multiple_shifts_and_different_worker_counts(): void
    {
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id'   => $this->workCenterA2->id,
                'name'             => 'Multi-Shift Cell',
                'code'             => 'SEC-MULTI-1',
                'status'           => 'active',
                'shifts'           => [$this->shift1->id, $this->shift2->id],
                'shift_workers'    => [
                    $this->shift1->id => 4,
                    $this->shift2->id => 6,
                ],
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA2->id));

        $section = WorkCenterSection::where('code', 'SEC-MULTI-1')->first();
        $this->assertNotNull($section);

        // Verify persistence in production_work_center_section_shifts pivot
        $this->assertDatabaseHas('production_work_center_section_shifts', [
            'tenant_id'        => $this->tenantA->id,
            'section_id'       => $section->id,
            'shift_id'         => $this->shift1->id,
            'workers_required' => 4,
        ]);
        $this->assertDatabaseHas('production_work_center_section_shifts', [
            'tenant_id'        => $this->tenantA->id,
            'section_id'       => $section->id,
            'shift_id'         => $this->shift2->id,
            'workers_required' => 6,
        ]);

        // Verify relationship loading
        $shifts = $section->shifts()->get();
        $this->assertCount(2, $shifts);
        $s1 = $shifts->firstWhere('id', $this->shift1->id);
        $s2 = $shifts->firstWhere('id', $this->shift2->id);
        $this->assertEquals(4, (int) $s1->pivot->workers_required);
        $this->assertEquals(6, (int) $s2->pivot->workers_required);
    }

    public function test_edit_individual_worker_counts(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA2->id,
            'name'             => 'Editable Cell',
            'code'             => 'SEC-EDIT-WC',
            'workers_required' => 2,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id, 'workers_required' => 2]);
        $section->shifts()->attach($this->shift2->id, ['tenant_id' => $this->tenantA->id, 'workers_required' => 3]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-center-sections.update', $section->id), [
                'work_center_id'   => $this->workCenterA2->id,
                'name'             => 'Editable Cell Updated',
                'code'             => 'SEC-EDIT-WC',
                'status'           => 'active',
                'shifts'           => [$this->shift1->id, $this->shift2->id],
                'shift_workers'    => [
                    $this->shift1->id => 5,
                    $this->shift2->id => 8,
                ],
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA2->id));

        $this->assertDatabaseHas('production_work_center_section_shifts', [
            'tenant_id'        => $this->tenantA->id,
            'section_id'       => $section->id,
            'shift_id'         => $this->shift1->id,
            'workers_required' => 5,
        ]);
        $this->assertDatabaseHas('production_work_center_section_shifts', [
            'tenant_id'        => $this->tenantA->id,
            'section_id'       => $section->id,
            'shift_id'         => $this->shift2->id,
            'workers_required' => 8,
        ]);
    }

    public function test_preserve_worker_counts_during_unrelated_section_updates(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA2->id,
            'name'             => 'Initial Section',
            'code'             => 'SEC-PRES-W',
            'workers_required' => 4,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id, 'workers_required' => 4]);
        $section->shifts()->attach($this->shift2->id, ['tenant_id' => $this->tenantA->id, 'workers_required' => 7]);

        // Submit update with same shifts and worker counts
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-center-sections.update', $section->id), [
                'work_center_id'   => $this->workCenterA2->id,
                'name'             => 'Renamed Section Preserved',
                'code'             => 'SEC-PRES-W',
                'description'      => 'Updated description',
                'status'           => 'active',
                'shifts'           => [$this->shift1->id, $this->shift2->id],
                'shift_workers'    => [
                    $this->shift1->id => 4,
                    $this->shift2->id => 7,
                ],
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA2->id));

        $this->assertDatabaseHas('production_work_center_section_shifts', [
            'section_id'       => $section->id,
            'shift_id'         => $this->shift1->id,
            'workers_required' => 4,
        ]);
        $this->assertDatabaseHas('production_work_center_section_shifts', [
            'section_id'       => $section->id,
            'shift_id'         => $this->shift2->id,
            'workers_required' => 7,
        ]);

        // Also test repository syncShifts directly without passing $shiftWorkers maintains existing pivot counts
        $repo = app(\App\Domains\Production\Repositories\WorkCenterSectionRepositoryInterface::class);
        $repo->syncShifts($section, [$this->shift1->id, $this->shift2->id]); // omitted shiftWorkers
        $this->assertDatabaseHas('production_work_center_section_shifts', [
            'section_id'       => $section->id,
            'shift_id'         => $this->shift1->id,
            'workers_required' => 4,
        ]);
    }

    public function test_adding_and_removing_shift_associations(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA2->id,
            'name'             => 'Dynamic Shift Section',
            'code'             => 'SEC-DYN-SH',
            'workers_required' => 2,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id, 'workers_required' => 3]);
        $section->shifts()->attach($this->shift2->id, ['tenant_id' => $this->tenantA->id, 'workers_required' => 5]);

        // Now remove shift1 and add shift3 (with 9 workers), keeping shift2 (with 5 workers)
        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.work-center-sections.update', $section->id), [
                'work_center_id'   => $this->workCenterA2->id,
                'name'             => 'Dynamic Shift Section',
                'code'             => 'SEC-DYN-SH',
                'status'           => 'active',
                'shifts'           => [$this->shift2->id, $this->shift3->id],
                'shift_workers'    => [
                    $this->shift2->id => 5,
                    $this->shift3->id => 9,
                ],
            ]);

        $response->assertRedirect(route('production.work-centers.show', $this->workCenterA2->id));

        // shift1 was detached
        $this->assertDatabaseMissing('production_work_center_section_shifts', [
            'section_id' => $section->id,
            'shift_id'   => $this->shift1->id,
        ]);
        // shift2 retained
        $this->assertDatabaseHas('production_work_center_section_shifts', [
            'section_id'       => $section->id,
            'shift_id'         => $this->shift2->id,
            'workers_required' => 5,
        ]);
        // shift3 attached
        $this->assertDatabaseHas('production_work_center_section_shifts', [
            'section_id'       => $section->id,
            'shift_id'         => $this->shift3->id,
            'workers_required' => 9,
        ]);
    }

    public function test_rejecting_missing_zero_negative_decimal_and_nonnumeric_worker_counts(): void
    {
        // 1. Missing shift_workers array entirely
        $resMissing = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id' => $this->workCenterA1->id,
                'name'           => 'Invalid Shift Section 1',
                'code'           => 'SEC-INV-S1',
                'status'         => 'active',
                'shifts'         => [$this->shift1->id],
            ]);
        $resMissing->assertSessionHasErrors(['shift_workers']);

        // 2. Missing worker count for a selected shift
        $resMissingForShift = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id' => $this->workCenterA2->id,
                'name'           => 'Invalid Shift Section 2',
                'code'           => 'SEC-INV-S2',
                'status'         => 'active',
                'shifts'         => [$this->shift1->id, $this->shift2->id],
                'shift_workers'  => [
                    $this->shift1->id => 3,
                    // shift2 missing
                ],
            ]);
        $resMissingForShift->assertSessionHasErrors(['shift_workers.' . $this->shift2->id]);

        // 3. Zero count
        $resZero = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id' => $this->workCenterA1->id,
                'name'           => 'Zero Shift Section',
                'code'           => 'SEC-ZERO-S',
                'status'         => 'active',
                'shifts'         => [$this->shift1->id],
                'shift_workers'  => [$this->shift1->id => 0],
            ]);
        $resZero->assertSessionHasErrors(['shift_workers.' . $this->shift1->id]);

        // 4. Negative count
        $resNegative = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id' => $this->workCenterA1->id,
                'name'           => 'Negative Shift Section',
                'code'           => 'SEC-NEG-S',
                'status'         => 'active',
                'shifts'         => [$this->shift1->id],
                'shift_workers'  => [$this->shift1->id => -2],
            ]);
        $resNegative->assertSessionHasErrors(['shift_workers.' . $this->shift1->id]);

        // 5. Decimal count
        $resDecimal = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id' => $this->workCenterA1->id,
                'name'           => 'Decimal Shift Section',
                'code'           => 'SEC-DEC-S',
                'status'         => 'active',
                'shifts'         => [$this->shift1->id],
                'shift_workers'  => [$this->shift1->id => 2.5],
            ]);
        $resDecimal->assertSessionHasErrors(['shift_workers.' . $this->shift1->id]);

        // 6. Non-numeric count
        $resNonNumeric = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id' => $this->workCenterA1->id,
                'name'           => 'NonNumeric Shift Section',
                'code'           => 'SEC-STR-S',
                'status'         => 'active',
                'shifts'         => [$this->shift1->id],
                'shift_workers'  => [$this->shift1->id => 'four'],
            ]);
        $resNonNumeric->assertSessionHasErrors(['shift_workers.' . $this->shift1->id]);
    }

    public function test_by_work_center_api_returns_saved_worker_counts_for_each_shift(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA2->id,
            'name'             => 'Prefilled Shift Section',
            'code'             => 'SEC-PREFILL',
            'workers_required' => 2,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id, 'workers_required' => 4]);
        $section->shifts()->attach($this->shift2->id, ['tenant_id' => $this->tenantA->id, 'workers_required' => 6]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->getJson(route('production.work-center-sections.by-work-center', $this->workCenterA2->id));

        $response->assertStatus(200);
        $data = collect($response->json());
        $sec = $data->firstWhere('id', $section->id);
        $this->assertNotNull($sec);
        $this->assertIsArray($sec['shifts']);

        $shifts = collect($sec['shifts']);
        $s1 = $shifts->firstWhere('id', $this->shift1->id);
        $s2 = $shifts->firstWhere('id', $this->shift2->id);

        $this->assertNotNull($s1);
        $this->assertNotNull($s2);
        $this->assertEquals(4, $s1['workers_required']);
        $this->assertEquals(6, $s2['workers_required']);
    }

    public function test_section_listing_displays_shift_alongside_worker_count_and_no_separate_workers_column(): void
    {
        $section = WorkCenterSection::create([
            'tenant_id'        => $this->tenantA->id,
            'work_center_id'   => $this->workCenterA2->id,
            'name'             => 'Listing Section Test',
            'code'             => 'SEC-LIST-TEST',
            'workers_required' => 2,
            'status'           => 'active',
        ]);
        $section->shifts()->attach($this->shift1->id, ['tenant_id' => $this->tenantA->id, 'workers_required' => 4]);
        $section->shifts()->attach($this->shift2->id, ['tenant_id' => $this->tenantA->id, 'workers_required' => 6]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.show', $this->workCenterA2->id));

        $response->assertStatus(200);
        // Displays each shift and its count
        $response->assertSee('Morning Shift');
        $response->assertSee('4 workers');
        $response->assertSee('Evening Shift');
        $response->assertSee('6 workers');

        // Does NOT display a separate "Workers Required" column header in the table
        $response->assertDontSee('<th class="py-3 px-4 text-center">Workers Required</th>', false);
        $response->assertDontSee('<th>Workers Required</th>', false);
    }

    public function test_tenant_isolation_on_shift_worker_assignment(): void
    {
        // Shift belonging to Tenant B
        $shiftTenantB = ProductionShift::create([
            'tenant_id'     => $this->tenantB->id,
            'name'          => 'Tenant B Shift',
            'code'          => 'SH-TB-01',
            'start_time'    => '08:00',
            'end_time'      => '16:00',
            'break_minutes' => 30,
            'active'        => true,
        ]);

        $response = $this->actingAs($this->adminA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.work-center-sections.store'), [
                'work_center_id' => $this->workCenterA1->id,
                'name'           => 'Cross Tenant Shift Section',
                'code'           => 'SEC-CROSS-SH',
                'status'         => 'active',
                'shifts'         => [$shiftTenantB->id],
                'shift_workers'  => [$shiftTenantB->id => 3],
            ]);

        $response->assertSessionHasErrors(['shifts.0']);
    }
}

