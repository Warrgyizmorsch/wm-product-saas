<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\ExternalWorkerPayGrade;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenterSection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExternalWorkerAllocationService
{
    /**
     * Allocate an external worker quantity for a Section, Shift, and Pay Grade.
     *
     * @param int $sectionId
     * @param int $shiftId
     * @param int $payGradeId
     * @param int $numberOfExternalWorkers
     * @param int $tenantId
     * @param bool $active
     * @param bool $updateIfExists
     * @return ExternalWorkerAllocation
     */
    public function allocate(
        int $sectionId,
        int $shiftId,
        int $payGradeId,
        int $numberOfExternalWorkers,
        int $tenantId,
        bool $active = true,
        bool $updateIfExists = false
    ): ExternalWorkerAllocation {
        if ($numberOfExternalWorkers < 1) {
            throw new InvalidArgumentException('Allocated external worker count must be at least 1.');
        }

        // 1. Validate Section and tenant ownership
        $section = WorkCenterSection::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($sectionId);

        if (!$section) {
            throw new InvalidArgumentException("Section #{$sectionId} does not exist or does not belong to this tenant.");
        }

        // 2. Validate Shift and tenant ownership
        $shift = ProductionShift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($shiftId);

        if (!$shift) {
            throw new InvalidArgumentException("Shift #{$shiftId} does not exist or does not belong to this tenant.");
        }

        // 3. Validate Pay Grade and tenant ownership
        $payGrade = ExternalWorkerPayGrade::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($payGradeId);

        if (!$payGrade) {
            throw new InvalidArgumentException("Pay grade #{$payGradeId} does not exist or does not belong to this tenant.");
        }

        if ($payGrade->deleted_at !== null) {
            throw new InvalidArgumentException("Cannot allocate to soft-deleted pay grade '{$payGrade->name}'.");
        }

        // 4. Validate Section–Shift relationship via assigned_shifts JSON
        if (!$section->hasShift($shiftId)) {
            throw new InvalidArgumentException(
                "Shift '{$shift->name}' is not assigned to Section '{$section->name}'. Allocation requires an existing Section operating shift."
            );
        }

        // 5. If allocating as active, all related records must be active
        if ($active) {
            $this->validateActivePrerequisites($section, $shift, $payGrade);
        }

        // 6. Uniqueness validation: logical grouping (Section, Shift, Pay Grade)
        $existing = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('section_id', $sectionId)
            ->where('shift_id', $shiftId)
            ->where('pay_grade_id', $payGradeId)
            ->first();

        if ($existing) {
            if ($updateIfExists) {
                $existing->update([
                    'number_of_external_workers' => $numberOfExternalWorkers,
                    'active'                     => $active,
                ]);
                return $existing->fresh(['section', 'shift', 'payGrade']);
            }

            throw new InvalidArgumentException(
                "An external worker allocation already exists for Section '{$section->name}', Shift '{$shift->name}', and Pay Grade '{$payGrade->name}'."
            );
        }

        return ExternalWorkerAllocation::create([
            'tenant_id'                  => $tenantId,
            'section_id'                 => $sectionId,
            'shift_id'                   => $shiftId,
            'pay_grade_id'               => $payGradeId,
            'number_of_external_workers' => $numberOfExternalWorkers,
            'active'                     => $active,
        ]);
    }

    /**
     * Update the allocated quantity of external workers.
     */
    public function updateQuantity(int $id, int $quantity, int $tenantId): ExternalWorkerAllocation
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Allocated external worker count must be at least 1.');
        }

        $allocation = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$allocation) {
            throw new InvalidArgumentException("External worker allocation #{$id} not found for this tenant.");
        }

        if ($allocation->active) {
            $section = WorkCenterSection::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($allocation->section_id);
            $shift = ProductionShift::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($allocation->shift_id);
            $payGrade = ExternalWorkerPayGrade::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($allocation->pay_grade_id);

            if ($section && $shift && $payGrade) {
                $this->validateActivePrerequisites($section, $shift, $payGrade);
            }
        }

        $allocation->update(['number_of_external_workers' => $quantity]);

        return $allocation->fresh(['section', 'shift', 'payGrade']);
    }

    /**
     * Set active/inactive status for an allocation.
     */
    public function setActive(int $id, bool $active, int $tenantId): ExternalWorkerAllocation
    {
        $allocation = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$allocation) {
            throw new InvalidArgumentException("External worker allocation #{$id} not found for this tenant.");
        }

        if ($active) {
            $section = WorkCenterSection::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($allocation->section_id);
            if (!$section) {
                throw new InvalidArgumentException("Section #{$allocation->section_id} does not exist or does not belong to this tenant.");
            }

            $shift = ProductionShift::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($allocation->shift_id);
            if (!$shift) {
                throw new InvalidArgumentException("Shift #{$allocation->shift_id} does not exist or does not belong to this tenant.");
            }

            $payGrade = ExternalWorkerPayGrade::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($allocation->pay_grade_id);
            if (!$payGrade) {
                throw new InvalidArgumentException("Pay grade #{$allocation->pay_grade_id} does not exist or does not belong to this tenant.");
            }

            $this->validateActivePrerequisites($section, $shift, $payGrade);
        }

        $allocation->update(['active' => $active]);

        return $allocation->fresh(['section', 'shift', 'payGrade']);
    }

    /**
     * Validate that Section, Shift, Section operating shift assignment, and Pay Grade are active.
     */
    private function validateActivePrerequisites(
        WorkCenterSection $section,
        ProductionShift $shift,
        ExternalWorkerPayGrade $payGrade
    ): void {
        if (!$section->isActive()) {
            throw new InvalidArgumentException("Cannot create or activate allocation because Section '{$section->name}' is inactive.");
        }

        if (!$shift->isActive()) {
            throw new InvalidArgumentException("Cannot create or activate allocation because Shift '{$shift->name}' is inactive.");
        }

        if (!$section->hasShift($shift->id)) {
            throw new InvalidArgumentException("Cannot create or activate allocation because Shift '{$shift->name}' is not assigned to Section '{$section->name}'.");
        }

        if ($payGrade->deleted_at !== null) {
            throw new InvalidArgumentException("Cannot create or activate allocation because Pay Grade '{$payGrade->name}' is soft-deleted.");
        }

        if (!$payGrade->isActive()) {
            throw new InvalidArgumentException("Cannot create or activate allocation because Pay Grade '{$payGrade->name}' is inactive.");
        }
    }

    /**
     * Remove / deallocate an external worker allocation.
     */
    public function deallocate(int $id, int $tenantId): bool
    {
        $allocation = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$allocation) {
            throw new InvalidArgumentException("External worker allocation #{$id} not found for this tenant.");
        }

        return (bool) $allocation->delete();
    }

    /**
     * Retrieve all allocations for a given section.
     */
    public function getAllocationsForSection(int $sectionId, int $tenantId, bool $activeOnly = true): Collection
    {
        $query = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('section_id', $sectionId)
            ->with(['shift', 'payGrade']);

        if ($activeOnly) {
            $query->where('active', true);
        }

        return $query->get();
    }

    /**
     * Retrieve all allocations for a specific Section–Shift combination.
     */
    public function getAllocationsForSectionShift(
        int $sectionId,
        int $shiftId,
        int $tenantId,
        bool $activeOnly = true
    ): Collection {
        $query = ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('section_id', $sectionId)
            ->where('shift_id', $shiftId)
            ->with(['payGrade']);

        if ($activeOnly) {
            $query->where('active', true);
        }

        return $query->get();
    }

    /**
     * Calculate total external workers for a Section–Shift combination.
     */
    public function getTotalWorkersForSectionShift(int $sectionId, int $shiftId, int $tenantId): int
    {
        return (int) ExternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('section_id', $sectionId)
            ->where('shift_id', $shiftId)
            ->where('active', true)
            ->sum('number_of_external_workers');
    }
}
