<?php

namespace App\Domains\Production\Services;

use App\Domains\HRMS\Models\Asset;
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

    public function create(MachineDTO $dto, int $tenantId, ?int $assetId = null): Machine
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

        $data = array_merge($dto->toArray(), ['tenant_id' => $tenantId]);

        if ($assetId !== null) {
            $data['asset_id'] = $this->validateAssetForLinking($assetId, $tenantId);
        }

        return $this->repository->create($data);
    }

    /**
     * Link an existing Fixed Asset accounting record to an already-created
     * machine — the retroactive/manual path (historical data, or a category
     * flagged as production machinery after the asset was already purchased).
     */
    public function linkAsset(int $machineId, int $assetId, int $tenantId): Machine
    {
        $machine = $this->repository->find($machineId);
        if (!$machine || $machine->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Machine not found.');
        }

        $this->validateAssetForLinking($assetId, $tenantId, $machineId);

        return $this->repository->update($machineId, ['asset_id' => $assetId]);
    }

    public function unlinkAsset(int $machineId, int $tenantId): Machine
    {
        $machine = $this->repository->find($machineId);
        if (!$machine || $machine->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Machine not found.');
        }

        return $this->repository->update($machineId, ['asset_id' => null]);
    }

    /**
     * Shared guard for both create-with-asset and link-asset: the asset must
     * belong to the same tenant and not already be linked to a different
     * machine (one-directional, at-most-one-machine-per-asset invariant
     * enforced here rather than via a DB unique index, so a violation
     * surfaces as a clear validation message instead of a constraint error).
     */
    private function validateAssetForLinking(int $assetId, int $tenantId, ?int $excludeMachineId = null): int
    {
        $asset = Asset::find($assetId);
        if (!$asset || $asset->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Invalid asset selected.');
        }

        $alreadyLinked = Machine::where('asset_id', $assetId)
            ->when($excludeMachineId !== null, fn ($q) => $q->where('id', '!=', $excludeMachineId))
            ->exists();

        if ($alreadyLinked) {
            throw new InvalidArgumentException('This asset is already linked to another machine.');
        }

        return $assetId;
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
