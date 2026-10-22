<?php

namespace App\Domains\Production\Services;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class WorkCenterEmployeeService
{
    /**
     * Assign an internal employee to a Work Center (or Section).
     *
     * Rules:
     * 1. Work Center-level: An employee may be assigned to multiple Work Centers simultaneously.
     * 2. Section-level: An employee assigned to a Section cannot be assigned to any other Section.
     * 3. Mutual Exclusivity: An employee cannot have both active Work Center-level and active Section-level assignments.
     * 4. A Work Center-level assignment implicitly provides operational access to all Sections in that Work Center without separate Section records.
     * 5. Duplicate active assignment to the same Work Center (or Section) is rejected.
     */
    public function assign(
        int $workCenterId,
        int $employeeId,
        int $tenantId,
        bool $active = true,
        ?int $sectionId = null
    ): WorkCenterEmployeeAssignment {
        // 1. Validate Work Center
        $workCenter = WorkCenter::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($workCenterId);

        if (!$workCenter) {
            throw new InvalidArgumentException("Work Center #{$workCenterId} does not exist or does not belong to this tenant.");
        }

        // 2. Validate Employee
        $employee = Employee::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($employeeId);

        if (!$employee) {
            throw new InvalidArgumentException("Employee #{$employeeId} does not exist or does not belong to this tenant.");
        }

        // 3. Section validation if sectionId provided
        $section = null;
        if ($sectionId !== null) {
            $section = WorkCenterSection::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('work_center_id', $workCenterId)
                ->find($sectionId);

            if (!$section) {
                throw new InvalidArgumentException("Section #{$sectionId} does not exist or does not belong to Work Center #{$workCenterId}.");
            }
        }

        if ($active) {
            if ($sectionId !== null) {
                // Section-level assignment:
                // Rule: Mutual exclusivity - Cannot have active Work Center-level assignment
                $activeWcAssignment = WorkCenterEmployeeAssignment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $employeeId)
                    ->whereNull('section_id')
                    ->where('active', true)
                    ->with('workCenter')
                    ->first();

                if ($activeWcAssignment) {
                    $wcName = $activeWcAssignment->workCenter?->name ?? "#{$activeWcAssignment->work_center_id}";
                    throw new InvalidArgumentException(
                        "Employee '{$employee->full_name}' is actively assigned to Work Center '{$wcName}'. Mutual exclusivity prevents holding both Work Center-level and Section-level assignments."
                    );
                }

                // Rule: Section exclusivity - Cannot be assigned to another Section
                $activeSecAssignment = WorkCenterEmployeeAssignment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $employeeId)
                    ->whereNotNull('section_id')
                    ->where('section_id', '!=', $sectionId)
                    ->where('active', true)
                    ->with('section')
                    ->first();

                if ($activeSecAssignment) {
                    $otherSec = $activeSecAssignment->section?->name ?? "#{$activeSecAssignment->section_id}";
                    throw new InvalidArgumentException(
                        "Employee '{$employee->full_name}' is already actively assigned to Section '{$otherSec}'. An employee assigned to a Section cannot be assigned to any other Section."
                    );
                }
            } else {
                // Work Center-level assignment:
                // Rule: Mutual exclusivity - Cannot have active Section-level assignment
                $activeSecAssignment = WorkCenterEmployeeAssignment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $employeeId)
                    ->whereNotNull('section_id')
                    ->where('active', true)
                    ->with('section')
                    ->first();

                if ($activeSecAssignment) {
                    $secName = $activeSecAssignment->section?->name ?? "#{$activeSecAssignment->section_id}";
                    throw new InvalidArgumentException(
                        "Employee '{$employee->full_name}' is actively assigned to Section '{$secName}'. Mutual exclusivity prevents holding both Work Center-level and Section-level assignments."
                    );
                }

