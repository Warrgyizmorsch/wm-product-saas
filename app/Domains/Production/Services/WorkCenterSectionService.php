<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\WorkCenterSectionDTO;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Repositories\WorkCenterSectionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkCenterSectionService
{
    public function __construct(
        private readonly WorkCenterSectionRepositoryInterface $repository
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

        // Validate shifts are a subset of parent work center's assigned shifts
        $this->validateShiftsBelongToWorkCenter($workCenter, $dto->shift_ids);

        return DB::transaction(function () use ($dto, $tenantId) {
            $data = array_merge($dto->toArray(), ['tenant_id' => $tenantId]);
            $section = $this->repository->create($data);
            $this->repository->syncShifts($section, $dto->shift_ids, $tenantId, $dto->shift_workers);

            if ($dto->machine_ids !== null) {
                $this->validateMachinesForAssignment($dto->machine_ids, $tenantId);
                $this->repository->syncMachines($section, $dto->machine_ids, $tenantId);
            }

            return $section->fresh(['workCenter', 'machines', 'shifts']);
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

        // Validate shifts are a subset of parent work center's assigned shifts
        $this->validateShiftsBelongToWorkCenter($workCenter, $dto->shift_ids);

        return DB::transaction(function () use ($id, $dto, $section) {
            $updated = $this->repository->update($id, $dto->toArray());
            $this->repository->syncShifts($updated, $dto->shift_ids, $section->tenant_id, $dto->shift_workers);

            if ($dto->machine_ids !== null) {
                $this->validateMachinesForAssignment($dto->machine_ids, $section->tenant_id, $section->id);
                $this->repository->syncMachines($updated, $dto->machine_ids, $section->tenant_id);
            }

            return $updated->fresh(['workCenter', 'machines', 'shifts']);
        });
    }

    public function delete(int $id): bool
    {
        $section = $this->repository->find($id);
        if (!$section) {
            throw new InvalidArgumentException('Work center section not found.');
        }

        // Prevent deletion if section has assigned machines
        if ($section->machines()->exists()) {
            throw new InvalidArgumentException(
                'Cannot delete a section that has assigned machines. Reassign or remove machines first.'
            );
        }

        return DB::transaction(function () use ($id) {
            return $this->repository->delete($id);
        });
    }

    private function validateShiftsBelongToWorkCenter(WorkCenter $workCenter, array $shiftIds): void
    {
        $wcShiftIds = $workCenter->shifts()->pluck('production_shifts.id')->map(fn($v) => (int)$v)->toArray();
        $diff = array_diff($shiftIds, $wcShiftIds);

        if (!empty($diff)) {
            throw new InvalidArgumentException(
                'Section shifts must be selected from the shifts assigned to its parent work center.'
            );
        }
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
