<?php

namespace App\Domains\Production\Services;

use App\Domains\HRMS\Models\Employee;
use App\Domains\Production\Models\ProductionShift;
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

            if (!$section->isActive()) {
                throw new InvalidArgumentException("Cannot assign employee: Section '{$section->name}' is inactive.");
            }
        }

        // 4. Shift & Section Requirements Validation
        if ($active) {
            if ($sectionId !== null) {
                // Section-Level Assignment:
                // Must validate employee predefined master shift against Section operating shifts (partial allocations restricted)
                $sectionShifts = $section->shifts->filter(fn($s) => (bool) ($s->active ?? true));
                $this->validateEmployeeShiftCompatibility(
                    $employee,
                    $sectionShifts,
                    "Section '{$section->name}'",
                    true
                );
            } else {
                // Work Center-Level Assignment:
                // Implied Rule: Work Center shifts are derived from its active sections.
                // An employee cannot be assigned at the Work Center level without at least one active section with operating shifts.
                $activeSections = $workCenter->sections()->where('status', 'active')->get();
                if ($activeSections->isEmpty()) {
                    throw new InvalidArgumentException(
                        "Cannot assign employee at Work Center level: Work Center has no active sections. Shifts are derived from active sections, so at least one active section with operating shifts is required."
                    );
                }

                $effectiveShifts = $workCenter->getEffectiveShifts(true);
                if ($effectiveShifts->isEmpty()) {
                    throw new InvalidArgumentException(
                        "Cannot assign employee at Work Center level: None of the active sections in Work Center '{$workCenter->name}' have operating shifts assigned."
                    );
                }

                $this->validateEmployeeShiftCompatibility(
                    $employee,
                    $effectiveShifts,
                    "Work Center '{$workCenter->name}'",
                    false
                );
            }

            // Exclusivity Rules
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
            ->withTrashed()
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
            if ($existing->trashed()) {
                $existing->restore();
                $existing->update(['active' => $active]);
                return $existing->fresh(['workCenter', 'section', 'employee']);
            }

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
            $employee = Employee::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($assignment->employee_id);

            if (!$employee) {
                throw new InvalidArgumentException("Employee #{$assignment->employee_id} not found.");
            }

            if ($assignment->section_id === null) {
                // Reactivating Work Center-level assignment:
                $workCenter = WorkCenter::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->find($assignment->work_center_id);

                if (!$workCenter) {
                    throw new InvalidArgumentException("Work Center #{$assignment->work_center_id} not found.");
                }

                $activeSections = $workCenter->sections()->where('status', 'active')->get();
                if ($activeSections->isEmpty()) {
                    throw new InvalidArgumentException(
                        "Cannot reactivate assignment: Work Center has no active sections. Shifts are derived from active sections."
                    );
                }

                $effectiveShifts = $workCenter->getEffectiveShifts(true);
                if ($effectiveShifts->isEmpty()) {
                    throw new InvalidArgumentException(
                        "Cannot reactivate assignment: None of the active sections in Work Center '{$workCenter->name}' have operating shifts assigned."
                    );
                }

                $this->validateEmployeeShiftCompatibility(
                    $employee,
                    $effectiveShifts,
                    "Work Center '{$workCenter->name}'",
                    false
                );

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
                $section = WorkCenterSection::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('work_center_id', $assignment->work_center_id)
                    ->find($assignment->section_id);

                if (!$section || !$section->isActive()) {
                    throw new InvalidArgumentException("Cannot reactivate assignment: Assigned Section is inactive or not found.");
                }

                $sectionShifts = $section->shifts->filter(fn($s) => (bool) ($s->active ?? true));
                $this->validateEmployeeShiftCompatibility(
                    $employee,
                    $sectionShifts,
                    "Section '{$section->name}'",
                    true
                );

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

    // ─── Shift Compatibility & Overlap Calculation Helpers ────────────────────

    /**
     * Parse a time string (HH:MM or HH:MM:SS) into minutes of the day (0..1439).
     */
    public function parseTimeToMinutes(?string $time): int
    {
        if (empty($time)) {
            return 0;
        }

        $parts = explode(':', trim($time));
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);

        return (($hours % 24) * 60) + ($minutes % 60);
    }

    /**
     * Calculate the operational duration of a shift in minutes.
     * Handles overnight shifts (e.g. 22:00 to 06:00) and 24-hour shifts.
     */
    public function calculateShiftDurationMinutes(ProductionShift $shift): int
    {
        $start = $this->parseTimeToMinutes($shift->start_time);
        $end = $this->parseTimeToMinutes($shift->end_time);

        if ($end <= $start) {
            $duration = (1440 - $start) + $end;
            return $duration === 0 ? 1440 : $duration;
        }

        return $end - $start;
    }

    /**
     * Calculate overlapping operational minutes between two shifts in a 24-hour cycle.
     */
    public function calculateShiftOverlapMinutes(ProductionShift $shiftA, ProductionShift $shiftB): int
    {
        $startA = $this->parseTimeToMinutes($shiftA->start_time);
        $endA = $this->parseTimeToMinutes($shiftA->end_time);
        $isOverA = ($endA <= $startA);

        $startB = $this->parseTimeToMinutes($shiftB->start_time);
        $endB = $this->parseTimeToMinutes($shiftB->end_time);
        $isOverB = ($endB <= $startB);

        $overlap = 0;
        for ($m = 0; $m < 1440; $m++) {
            $inA = $isOverA ? ($m >= $startA || $m < $endA) : ($m >= $startA && $m < $endA);
            $inB = $isOverB ? ($m >= $startB || $m < $endB) : ($m >= $startB && $m < $endB);

            if ($inA && $inB) {
                $overlap++;
            }
        }

        return $overlap;
    }

    /**
     * Evaluate shift compatibility between an employee's predefined master shift and target shifts.
     *
     * Rules:
     * 1. Predefined Shift ID Match: If the employee's shift ID matches ANY target shift => Exact Match (Fully Compatible).
     * 2. Operating Hours Coverage:
     *    - Target operating minutes is the union across all active target shifts in a 24-hour cycle.
     *    - Zero Overlap (overlap == 0) => Incompatible.
     *    - Section-Level (isSectionLevel == true):
     *        Partial allocation is RESTRICTED: Employee shift hours must lie completely within Section operating hours (idle_minutes == 0).
     *    - Work Center-Level (isSectionLevel == false):
     *        Partial allocation is ALLOWED: As long as there is active operational overlap (overlap > 0), compatible.
     */
    public function evaluateShiftCompatibility(
        ProductionShift $empShift,
        $targetShifts,
        bool $isSectionLevel = true
    ): array {
        if ($targetShifts->isEmpty()) {
            return [
                'compatible'      => false,
                'status'          => 'no_target_shifts',
                'label'           => 'No Operating Shifts',
                'overlap_minutes' => 0,
                'idle_minutes'    => 0,
                'reason'          => 'No operating shifts configured.',
            ];
        }

        // 1. Exact Shift ID Match
        foreach ($targetShifts as $tShift) {
            if ((int) $empShift->id === (int) $tShift->id) {
                $duration = $this->calculateShiftDurationMinutes($empShift);
                return [
                    'compatible'      => true,
                    'status'          => 'compatible',
                    'label'           => 'Exact Shift Match',
                    'overlap_minutes' => $duration,
                    'idle_minutes'    => 0,
                    'reason'          => "Predefined master shift '{$empShift->name}' matches assigned operating shift.",
                ];
            }
        }

        // 2. Build target operational minutes map (union across all active target shifts in a 24h cycle)
        $targetOperational = array_fill(0, 1440, false);
        foreach ($targetShifts as $tShift) {
            $s = $this->parseTimeToMinutes($tShift->start_time);
            $e = $this->parseTimeToMinutes($tShift->end_time);
            if ($e <= $s) {
                for ($m = $s; $m < 1440; $m++) {
                    $targetOperational[$m] = true;
                }
                for ($m = 0; $m < $e; $m++) {
                    $targetOperational[$m] = true;
                }
            } else {
                for ($m = $s; $m < $e; $m++) {
                    $targetOperational[$m] = true;
                }
            }
        }

        // 3. Build employee shift minutes list
        $empStart = $this->parseTimeToMinutes($empShift->start_time);
        $empEnd = $this->parseTimeToMinutes($empShift->end_time);
        $empMinutes = [];
        if ($empEnd <= $empStart) {
            for ($m = $empStart; $m < 1440; $m++) {
                $empMinutes[] = $m;
            }
            for ($m = 0; $m < $empEnd; $m++) {
                $empMinutes[] = $m;
            }
        } else {
            for ($m = $empStart; $m < $empEnd; $m++) {
                $empMinutes[] = $m;
            }
        }

        $overlapMinutes = 0;
        $idleMinutes = 0;

        foreach ($empMinutes as $m) {
            if ($targetOperational[$m]) {
                $overlapMinutes++;
            } else {
                $idleMinutes++;
            }
        }

        $empStartStr = substr((string) $empShift->start_time, 0, 5);
        $empEndStr = substr((string) $empShift->end_time, 0, 5);

        // Zero overlap is never compatible
        if ($overlapMinutes === 0) {
            return [
                'compatible'      => false,
                'status'          => 'incompatible',
                'label'           => "Shift Mismatch: No Overlap ({$empStartStr}-{$empEndStr})",
                'overlap_minutes' => 0,
                'idle_minutes'    => $idleMinutes,
                'reason'          => "Zero operational overlap between employee shift '{$empShift->name}' ({$empStartStr}-{$empEndStr}) and operating hours.",
            ];
        }

        // Section Level: Partial allocation is restricted
        if ($isSectionLevel) {
            if ($idleMinutes > 0) {
                return [
                    'compatible'      => false,
                    'status'          => 'incompatible',
                    'label'           => "Shift Mismatch: Closed for {$idleMinutes}m ({$empStartStr}-{$empEndStr})",
                    'overlap_minutes' => $overlapMinutes,
                    'idle_minutes'    => $idleMinutes,
                    'reason'          => "Partial allocation restricted at Section level: Section is not operating for {$idleMinutes} minutes of employee shift '{$empShift->name}' ({$empStartStr}-{$empEndStr}). Employee shift hours must lie completely within Section operating hours.",
                ];
            }

            return [
                'compatible'      => true,
                'status'          => 'compatible',
                'label'           => "Shift Covered ({$empStartStr}-{$empEndStr})",
                'overlap_minutes' => $overlapMinutes,
                'idle_minutes'    => 0,
                'reason'          => "Employee shift hours lie completely within Section operating hours.",
            ];
        }

        // Work Center Level: Partial allocation is allowed as long as overlap > 0
        if ($idleMinutes > 0) {
            return [
                'compatible'      => true,
                'status'          => 'compatible',
                'label'           => "Partial WC Overlap ({$overlapMinutes}m)",
                'overlap_minutes' => $overlapMinutes,
                'idle_minutes'    => $idleMinutes,
                'reason'          => "Compatible with partial Work Center allocation ({$overlapMinutes} min active overlap).",
            ];
        }

        return [
            'compatible'      => true,
            'status'          => 'compatible',
            'label'           => "Fully Covered ({$empStartStr}-{$empEndStr})",
            'overlap_minutes' => $overlapMinutes,
            'idle_minutes'    => 0,
            'reason'          => "Employee shift hours lie completely within Work Center operating hours.",
        ];
    }

    /**
     * Check if an employee's master predefined shift is compatible with a single target shift.
     */
    public function isShiftCompatible(ProductionShift $empShift, ProductionShift $targetShift, bool $isSectionLevel = true): array
    {
        return $this->evaluateShiftCompatibility($empShift, collect([$targetShift]), $isSectionLevel);
    }

    /**
     * Validate that an employee's predefined master shift is compatible with target shifts.
     * Throws InvalidArgumentException if incompatible.
     *
     * @param Employee $employee
     * @param \Illuminate\Support\Collection<int, ProductionShift>|Collection $targetShifts
     * @param string $targetDescription
     * @param bool $isSectionLevel
     */
    public function validateEmployeeShiftCompatibility(
        Employee $employee,
        $targetShifts,
        string $targetDescription,
        bool $isSectionLevel = true
    ): void {
        $empShift = $employee->shift;
        if (!$empShift || !$empShift->isActive()) {
            throw new InvalidArgumentException(
                "Employee '{$employee->full_name}' has no active predefined master shift in HRMS. An active master shift is required for operational assignment."
            );
        }

        if ($targetShifts->isEmpty()) {
            throw new InvalidArgumentException(
                "Cannot assign employee: {$targetDescription} has no active operating shifts assigned."
            );
        }

        $result = $this->evaluateShiftCompatibility($empShift, $targetShifts, $isSectionLevel);

        if (!$result['compatible']) {
            throw new InvalidArgumentException("Shift Incompatible: {$result['reason']}");
        }
    }

    /**
     * Helper to test if an employee is compatible with a given target shifts collection.
     *
     * @param Employee $employee
     * @param \Illuminate\Support\Collection<int, ProductionShift>|Collection $targetShifts
     * @param bool $isSectionLevel
     */
    public function isEmployeeCompatibleWithShifts(Employee $employee, $targetShifts, bool $isSectionLevel = true): bool
    {
        $empShift = $employee->shift;
        if (!$empShift || !$empShift->isActive() || $targetShifts->isEmpty()) {
            return false;
        }

        $res = $this->evaluateShiftCompatibility($empShift, $targetShifts, $isSectionLevel);
        return $res['compatible'];
    }

    /**
     * Get a human-readable compatibility status and label for an employee.
     *
     * @param Employee $employee
     * @param \Illuminate\Support\Collection<int, ProductionShift>|Collection $targetShifts
     * @param bool $isSectionLevel
     */
    public function getShiftCompatibilityStatus(Employee $employee, $targetShifts, bool $isSectionLevel = true): array
    {
        $empShift = $employee->shift;
        if (!$empShift) {
            return [
                'compatible' => false,
                'status'     => 'no_shift',
                'label'      => 'No Predefined Shift',
                'reason'     => 'Employee has no master shift assigned in HRMS.',
            ];
        }

        if (!$empShift->isActive()) {
            return [
                'compatible' => false,
                'status'     => 'inactive_shift',
                'label'      => 'Inactive Master Shift',
                'reason'     => 'Employee master shift is inactive.',
            ];
        }

        if ($targetShifts->isEmpty()) {
            return [
                'compatible' => false,
                'status'     => 'no_target_shifts',
                'label'      => 'No Operating Shifts',
                'reason'     => 'No operating shifts configured.',
            ];
        }

        return $this->evaluateShiftCompatibility($empShift, $targetShifts, $isSectionLevel);
    }
}
