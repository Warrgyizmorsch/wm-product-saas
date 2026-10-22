<?php

namespace Tests\Feature\Production;

use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExternalWorkerUiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private WorkCenter $workCenterA;
    private WorkCenterSection $sectionA;
    private ProductionShift $shiftA;
    private ExternalWorkerPayGrade $payGradeA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenantA = Tenant::create([
            'name'   => 'Tenant A',
            'slug'   => 'tenant-a',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name'   => 'Tenant B',
            'slug'   => 'tenant-b',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Production Manager A',
            'email'     => 'manager.a@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'production_manager',
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Production Manager B',
            'email'     => 'manager.b@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'production_manager',
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id'   => $this->userA->id,
            'role_id'   => $role->id,
            'tenant_id' => $this->tenantA->id,
        ]);
        UserRole::create([
            'user_id'   => $this->userB->id,
            'role_id'   => $role->id,
            'tenant_id' => $this->tenantB->id,
        ]);

        // Tenant A Work Center, Section, Shift
        $this->workCenterA = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Work Center Alpha',
            'code'      => 'WC-ALPHA',
            'status'    => WorkCenter::STATUS_ACTIVE,
        ]);

        $this->sectionA = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name'           => 'Section Alpha 1',
            'code'           => 'SEC-A1',
            'status'         => WorkCenterSection::STATUS_ACTIVE,
        ]);

        $this->shiftA = ProductionShift::create([
            'tenant_id'  => $this->tenantA->id,
            'name'       => 'Morning Shift',
            'code'       => 'SHIFT-MORN',
            'start_time' => '08:00:00',
            'end_time'   => '16:00:00',
            'active'     => true,
        ]);

        DB::table('production_section_shifts')->insert([
            'tenant_id'        => $this->tenantA->id,
            'section_id'       => $this->sectionA->id,
            'shift_id'         => $this->shiftA->id,
            'target_headcount' => 5,
            'active'           => 1,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->payGradeA = ExternalWorkerPayGrade::create([
            'tenant_id'   => $this->tenantA->id,
            'name'        => 'Assembler Grade 1',
            'code'        => 'PG-ASM-1',
            'hourly_rate' => 20.00,
            'active'      => true,
        ]);
    }

    /**
     * Requirement 1: Authorized tenant user can access Pay Grade management.
     */
    public function test_authorized_tenant_user_can_access_pay_grade_management(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.pay-grades.index'));

        $response->assertOk();
        $response->assertViewIs('modules.production.pay-grades.index');
        $response->assertSee('Assembler Grade 1');
        $response->assertSee('PG-ASM-1');
    }

    /**
     * Requirement 2: Pay Grade creation and editing work with validation.
     */
    public function test_pay_grade_creation_and_editing_work_with_validation(): void
    {
        // Validation failure: missing code & negative rate
        $invalidResponse = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.pay-grades.store'), [
                'code'        => '',
                'name'        => 'Invalid Grade',
                'hourly_rate' => -5,
            ]);

        $invalidResponse->assertSessionHasErrors(['code', 'hourly_rate']);

        // Successful creation
        $createResponse = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.pay-grades.store'), [
                'code'        => 'PG-WELD',
                'name'        => 'Certified Welder',
                'hourly_rate' => 35.50,
                'description' => 'MIG/TIG certification required',
                'active'      => '1',
            ]);

        $createResponse->assertRedirect(route('production.pay-grades.index'));
        $createResponse->assertSessionHas('success');

        $this->assertDatabaseHas('production_external_worker_pay_grades', [
            'tenant_id' => $this->tenantA->id,
            'code'      => 'PG-WELD',
            'name'      => 'Certified Welder',
            'active'    => 1,
        ]);

        $weldGrade = ExternalWorkerPayGrade::where('tenant_id', $this->tenantA->id)
            ->where('code', 'PG-WELD')
            ->firstOrFail();

        // Successful edit
        $updateResponse = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->put(route('production.pay-grades.update', $weldGrade->id), [
                'code'        => 'PG-WELD',
                'name'        => 'Senior Welder Specialist',
                'hourly_rate' => 42.00,
                'description' => 'Updated requirements',
                'active'      => '1',
            ]);

        $updateResponse->assertRedirect(route('production.pay-grades.index'));
        $updateResponse->assertSessionHas('success');

        $this->assertDatabaseHas('production_external_worker_pay_grades', [
            'id'   => $weldGrade->id,
            'name' => 'Senior Welder Specialist',
        ]);
    }

    /**
     * Requirement 3: Pay Grade deletion is rejected while active allocations exist.
     */
    public function test_pay_grade_deletion_is_rejected_while_active_allocations_exist(): void
    {
        $sectionShift = DB::table('production_section_shifts')
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        // Active allocation referencing payGradeA
        ExternalWorkerAllocation::create([
            'tenant_id'        => $this->tenantA->id,
            'section_shift_id' => $sectionShift->id,
            'section_id'       => $this->sectionA->id,
            'shift_id'         => $this->shiftA->id,
            'pay_grade_id'     => $this->payGradeA->id,
            'headcount'        => 3,
            'active'           => true,
        ]);

        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->delete(route('production.pay-grades.destroy', $this->payGradeA->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('production_external_worker_pay_grades', [
            'id'         => $this->payGradeA->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Requirement 4: Pay Grade deletion is permitted after allocations are deactivated, with history retained.
     */
    public function test_pay_grade_deletion_is_permitted_after_allocations_are_deactivated_with_history_retained(): void
    {
        $sectionShift = DB::table('production_section_shifts')
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $allocation = ExternalWorkerAllocation::create([
            'tenant_id'        => $this->tenantA->id,
            'section_shift_id' => $sectionShift->id,
            'section_id'       => $this->sectionA->id,
            'shift_id'         => $this->shiftA->id,
            'pay_grade_id'     => $this->payGradeA->id,
            'headcount'        => 2,
            'active'           => false, // Deactivated allocation
        ]);

        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->delete(route('production.pay-grades.destroy', $this->payGradeA->id));

        $response->assertRedirect(route('production.pay-grades.index'));
        $response->assertSessionHas('success');

        // Pay grade is soft-deleted
        $this->assertSoftDeleted('production_external_worker_pay_grades', [
            'id' => $this->payGradeA->id,
        ]);

        // Historical allocation record is preserved
        $this->assertDatabaseHas('production_external_worker_allocations', [
            'id'           => $allocation->id,
            'pay_grade_id' => $this->payGradeA->id,
            'deleted_at'   => null,
        ]);
    }

    /**
     * Requirement 5: Cross-tenant access and mutation attempts are rejected.
     */
    public function test_cross_tenant_access_and_mutation_attempts_are_rejected(): void
    {
        // User B attempts to edit Tenant A's pay grade
        $editResponse = $this->actingAs($this->userB)
            ->withHeader('X-Tenant', 'tenant-b')
            ->get(route('production.pay-grades.edit', $this->payGradeA->id));

        $editResponse->assertNotFound();

        // User B attempts to delete Tenant A's pay grade
        $deleteResponse = $this->actingAs($this->userB)
            ->withHeader('X-Tenant', 'tenant-b')
            ->delete(route('production.pay-grades.destroy', $this->payGradeA->id));

        $deleteResponse->assertNotFound();

        // User B attempts to allocate for Tenant A's Section & Shift
        $allocResponse = $this->actingAs($this->userB)
            ->withHeader('X-Tenant', 'tenant-b')
            ->post(route('production.external-worker-allocations.store'), [
                'section_id'   => $this->sectionA->id,
                'shift_id'     => $this->shiftA->id,
                'pay_grade_id' => $this->payGradeA->id,
                'headcount'    => 2,
            ]);

        $allocResponse->assertSessionHasErrors(['section_id', 'shift_id', 'pay_grade_id']);
    }

    /**
     * Requirement 6: A valid Section–Shift allocation can be created and displayed.
     */
    public function test_valid_section_shift_allocation_can_be_created_and_displayed(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.external-worker-allocations.store'), [
                'section_id'   => $this->sectionA->id,
                'shift_id'     => $this->shiftA->id,
                'pay_grade_id' => $this->payGradeA->id,
                'headcount'    => 4,
            ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('production_external_worker_allocations', [
            'tenant_id'    => $this->tenantA->id,
            'section_id'   => $this->sectionA->id,
            'shift_id'     => $this->shiftA->id,
            'pay_grade_id' => $this->payGradeA->id,
            'headcount'    => 4,
            'active'       => 1,
        ]);

        // Verify displayed on Work Center details page
        $showResponse = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.show', $this->workCenterA->id));

        $showResponse->assertOk();
        $showResponse->assertSee('Ext: 4');
        $showResponse->assertSee('Target: 5');
        $showResponse->assertSee('Assembler Grade 1');
    }

    /**
     * Requirement 7: Headcount updates work without creating duplicate logical allocations.
     */
    public function test_headcount_updates_work_without_creating_duplicate_logical_allocations(): void
    {
        $sectionShift = DB::table('production_section_shifts')
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $alloc = ExternalWorkerAllocation::create([
            'tenant_id'        => $this->tenantA->id,
            'section_shift_id' => $sectionShift->id,
            'section_id'       => $this->sectionA->id,
            'shift_id'         => $this->shiftA->id,
            'pay_grade_id'     => $this->payGradeA->id,
            'headcount'        => 2,
            'active'           => true,
        ]);

        // Update headcount via endpoint
        $patchResponse = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->patch(route('production.external-worker-allocations.headcount', $alloc->id), [
                'headcount' => 6,
            ]);

        $patchResponse->assertSessionHas('success');

        $this->assertEquals(6, $alloc->fresh()->headcount);
        $this->assertEquals(1, ExternalWorkerAllocation::where('section_id', $this->sectionA->id)->count());

        // Update-in-place via store endpoint with update_if_exists = 1
        $storeResponse = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.external-worker-allocations.store'), [
                'section_id'       => $this->sectionA->id,
                'shift_id'         => $this->shiftA->id,
                'pay_grade_id'     => $this->payGradeA->id,
                'headcount'        => 8,
                'update_if_exists' => '1',
            ]);

        $storeResponse->assertSessionHas('success');
        $this->assertEquals(8, $alloc->fresh()->headcount);
        $this->assertEquals(1, ExternalWorkerAllocation::where('section_id', $this->sectionA->id)->count());
    }

    /**
     * Requirement 8: Activation is rejected when any required related record is inactive.
     */
    public function test_activation_is_rejected_when_any_required_related_record_is_inactive(): void
    {
        $sectionShift = DB::table('production_section_shifts')
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $alloc = ExternalWorkerAllocation::create([
            'tenant_id'        => $this->tenantA->id,
            'section_shift_id' => $sectionShift->id,
            'section_id'       => $this->sectionA->id,
            'shift_id'         => $this->shiftA->id,
            'pay_grade_id'     => $this->payGradeA->id,
            'headcount'        => 2,
            'active'           => false,
        ]);

        // Deactivate Section
        $this->sectionA->update(['status' => WorkCenterSection::STATUS_INACTIVE]);

        $activateResponse = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->patch(route('production.external-worker-allocations.toggle-status', $alloc->id), [
                'active' => '1',
            ]);

        $activateResponse->assertSessionHas('error');
        $this->assertFalse($alloc->fresh()->active);
    }

    /**
     * Requirement 9: Deactivation preserves the allocation record.
     */
    public function test_deactivation_preserves_the_allocation_record(): void
    {
        $sectionShift = DB::table('production_section_shifts')
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $alloc = ExternalWorkerAllocation::create([
            'tenant_id'        => $this->tenantA->id,
            'section_shift_id' => $sectionShift->id,
            'section_id'       => $this->sectionA->id,
            'shift_id'         => $this->shiftA->id,
            'pay_grade_id'     => $this->payGradeA->id,
            'headcount'        => 3,
            'active'           => true,
        ]);

        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->patch(route('production.external-worker-allocations.toggle-status', $alloc->id), [
                'active' => '0',
            ]);

        $response->assertSessionHas('success');
        $this->assertFalse($alloc->fresh()->active);
        $this->assertNull($alloc->fresh()->deleted_at);
        $this->assertEquals(1, ExternalWorkerAllocation::where('id', $alloc->id)->count());
    }

    /**
     * Requirement 10: The displayed external headcount includes active allocations only and remains separate from target_headcount.
     */
    public function test_displayed_external_headcount_includes_active_allocations_only_and_remains_separate_from_target_headcount(): void
    {
        $sectionShift = DB::table('production_section_shifts')
            ->where('tenant_id', $this->tenantA->id)
            ->first();

        $payGrade2 = ExternalWorkerPayGrade::create([
            'tenant_id'   => $this->tenantA->id,
            'name'        => 'Assembler Grade 2',
            'code'        => 'PG-ASM-2',
            'hourly_rate' => 25.00,
            'active'      => true,
        ]);

        // Active allocation: headcount 3
        ExternalWorkerAllocation::create([
            'tenant_id'        => $this->tenantA->id,
            'section_shift_id' => $sectionShift->id,
            'section_id'       => $this->sectionA->id,
            'shift_id'         => $this->shiftA->id,
            'pay_grade_id'     => $this->payGradeA->id,
            'headcount'        => 3,
            'active'           => true,
        ]);

        // Inactive allocation: headcount 4
        ExternalWorkerAllocation::create([
            'tenant_id'        => $this->tenantA->id,
            'section_shift_id' => $sectionShift->id,
            'section_id'       => $this->sectionA->id,
            'shift_id'         => $this->shiftA->id,
            'pay_grade_id'     => $payGrade2->id,
            'headcount'        => 4,
            'active'           => false,
        ]);

        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.work-centers.show', $this->workCenterA->id));

        $response->assertOk();
        // Target headcount must remain 5
        $response->assertSee('Target: 5');
        // External headcount must be exactly 3 (active), NOT 7 (which would include the inactive 4)
        $response->assertSee('Ext: 3');

        // Confirm database target_headcount in production_section_shifts was NOT modified
        $this->assertEquals(5, DB::table('production_section_shifts')->where('id', $sectionShift->id)->value('target_headcount'));
    }
}
