<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\WorkCenterDTO;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Repositories\WorkCenterRepositoryInterface;
use InvalidArgumentException;

class WorkCenterService
{
    public function __construct(
        private readonly WorkCenterRepositoryInterface $repository
    ) {}

    public function create(WorkCenterDTO $dto, int $tenantId): WorkCenter
    {
        // Validate unique code per tenant
        if ($this->repository->findByCode($dto->code, $tenantId)) {
            throw new InvalidArgumentException(
                "A work center with code '{$dto->code}' already exists."
            );
        }

        return $this->repository->create(
            array_merge($dto->toArray(), ['tenant_id' => $tenantId])
        );
    }

    public function update(int $id, WorkCenterDTO $dto): WorkCenter
    {
        $workCenter = $this->repository->find($id);

        if (!$workCenter) {
            throw new InvalidArgumentException('Work center not found.');
        }

        // Validate unique code (excluding self)
        $existing = $this->repository->findByCode($dto->code, $workCenter->tenant_id, $id);
        if ($existing) {
            throw new InvalidArgumentException(
                "A work center with code '{$dto->code}' already exists."
            );
        }

        if ($dto->parent_id) {
            if ($dto->parent_id === $id) {
                throw new \InvalidArgumentException('A work center cannot be its own parent.');
            }
            $parent = $this->repository->find($dto->parent_id);
            while ($parent) {
                if ($parent->id === $id) {
                    throw new \InvalidArgumentException('Circular work center hierarchy cycle detected.');
                }
                $parent = $parent->parent_id ? $this->repository->find($parent->parent_id) : null;
            }
        }

        return $this->repository->update($id, $dto->toArray());
    }

    public function delete(int $id): bool
    {
        $workCenter = $this->repository->find($id);

        if (!$workCenter) {
            throw new InvalidArgumentException('Work center not found.');
        }

        // Prevent deletion if work center has active routing operations
        if ($workCenter->operations()->exists()) {
            throw new InvalidArgumentException(
                'Cannot delete a work center that is used in routing operations. Deactivate it instead.'
            );
        }

        return $this->repository->delete($id);
    }
}
