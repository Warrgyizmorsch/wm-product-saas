<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\WorkCenterSectionDTO;
use App\Domains\Production\Models\ExternalWorkerAllocation;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterEmployeeAssignment;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Repositories\WorkCenterSectionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkCenterSectionService
{
    public function __construct(
        private readonly WorkCenterSectionRepositoryInterface $repository,
        private readonly SectionCalculationService $calculationService,
    ) {}

    public function create(WorkCenterSectionDTO $dto, int $tenantId): WorkCenterSection
    {
        // Validate parent work center belongs to tenant
        $workCenter = WorkCenter::find($dto->work_center_id);
        if (!$workCenter || $workCenter->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Invalid work center selected.');
        }

        // Validate unique code within tenant and work center scope
        if ($this->repository->findByCode($dto->code, $dto->work_center_id, $tenantId)) {
            throw new InvalidArgumentException(
                "A section with code '{$dto->code}' already exists in this work center."
            );
        }

        // Validate at least one shift assigned
        if (empty($dto->shift_ids)) {
            throw new InvalidArgumentException('Every section must have at least one assigned shift.');
        }

        return DB::transaction(function () use ($dto, $tenantId, $workCenter) {
            $data = array_merge($dto->toArray(), ['tenant_id' => $tenantId]);
            if (!array_key_exists('supports_manual_production', $data)) {
                $data['supports_manual_production'] = false;
                $data['manual_capacity_per_hour'] = null;
            }
            $section = $this->repository->create($data);
            $this->repository->syncShifts($section, $dto->shift_ids, $tenantId);

            if ($dto->machine_ids !== null) {
                $this->validateMachinesForAssignment($dto->machine_ids, $tenantId);
                $this->repository->syncMachines($section, $dto->machine_ids, $tenantId);
            }

            $fresh = $section->fresh(['workCenter', 'machines']);
            $calculated = $this->calculationService->calculateAndSave($fresh);

            if ($workCenter) {
                app(WorkCenterCalculationService::class)->calculateAndSave($workCenter);
            }

            return $calculated;
        });
    }

    public function update(int $id, WorkCenterSectionDTO $dto): WorkCenterSection
    {
        $section = $this->repository->find($id);
        if (!$section) {
            throw new InvalidArgumentException('Work center section not found.');
        }

        // Validate parent work center belongs to tenant
        $workCenter = WorkCenter::find($dto->work_center_id);
        if (!$workCenter || $workCenter->tenant_id !== $section->tenant_id) {
            throw new InvalidArgumentException('Invalid work center selected.');
        }

        // Validate unique code within tenant and work center scope (excluding self)
        $existing = $this->repository->findByCode($dto->code, $dto->work_center_id, $section->tenant_id, $id);
        if ($existing) {
            throw new InvalidArgumentException(
                "A section with code '{$dto->code}' already exists in this work center."
            );
        }

        // Validate at least one shift assigned
        if (empty($dto->shift_ids)) {
            throw new InvalidArgumentException('Every section must have at least one assigned shift.');
        }

        return DB::transaction(function () use ($id, $dto, $section, $workCenter) {
            $updated = $this->repository->update($id, $dto->toArray());
            $this->repository->syncShifts($updated, $dto->shift_ids, $section->tenant_id);

            if ($dto->machine_ids !== null) {
                $this->validateMachinesForAssignment($dto->machine_ids, $section->tenant_id, $section->id);
                $this->repository->syncMachines($updated, $dto->machine_ids, $section->tenant_id);
            }

            $fresh = $updated->fresh(['workCenter', 'machines']);
            $calculated = $this->calculationService->calculateAndSave($fresh);

            if ($workCenter) {
                app(WorkCenterCalculationService::class)->calculateAndSave($workCenter);
            }

            return $calculated;
        });
    }

    public function recalculate(WorkCenterSection $section): WorkCenterSection
    {
        $fresh = $section->fresh(['workCenter', 'machines']);
        $calculated = $this->calculationService->calculateAndSave($fresh);
        if ($fresh->workCenter) {
            app(WorkCenterCalculationService::class)->calculateAndSave($fresh->workCenter);
        }
        return $calculated;
    }

    public function updateManualProduction(int $id, bool $supportsManual, ?float $capacity): WorkCenterSection
    {
        $section = $this->repository->find($id);
        if (!$section) {
            throw new InvalidArgumentException('Work center section not found.');
        }

        if ($supportsManual) {
            if (!$section->hasActiveWorkerAssignments()) {
                throw new InvalidArgumentException(
                    'Cannot enable manual production: Section has no active internal employees or external worker allocations.'
                );
            }
            if ($capacity === null || $capacity <= 0) {
                throw new InvalidArgumentException(
                    'Manual capacity per hour must be greater than zero when manual production is enabled.'
                );
            }
        } else {
            $capacity = null;
        }

        return DB::transaction(function () use ($id, $supportsManual, $capacity, $section) {
            $updated = $this->repository->update($id, [
                'supports_manual_production' => $supportsManual,
                'manual_capacity_per_hour'   => $capacity,
            ]);

            $fresh = $updated->fresh(['workCenter', 'machines']);
            $calculated = $this->calculationService->calculateAndSave($fresh);

            if ($fresh->workCenter) {
                app(WorkCenterCalculationService::class)->calculateAndSave($fresh->workCenter);
            }

            return $calculated;
        });
    }

    public function delete(int $id): bool
    {
        $section = $this->repository->find($id);
        if (!$section) {
            throw new InvalidArgumentException('Work center section not found.');
        }

        // Rule: Permanent block if section has recorded production or routing history
        if ($section->hasProductionHistory()) {
            throw new InvalidArgumentException(
                "Cannot delete Section '{$section->name}': This section has recorded production or routing history. Sections with manufacturing history cannot be deleted; deactivate the section instead."
            );
        }

        $workCenter = $section->workCenter;

        return DB::transaction(function () use ($id, $section, $workCenter) {
            // 1. Unassign all machines so they return to the unassigned machine pool
            Machine::where('section_id', $section->id)->update(['section_id' => null]);

            // 2. Delete internal employee assignments for this section (soft delete)
            WorkCenterEmployeeAssignment::where('section_id', $section->id)->delete();

            // 3. Delete external worker allocations for this section (soft delete)
            ExternalWorkerAllocation::where('section_id', $section->id)->delete();

            // 4. Delete the section (soft delete)
            $deleted = $this->repository->delete($id);

            // 5. Recalculate parent work center
            if ($deleted && $workCenter) {
                app(WorkCenterCalculationService::class)->calculateAndSave($workCenter);
            }

            return (bool) $deleted;
        });
    }

    private function validateMachinesForAssignment(array $machineIds, int $tenantId, ?int $currentSectionId = null): void
    {
        if (empty($machineIds)) {
            return;
        }

        $uniqueIds = array_values(array_unique($machineIds));

        $machines = \App\Domains\Production\Models\Machine::where('tenant_id', $tenantId)
            ->whereIn('id', $uniqueIds)
            ->lockForUpdate()
            ->get();

        if ($machines->count() !== count($uniqueIds)) {
            throw new InvalidArgumentException('One or more selected machines do not exist or belong to another organization.');
        }

        foreach ($machines as $machine) {
            if ($machine->isDecommissioned()) {
                throw new InvalidArgumentException("Machine '{$machine->name}' is decommissioned and cannot be assigned to a section.");
            }

            if ($machine->section_id !== null && $machine->section_id !== $currentSectionId) {
                $otherSection = $machine->section;
                $otherName = $otherSection ? $otherSection->name : "Section #{$machine->section_id}";
                throw new InvalidArgumentException("Machine '{$machine->name}' is already assigned to {$otherName}.");
            }
        }
    }
}