                // Rule: Multi Work Center allowed! Do not reject if assigned to other Work Centers.
            }
        }

        // 4. Duplicate assignment check on same target (Work Center or Section)
        $existingQuery = WorkCenterEmployeeAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('work_center_id', $workCenterId)
            ->where('employee_id', $employeeId);

        if ($sectionId !== null) {
            $existingQuery->where('section_id', $sectionId);
        } else {
            $existingQuery->whereNull('section_id');
        }

        $existing = $existingQuery->first();

        if ($existing) {
            if ($existing->active && $active) {
                $targetName = $sectionId !== null ? "Section '{$section->name}'" : "this Work Center";
                throw new InvalidArgumentException(
                    "Employee '{$employee->full_name}' is already assigned to {$targetName}."
                );
            }

            $existing->update(['active' => $active]);
            return $existing->fresh(['workCenter', 'section', 'employee']);
        }

        return WorkCenterEmployeeAssignment::create([
            'tenant_id'      => $tenantId,
            'work_center_id' => $workCenterId,
            'section_id'     => $sectionId,
            'employee_id'    => $employeeId,
            'active'         => $active,
        ]);
    }

    public function setActive(int $id, bool $active, int $tenantId): WorkCenterEmployeeAssignment
    {
        $assignment = WorkCenterEmployeeAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$assignment) {
            throw new InvalidArgumentException("Employee assignment #{$id} not found for this tenant.");
        }

        if ($active) {
            if ($assignment->section_id === null) {
                // Reactivating Work Center-level assignment:
                // Rule: Mutual exclusivity with active Section-level assignments
                $conflictingSec = WorkCenterEmployeeAssignment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $assignment->employee_id)
                    ->whereNotNull('section_id')
                    ->where('active', true)
                    ->with('section')
                    ->first();

                if ($conflictingSec) {
                    $secName = $conflictingSec->section?->name ?? "#{$conflictingSec->section_id}";
                    throw new InvalidArgumentException(
                        "Cannot reactivate assignment: Employee is actively assigned to Section '{$secName}'. Mutual exclusivity prevents holding both Work Center-level and Section-level assignments."
                    );
                }

                // Check duplicate active assignment on the same Work Center
                $duplicateWc = WorkCenterEmployeeAssignment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $assignment->employee_id)
                    ->where('work_center_id', $assignment->work_center_id)
                    ->whereNull('section_id')
                    ->where('id', '!=', $id)
                    ->where('active', true)
                    ->first();

                if ($duplicateWc) {
                    throw new InvalidArgumentException(
                        "Cannot reactivate assignment: Employee is already actively assigned to this Work Center."
                    );
                }
            } else {
                // Reactivating Section-level assignment:
                // Rule: Mutual exclusivity with active Work Center-level assignments
                $conflictingWc = WorkCenterEmployeeAssignment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $assignment->employee_id)
                    ->whereNull('section_id')
                    ->where('active', true)
                    ->with('workCenter')
                    ->first();

                if ($conflictingWc) {
                    $wcName = $conflictingWc->workCenter?->name ?? "#{$conflictingWc->work_center_id}";
                    throw new InvalidArgumentException(
                        "Cannot reactivate assignment: Employee is actively assigned to Work Center '{$wcName}'. Mutual exclusivity prevents holding both Work Center-level and Section-level assignments."
                    );
                }

                // Rule: Section exclusivity - Cannot be assigned to another Section
                $conflictingSec = WorkCenterEmployeeAssignment::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $assignment->employee_id)
                    ->whereNotNull('section_id')
                    ->where('id', '!=', $id)
                    ->where('active', true)
                    ->with('section')
                    ->first();

                if ($conflictingSec) {
                    $secName = $conflictingSec->section?->name ?? "#{$conflictingSec->section_id}";
                    throw new InvalidArgumentException(
                        "Cannot reactivate assignment: Employee is already actively assigned to Section '{$secName}'."
                    );
                }
            }
        }

        $assignment->update(['active' => $active]);

        return $assignment->fresh(['workCenter', 'section', 'employee']);
    }

    public function remove(int $id, int $tenantId): bool
    {
        $assignment = WorkCenterEmployeeAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($id);

        if (!$assignment) {
            throw new InvalidArgumentException("Employee assignment #{$id} not found for this tenant.");
        }

        return (bool) $assignment->delete();
    }

    public function getAssignments(int $workCenterId, int $tenantId): Collection
    {
        return WorkCenterEmployeeAssignment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('work_center_id', $workCenterId)
            ->with(['employee'])
            ->orderByDesc('active')
            ->orderBy('id')
            ->get();
    }
}
