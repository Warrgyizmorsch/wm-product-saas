<?php

namespace Tests\Feature\Production;

use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\ExternalWorkerAllocationService;
use App\Domains\Production\Services\ExternalWorkerPayGradeService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class ExternalWorkerAllocationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private WorkCenter $workCenterA;
    private WorkCenterSection $sectionA1;
    private WorkCenterSection $sectionA2;
    private ProductionShift $shiftDayA;
    private ProductionShift $shiftNightA;
    private ExternalWorkerPayGrade $payGradeSkilledA;
    private ExternalWorkerPayGrade $payGradeHelperA;

    private WorkCenter $workCenterB;
    private WorkCenterSection $sectionB;
    private ProductionShift $shiftDayB;
    private ExternalWorkerPayGrade $payGradeB;

    private ExternalWorkerPayGradeService $payGradeService;
    private ExternalWorkerAllocationService $allocationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name'   => 'Tenant A',
            'slug'   => 'tenant-a',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'User A',
            'email'     => 'usera@example.com',
            'password'  => bcrypt('password'),
            'role'      => 'admin',
        ]);

        $this->tenantB = Tenant::create([
            'name'   => 'Tenant B',
            'slug'   => 'tenant-b',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        // Tenant A Work Center & Sections
        $this->workCenterA = WorkCenter::create([
            'tenant_id' => $this->tenantA->id,
            'name'      => 'Assembly Center A',
            'code'      => 'WC-A',
            'status'    => WorkCenter::STATUS_ACTIVE,
        ]);

        $this->sectionA1 = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name'           => 'Section A1',
            'code'           => 'SEC-A1',
            'status'         => WorkCenterSection::STATUS_ACTIVE,
        ]);

        $this->sectionA2 = WorkCenterSection::create([
            'tenant_id'      => $this->tenantA->id,
            'work_center_id' => $this->workCenterA->id,
            'name'           => 'Section A2',
            'code'           => 'SEC-A2',
            'status'         => WorkCenterSection::STATUS_ACTIVE,
        ]);

        // Tenant A Shifts
        $this->shiftDayA = ProductionShift::create([
            'tenant_id'  => $this->tenantA->id,
            'name'       => 'Day Shift A',
            'code'       => 'SHIFT-DAY-A',
            'start_time' => '08:00:00',
            'end_time'   => '16:00:00',
            'active'     => true,
        ]);

        $this->shiftNightA = ProductionShift::create([
            'tenant_id'  => $this->tenantA->id,
            'name'       => 'Night Shift A',
            'code'       => 'SHIFT-NIGHT-A',
            'start_time' => '20:00:00',
            'end_time'   => '04:00:00',
            'active'     => true,
        ]);

        // Configure operating shifts for Sections
        $this->sectionA1->update(['assigned_shifts' => [$this->shiftDayA->id, $this->shiftNightA->id]]);
        $this->sectionA2->update(['assigned_shifts' => [$this->shiftDayA->id]]);

        // Tenant A Pay Grades
        $this->payGradeSkilledA = ExternalWorkerPayGrade::create([
            'tenant_id'   => $this->tenantA->id,
            'name'        => 'Skilled Technician',
            'code'        => 'PG-SKILLED',
            'hourly_rate' => 25.5000,
            'active'      => true,
        ]);

        $this->payGradeHelperA = ExternalWorkerPayGrade::create([
            'tenant_id'   => $this->tenantA->id,
            'name'        => 'General Helper',
            'code'        => 'PG-HELPER',
            'hourly_rate' => 15.0000,
            'active'      => true,
        ]);

        // Tenant B Entities
        $this->workCenterB = WorkCenter::create([
            'tenant_id' => $this->tenantB->id,
            'name'      => 'Machining Center B',
            'code'      => 'WC-B',
            'status'    => WorkCenter::STATUS_ACTIVE,
        ]);

        $this->sectionB = WorkCenterSection::create([
            'tenant_id'      => $this->tenantB->id,
            'work_center_id' => $this->workCenterB->id,
            'name'           => 'Section B1',
            'code'           => 'SEC-B1',
            'status'         => WorkCenterSection::STATUS_ACTIVE,
        ]);

        $this->shiftDayB = ProductionShift::create([
            'tenant_id'  => $this->tenantB->id,
            'name'       => 'Day Shift B',
            'code'       => 'SHIFT-DAY-B',
            'start_time' => '09:00:00',
            'end_time'   => '17:00:00',
            'active'     => true,
        ]);

        $this->sectionB->update(['assigned_shifts' => [$this->shiftDayB->id]]);

        $this->payGradeB = ExternalWorkerPayGrade::create([
            'tenant_id'   => $this->tenantB->id,
            'name'        => 'Welder Grade B',
            'code'        => 'PG-WELDER-B',
            'hourly_rate' => 30.0000,
            'active'      => true,
        ]);

        $this->payGradeService = app(ExternalWorkerPayGradeService::class);
        $this->allocationService = app(ExternalWorkerAllocationService::class);
    }

    public function test_pay_grade_schema_and_tenant_isolation(): void
    {
        $this->assertTrue(Schema::hasTable('production_external_worker_pay_grades'));
        $this->assertTrue(Schema::hasColumns('production_external_worker_pay_grades', [
            'id', 'tenant_id', 'name', 'code', 'hourly_rate', 'description', 'active', 'deleted_at'
        ]));

        // Tenant A pay grades exist
        $this->assertDatabaseHas('production_external_worker_pay_grades', [
            'tenant_id' => $this->tenantA->id,
            'code'      => 'PG-SKILLED',
        ]);

        // Duplicate code within the SAME tenant must be rejected
        $this->expectException(InvalidArgumentException::class);
        $this->payGradeService->create([
            'name'        => 'Duplicate Code',
            'code'        => 'PG-SKILLED',
            'hourly_rate' => 20.0,
        ], $this->tenantA->id);
    }

    public function test_same_pay_grade_code_allowed_for_different_tenants(): void
    {
        // Tenant B can use the same code 'PG-SKILLED' because uniqueness is scoped by tenant
        $pgB = $this->payGradeService->create([
            'name'        => 'Tenant B Skilled',
            'code'        => 'PG-SKILLED',
            'hourly_rate' => 28.0000,
        ], $this->tenantB->id);

        $this->assertDatabaseHas('production_external_worker_pay_grades', [
            'id'        => $pgB->id,
            'tenant_id' => $this->tenantB->id,
            'code'      => 'PG-SKILLED',
        ]);
    }

    public function test_pay_grade_rejects_negative_hourly_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->payGradeService->create([
            'name'        => 'Invalid Negative Rate',
            'code'        => 'PG-NEG',
            'hourly_rate' => -10.50,
        ], $this->tenantA->id);
    }

    public function test_allocation_schema_has_no_work_center_column(): void
    {
        $this->assertTrue(Schema::hasTable('production_external_worker_allocations'));
        $this->assertFalse(Schema::hasColumn('production_external_worker_allocations', 'work_center_id'));

        $this->assertTrue(Schema::hasColumns('production_external_worker_allocations', [
            'id', 'tenant_id', 'section_id', 'shift_id', 'pay_grade_id', 'number_of_external_workers', 'active'
        ]));
        $this->assertFalse(Schema::hasColumn('production_external_worker_allocations', 'headcount'));
        $this->assertFalse(Schema::hasColumn('production_external_worker_allocations', 'section_shift_id'));
    }

    public function test_external_worker_allocation_creates_successfully(): void
    {
        $allocation = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 3,
            tenantId: $this->tenantA->id
        );

        $this->assertDatabaseHas('production_external_worker_allocations', [
            'id'                         => $allocation->id,
            'tenant_id'                  => $this->tenantA->id,
            'section_id'                 => $this->sectionA1->id,
            'shift_id'                   => $this->shiftDayA->id,
            'pay_grade_id'               => $this->payGradeSkilledA->id,
            'number_of_external_workers' => 3,
            'active'                     => 1,
        ]);
    }

    public function test_work_center_is_derived_through_section(): void
    {
        $allocation = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id
        );

        // Derived Work Center matches Section's Work Center
        $this->assertNotNull($allocation->workCenter);
        $this->assertEquals($this->workCenterA->id, $allocation->workCenter->id);
        $this->assertEquals('Assembly Center A', $allocation->workCenter->name);

        // WorkCenter hasManyThrough externalWorkerAllocations
        $wcAllocations = $this->workCenterA->externalWorkerAllocations;
        $this->assertTrue($wcAllocations->contains('id', $allocation->id));
    }

    public function test_allocation_rejected_if_shift_not_assigned_to_section(): void
    {
        // ShiftNightA is assigned to SectionA1, but NOT to SectionA2 in assigned_shifts
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not assigned to Section/i');

        $this->allocationService->allocate(
            sectionId: $this->sectionA2->id,
            shiftId: $this->shiftNightA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id
        );
    }

    public function test_cross_tenant_allocation_is_rejected(): void
    {
        // Attempt to allocate Tenant B's pay grade to Tenant A's section
        $this->expectException(InvalidArgumentException::class);

        $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeB->id, // Belongs to Tenant B
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id
        );
    }

    public function test_cross_tenant_shift_is_rejected(): void
    {
        // Attempt to allocate Tenant B's shift to Tenant A's section
        $this->expectException(InvalidArgumentException::class);

        $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayB->id, // Belongs to Tenant B
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id
        );
    }

    public function test_duplicate_allocation_for_same_logical_grouping_rejected(): void
    {
        // First allocation succeeds
        $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id
        );

        // Second allocation with identical (Section, Shift, PayGrade) must be rejected
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already exists/i');

        $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 4,
            tenantId: $this->tenantA->id,
            updateIfExists: false
        );
    }

    public function test_number_of_external_workers_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be at least 1/i');
        $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 0,
            tenantId: $this->tenantA->id
        );
    }

    public function test_section_worker_aggregation_and_active_toggle(): void
    {
        // Allocate 3 skilled and 4 helpers to Section A1 on Day Shift
        $alloc1 = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 3,
            tenantId: $this->tenantA->id
        );

        $alloc2 = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeHelperA->id,
            numberOfExternalWorkers: 4,
            tenantId: $this->tenantA->id
        );

        // Allocate 2 helpers to Section A1 on Night Shift
        $alloc3 = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftNightA->id,
            payGradeId: $this->payGradeHelperA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id
        );

        // Total Day Shift external workers for Section A1: 3 + 4 = 7
        $this->assertEquals(7, $this->allocationService->getTotalWorkersForSectionShift(
            $this->sectionA1->id,
            $this->shiftDayA->id,
            $this->tenantA->id
        ));

        // Section helper method
        $this->assertEquals(7, $this->sectionA1->getTotalExternalWorkers($this->shiftDayA->id));
        $this->assertEquals(2, $this->sectionA1->getTotalExternalWorkers($this->shiftNightA->id));
        $this->assertEquals(9, $this->sectionA1->getTotalExternalWorkers()); // Both shifts

        // Deactivate alloc1 (3 skilled)
        $this->allocationService->setActive($alloc1->id, false, $this->tenantA->id);

        // Now active workers for Day Shift should be only 4
        $this->assertEquals(4, $this->sectionA1->fresh()->getTotalExternalWorkers($this->shiftDayA->id));
    }

    public function test_cascade_deletion_preserves_data_integrity(): void
    {
        $alloc = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 3,
            tenantId: $this->tenantA->id
        );

        $this->assertDatabaseHas('production_external_worker_allocations', ['id' => $alloc->id]);

        // When the Section is deleted, child allocations are removed cleanly via FK cascade
        $this->sectionA1->forceDelete();

        $this->assertDatabaseMissing('production_external_worker_allocations', ['id' => $alloc->id]);
    }

    public function test_deleting_pay_grade_with_active_allocation_is_rejected(): void
    {
        $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id,
            active: true
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/active external worker allocations/i');

        $this->payGradeService->delete($this->payGradeSkilledA->id, $this->tenantA->id);

        $this->assertDatabaseHas('production_external_worker_pay_grades', [
            'id'         => $this->payGradeSkilledA->id,
            'deleted_at' => null,
        ]);
    }

    public function test_after_allocations_deactivated_pay_grade_soft_delete_succeeds_and_history_preserved(): void
    {
        $alloc = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id,
            active: true
        );

        // Deactivate the allocation
        $this->allocationService->setActive($alloc->id, false, $this->tenantA->id);

        // Soft deletion must now succeed
        $deleted = $this->payGradeService->delete($this->payGradeSkilledA->id, $this->tenantA->id);
        $this->assertTrue($deleted);

        // Pay grade is soft deleted
        $this->assertSoftDeleted('production_external_worker_pay_grades', [
            'id' => $this->payGradeSkilledA->id,
        ]);

        // Allocation history remains present in database
        $this->assertDatabaseHas('production_external_worker_allocations', [
            'id'           => $alloc->id,
            'pay_grade_id' => $this->payGradeSkilledA->id,
            'active'       => 0,
            'deleted_at'   => null,
        ]);

        // Historical relation resolves withTrashed
        $freshAlloc = $alloc->fresh();
        $this->assertNotNull($freshAlloc->payGrade);
        $this->assertTrue($freshAlloc->payGrade->trashed());
    }

    public function test_soft_deleted_pay_grade_cannot_be_used_for_new_allocation_or_reactivation(): void
    {
        // Create an inactive allocation first
        $alloc = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 1,
            tenantId: $this->tenantA->id,
            active: false
        );

        // Soft delete the pay grade
        $this->payGradeService->delete($this->payGradeSkilledA->id, $this->tenantA->id);

        // 1. New allocation with soft-deleted pay grade must be rejected
        try {
            $this->allocationService->allocate(
                sectionId: $this->sectionA1->id,
                shiftId: $this->shiftNightA->id,
                payGradeId: $this->payGradeSkilledA->id,
                numberOfExternalWorkers: 2,
                tenantId: $this->tenantA->id
            );
            $this->fail('Expected exception when allocating soft-deleted pay grade.');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('/soft-deleted/i', $e->getMessage());
        }

        // 2. Reactivating existing allocation with soft-deleted pay grade must be rejected
        try {
            $this->allocationService->setActive($alloc->id, true, $this->tenantA->id);
            $this->fail('Expected exception when reactivating allocation with soft-deleted pay grade.');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('/soft-deleted/i', $e->getMessage());
        }
    }

    public function test_creation_and_activation_rejected_when_section_is_inactive(): void
    {
        $inactiveSection = WorkCenterSection::create([
            'tenant_id'       => $this->tenantA->id,
            'work_center_id'  => $this->workCenterA->id,
            'name'            => 'Inactive Section',
            'code'            => 'SEC-INACTIVE',
            'status'          => WorkCenterSection::STATUS_INACTIVE,
            'assigned_shifts' => [$this->shiftDayA->id],
        ]);

        // Creation with active=true must be rejected
        try {
            $this->allocationService->allocate(
                sectionId: $inactiveSection->id,
                shiftId: $this->shiftDayA->id,
                payGradeId: $this->payGradeSkilledA->id,
                numberOfExternalWorkers: 2,
                tenantId: $this->tenantA->id,
                active: true
            );
            $this->fail('Expected exception for inactive section.');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('/inactive Section/i', $e->getMessage());
        }

        // Creation with active=false succeeds
        $alloc = $this->allocationService->allocate(
            sectionId: $inactiveSection->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id,
            active: false
        );

        // Reactivating must be rejected
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/inactive Section/i');
        $this->allocationService->setActive($alloc->id, true, $this->tenantA->id);
    }

    public function test_creation_and_activation_rejected_when_shift_is_inactive(): void
    {
        $inactiveShift = ProductionShift::create([
            'tenant_id'  => $this->tenantA->id,
            'name'       => 'Inactive Shift',
            'code'       => 'SHIFT-INACTIVE',
            'start_time' => '10:00:00',
            'end_time'   => '18:00:00',
            'active'     => false,
        ]);

        $this->sectionA1->update(['assigned_shifts' => [$this->shiftDayA->id, $this->shiftNightA->id, $inactiveShift->id]]);

        // Creation with active=true must be rejected
        try {
            $this->allocationService->allocate(
                sectionId: $this->sectionA1->id,
                shiftId: $inactiveShift->id,
                payGradeId: $this->payGradeSkilledA->id,
                numberOfExternalWorkers: 2,
                tenantId: $this->tenantA->id,
                active: true
            );
            $this->fail('Expected exception for inactive shift.');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('/inactive Shift/i', $e->getMessage());
        }

        // Inactive allocation creation succeeds, but activation fails
        $alloc = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $inactiveShift->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id,
            active: false
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/inactive Shift/i');
        $this->allocationService->setActive($alloc->id, true, $this->tenantA->id);
    }

    public function test_creation_and_activation_rejected_when_shift_is_not_assigned_to_section(): void
    {
        // SectionA2 only has shiftDayA assigned
        $this->sectionA2->update(['assigned_shifts' => [$this->shiftDayA->id]]);

        // Creation with active=true must fail
        try {
            $this->allocationService->allocate(
                sectionId: $this->sectionA2->id,
                shiftId: $this->shiftNightA->id,
                payGradeId: $this->payGradeSkilledA->id,
                numberOfExternalWorkers: 2,
                tenantId: $this->tenantA->id,
                active: true
            );
            $this->fail('Expected exception for unassigned section-shift.');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('/not assigned to Section/i', $e->getMessage());
        }

        // Inactive allocation creation also fails when shift is not configured on Section
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not assigned to Section/i');
        $this->allocationService->allocate(
            sectionId: $this->sectionA2->id,
            shiftId: $this->shiftNightA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id,
            active: false
        );
    }

    public function test_creation_and_activation_rejected_when_pay_grade_is_inactive(): void
    {
        $this->payGradeHelperA->update(['active' => false]);

        // Creation with active=true must fail
        try {
            $this->allocationService->allocate(
                sectionId: $this->sectionA1->id,
                shiftId: $this->shiftDayA->id,
                payGradeId: $this->payGradeHelperA->id,
                numberOfExternalWorkers: 2,
                tenantId: $this->tenantA->id,
                active: true
            );
            $this->fail('Expected exception for inactive pay grade.');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('/Pay Grade.*inactive/i', $e->getMessage());
        }

        // Inactive allocation creation succeeds
        $alloc = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeHelperA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id,
            active: false
        );

        // Reactivation must fail
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Pay Grade.*inactive/i');
        $this->allocationService->setActive($alloc->id, true, $this->tenantA->id);
    }

    public function test_update_quantity_modifies_number_of_external_workers_without_duplicate(): void
    {
        $alloc = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id
        );

        $updated = $this->allocationService->updateQuantity($alloc->id, 5, $this->tenantA->id);

        $this->assertEquals(5, $updated->number_of_external_workers);
        $this->assertEquals(5, $alloc->fresh()->number_of_external_workers);
        $this->assertEquals(1, ExternalWorkerAllocation::where('section_id', $this->sectionA1->id)->count());
    }

    public function test_update_quantity_rejects_non_positive_value(): void
    {
        $alloc = $this->allocationService->allocate(
            sectionId: $this->sectionA1->id,
            shiftId: $this->shiftDayA->id,
            payGradeId: $this->payGradeSkilledA->id,
            numberOfExternalWorkers: 2,
            tenantId: $this->tenantA->id
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must be at least 1/i');

        $this->allocationService->updateQuantity($alloc->id, 0, $this->tenantA->id);
    }

    public function test_missing_section_shift_pair_is_rejected(): void
    {
        // ShiftNightA is not assigned to SectionA2 in assigned_shifts
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not assigned to Section/i');

        ExternalWorkerAllocation::create([
            'tenant_id'                  => $this->tenantA->id,
            'section_id'                 => $this->sectionA2->id,
            'shift_id'                   => $this->shiftNightA->id,
            'pay_grade_id'               => $this->payGradeSkilledA->id,
            'number_of_external_workers' => 2,
            'active'                     => true,
        ]);
    }

    public function test_direct_model_writes_reject_cross_tenant_and_invalid_quantity(): void
    {
        // 1. Invalid number_of_external_workers (0)
        try {
            ExternalWorkerAllocation::create([
                'tenant_id'                  => $this->tenantA->id,
                'section_id'                 => $this->sectionA1->id,
                'shift_id'                   => $this->shiftDayA->id,
                'pay_grade_id'               => $this->payGradeSkilledA->id,
                'number_of_external_workers' => 0,
                'active'                     => true,
            ]);
            $this->fail('Expected exception for quantity 0.');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('/must be at least 1/i', $e->getMessage());
        }

        // 2. Cross-tenant Pay Grade
        try {
            ExternalWorkerAllocation::create([
                'tenant_id'                  => $this->tenantA->id,
                'section_id'                 => $this->sectionA1->id,
                'shift_id'                   => $this->shiftDayA->id,
                'pay_grade_id'               => $this->payGradeB->id, // Belongs to Tenant B
                'number_of_external_workers' => 2,
                'active'                     => true,
            ]);
            $this->fail('Expected exception for cross-tenant pay grade.');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('/does not belong to the allocation tenant/i', $e->getMessage());
        }

        // 3. Cross-tenant Section
        try {
            ExternalWorkerAllocation::create([
                'tenant_id'                  => $this->tenantA->id,
                'section_id'                 => $this->sectionB->id, // Belongs to Tenant B
                'shift_id'                   => $this->shiftDayA->id,
                'pay_grade_id'               => $this->payGradeSkilledA->id,
                'number_of_external_workers' => 2,
                'active'                     => true,
            ]);
            $this->fail('Expected exception for cross-tenant section.');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('/does not belong to the allocation tenant/i', $e->getMessage());
        }
    }
}
