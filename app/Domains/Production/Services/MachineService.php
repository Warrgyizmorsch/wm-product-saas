<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\MachineDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Repositories\MachineRepositoryInterface;
use InvalidArgumentException;

class MachineService
{
    public function __construct(
        private readonly MachineRepositoryInterface $repository
    ) {}

    public function create(MachineDTO $dto, int $tenantId): Machine
    {
        // Validate work center belongs to tenant
        $workCenter = WorkCenter::find($dto->work_center_id);
        if (!$workCenter || $workCenter->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Invalid work center selected.');
        }

        // Validate unique code per tenant
        if ($this->repository->findByCode($dto->code, $tenantId)) {
            throw new InvalidArgumentException(
                "A machine with code '{$dto->code}' already exists."
            );
        }

        return $this->repository->create(
            array_merge($dto->toArray(), ['tenant_id' => $tenantId])
        );
    }

    public function update(int $id, MachineDTO $dto): Machine
    {
        $machine = $this->repository->find($id);

        if (!$machine) {
            throw new InvalidArgumentException('Machine not found.');
        }

        // Validate work center belongs to same tenant
        $workCenter = WorkCenter::find($dto->work_center_id);
        if (!$workCenter || $workCenter->tenant_id !== $machine->tenant_id) {
            throw new InvalidArgumentException('Invalid work center selected.');
        }

        // Validate unique code (excluding self)
        $existing = $this->repository->findByCode($dto->code, $machine->tenant_id, $id);
        if ($existing) {
            throw new InvalidArgumentException(
                "A machine with code '{$dto->code}' already exists."
            );
        }

        return $this->repository->update($id, $dto->toArray());
    }

    public function delete(int $id): bool
    {
        $machine = $this->repository->find($id);

        if (!$machine) {
            throw new InvalidArgumentException('Machine not found.');
        }

        // Prevent deletion if machine is assigned to routing operations
        if ($machine->operations()->exists()) {
            throw new InvalidArgumentException(
                'Cannot delete a machine that is referenced in routing operations. Set it to Inactive or Decommissioned instead.'
            );
        }

        return $this->repository->delete($id);
    }

    /**
     * Q5: AJAX endpoint data — get machines for a specific work center.
     */
    public function getMachinesForWorkCenter(int $workCenterId, bool $activeOnly = true): array
    {
        $machines = $this->repository->getByWorkCenter($workCenterId, $activeOnly);

        return $machines->map(fn (Machine $m) => [
            'id'     => $m->id,
            'name'   => $m->name,
            'code'   => $m->code,
            'status' => $m->status,
            'label'  => "{$m->name} ({$m->code})",
        ])->values()->toArray();
    }
}
