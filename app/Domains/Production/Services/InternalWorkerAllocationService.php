<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\InternalWorkerAllocation;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\WorkCenterSection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InternalWorkerAllocationService
{
    /**
     * Allocate an internal worker headcount for a Section and Shift.
     */
    public function allocate(
        int $sectionId,
        int $shiftId,
        int $headcount,
        int $tenantId,
        bool $active = true,
        bool $updateIfExists = true
    ): InternalWorkerAllocation {
        if ($headcount < 1) {
            throw new InvalidArgumentException('Allocated internal worker headcount must be at least 1.');
        }

        // 1. Validate Section
        $section = WorkCenterSection::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($sectionId);

        if (!$section) {
            throw new InvalidArgumentException("Section #{$sectionId} does not exist or does not belong to this tenant.");
        }

        // 2. Validate Shift
        $shift = ProductionShift::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($shiftId);

        if (!$shift) {
            throw new InvalidArgumentException("Shift #{$shiftId} does not exist or does not belong to this tenant.");
        }

        // 3. Validate Section–Shift relationship
        $sectionShift = DB::table('production_section_shifts')
            ->where('tenant_id', $tenantId)
            ->where('section_id', $sectionId)
            ->where('shift_id', $shiftId)
            ->first();

        if (!$sectionShift) {
            throw new InvalidArgumentException(
                "Shift '{$shift->name}' is not assigned to Section '{$section->name}'. Allocation requires an existing Section–Shift assignment."
            );
        }

        if ($active) {
            $this->validateActivePrerequisites($section, $shift, $sectionShift);
        }

        // 4. Logical grouping check (Section, Shift)
        $existing = InternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('section_id', $sectionId)
            ->where('shift_id', $shiftId)
            ->first();

        if ($existing) {
            if ($updateIfExists) {
                $existing->update([
                    'headcount'        => $headcount,
                    'active'           => $active,
                    'rate'             => 100.0000,
                    'section_shift_id' => $sectionShift->id,
                ]);
                return $existing->fresh(['section', 'shift']);
            }

            throw new InvalidArgumentException(
                "An internal worker allocation already exists for Section '{$section->name}' and Shift '{$shift->name}'."
            );
        }

        return InternalWorkerAllocation::create([
            'tenant_id'        => $tenantId,
            'section_shift_id' => $sectionShift->id,
            'section_id'       => $sectionId,
            'shift_id'         => $shiftId,
            'headcount'        => $headcount,
            'rate'             => 100.0000,
            'active'           => $active,
        ]);
    }

    public function updateHeadcount(int $id, int $headcount, int $tenantId): InternalWorkerAllocation
    {
        if ($headcount < 1) {
            throw new InvalidArgumentException('Allocated internal worker headcount must be at least 1.');
        }

        $allocation = InternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$allocation) {
            throw new InvalidArgumentException("Internal worker allocation #{$id} not found for this tenant.");
        }

        $allocation->update(['headcount' => $headcount]);

        return $allocation->fresh(['section', 'shift']);
    }

    public function setActive(int $id, bool $active, int $tenantId): InternalWorkerAllocation
    {
        $allocation = InternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$allocation) {
            throw new InvalidArgumentException("Internal worker allocation #{$id} not found for this tenant.");
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

            $sectionShift = DB::table('production_section_shifts')
                ->where('tenant_id', $tenantId)
                ->where('section_id', $allocation->section_id)
                ->where('shift_id', $allocation->shift_id)
                ->first();
            if (!$sectionShift) {
                throw new InvalidArgumentException("Shift #{$allocation->shift_id} is not assigned to Section #{$allocation->section_id}.");
            }

            $this->validateActivePrerequisites($section, $shift, $sectionShift);
        }

        $allocation->update(['active' => $active]);

        return $allocation->fresh(['section', 'shift']);
    }

    public function deallocate(int $id, int $tenantId): bool
    {
        $allocation = InternalWorkerAllocation::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$allocation) {
            throw new InvalidArgumentException("Internal worker allocation #{$id} not found for this tenant.");
        }

        return (bool) $allocation->delete();
    }

    private function validateActivePrerequisites(
        WorkCenterSection $section,
        ProductionShift $shift,
        object $sectionShift
    ): void {
        if (!$section->isActive()) {
            throw new InvalidArgumentException("Cannot activate internal worker allocation: Section '{$section->name}' is inactive.");
        }

        if (!$shift->isActive()) {
            throw new InvalidArgumentException("Cannot activate internal worker allocation: Shift '{$shift->name}' is inactive.");
        }

        if (property_exists($sectionShift, 'active') && !(bool) $sectionShift->active) {
            throw new InvalidArgumentException("Cannot activate internal worker allocation: Assignment of Shift '{$shift->name}' to Section '{$section->name}' is inactive.");
        }
    }
}
