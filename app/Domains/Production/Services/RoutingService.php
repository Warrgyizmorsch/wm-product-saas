<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\RoutingDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Repositories\RoutingRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RoutingService
{
    public function __construct(
        private readonly RoutingRepositoryInterface $routingRepository,
        private readonly RoutingNumberService $numberService
    ) {}

    /**
     * Create a new routing draft.
     */
    public function create(RoutingDTO $dto, int $tenantId, ?int $creatorUserId = null): Routing
    {
        // Resolve routing number
        $routingNumber = trim($dto->routing_number ?? '');
        if (empty($routingNumber) || strtoupper($routingNumber) === 'AUTO') {
            $routingNumber = $this->numberService->generateNextNumber($tenantId);
        } else {
            if (!$this->numberService->validateNumber($routingNumber)) {
                throw new InvalidArgumentException('Routing number format is invalid.');
            }
            if ($this->numberService->isDuplicate($routingNumber, $tenantId)) {
                throw new InvalidArgumentException("A routing with number '{$routingNumber}' already exists.");
            }
        }

        // Check version conflicts for this product
        $this->checkRoutingConflicts($dto->product_id, $dto->version, $tenantId);

        return DB::transaction(function () use ($dto, $routingNumber, $tenantId, $creatorUserId) {
            $routing = $this->routingRepository->create(array_merge($dto->toArray(), [
                'tenant_id'      => $tenantId,
                'routing_number' => $routingNumber,
                'status'         => Routing::STATUS_DRAFT,
                'created_by'     => $creatorUserId,
                'revision'       => 0,
            ]));

            $this->syncOperations($routing, $dto->operations, $tenantId);

            RoutingApproval::create([
                'tenant_id'  => $tenantId,
                'routing_id' => $routing->id,
                'user_id'    => $creatorUserId,
                'action'     => RoutingApproval::ACTION_CREATED,
                'comments'   => 'Initial routing draft created.',
            ]);

            event(new \App\Domains\Production\Events\RoutingCreated($routing));

            return $routing;
        });
    }

    /**
     * Update a draft routing.
     */
    public function update(int $id, RoutingDTO $dto): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if (!$routing->isEditable()) {
            throw new InvalidArgumentException(
                'Only draft routings can be edited. Approved routings are read-only.'
            );
        }

        $this->checkRoutingConflicts($dto->product_id, $dto->version, $routing->tenant_id, $id);

        return DB::transaction(function () use ($routing, $dto) {
            $this->routingRepository->update($routing->id, $dto->toArray());
            $routing->refresh();

            $this->syncOperations($routing, $dto->operations, $routing->tenant_id);

            return $routing->load('operations');
        });
    }

    /**
     * Submit routing for approval.
     */
    public function submitApproval(int $id, int $userId): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if (!$routing->isDraft()) {
            throw new InvalidArgumentException('Only draft routings can be submitted for approval.');
        }

        if ($routing->operations()->count() === 0) {
            throw new InvalidArgumentException('Routing must have at least one operation before submitting for approval.');
        }

        return DB::transaction(function () use ($routing, $userId) {
            $this->routingRepository->update($routing->id, ['status' => Routing::STATUS_PENDING_APPROVAL]);

            RoutingApproval::create([
                'tenant_id'  => $routing->tenant_id,
                'routing_id' => $routing->id,
                'user_id'    => $userId,
                'action'     => RoutingApproval::ACTION_SUBMITTED,
                'comments'   => 'Submitted for engineering and production manager approval.',
            ]);

            return $routing->fresh();
        });
    }

    /**
     * Approve a pending routing. Makes it active and archives old active version.
     */
    public function approve(int $id, int $approverUserId): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if (!$routing->isPendingApproval()) {
            throw new InvalidArgumentException('Only routings pending approval can be approved.');
        }

        return DB::transaction(function () use ($routing, $approverUserId) {
            // Archive previously active routings for the same product
            Routing::withoutGlobalScopes()
                ->where('tenant_id', $routing->tenant_id)
                ->where('product_id', $routing->product_id)
                ->where('status', Routing::STATUS_ACTIVE)
                ->where('id', '!=', $routing->id)
                ->update(['status' => Routing::STATUS_HISTORICAL]);

            // Activate this routing
            $this->routingRepository->update($routing->id, [
                'status'      => Routing::STATUS_ACTIVE,
                'approved_by' => $approverUserId,
                'approved_at' => Carbon::now(),
            ]);

            RoutingApproval::create([
                'tenant_id'  => $routing->tenant_id,
                'routing_id' => $routing->id,
                'user_id'    => $approverUserId,
                'action'     => RoutingApproval::ACTION_APPROVED,
                'comments'   => 'Routing approved and set as active manufacturing process.',
            ]);

            event(new \App\Domains\Production\Events\RoutingApproved($routing->fresh()));

            return $routing->fresh();
        });
    }

    /**
     * Reject a pending routing — returns to draft.
     */
    public function reject(int $id, int $userId, ?string $comments = null): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if (!$routing->isPendingApproval()) {
            throw new InvalidArgumentException('Only routings pending approval can be rejected.');
        }

        return DB::transaction(function () use ($routing, $userId, $comments) {
            $this->routingRepository->update($routing->id, ['status' => Routing::STATUS_DRAFT]);

            RoutingApproval::create([
                'tenant_id'  => $routing->tenant_id,
                'routing_id' => $routing->id,
                'user_id'    => $userId,
                'action'     => RoutingApproval::ACTION_REJECTED,
                'comments'   => $comments ?: 'Routing rejected during review.',
            ]);

            return $routing->fresh();
        });
    }

    /**
     * Cancel an active or pending routing.
     */
    public function cancel(int $id, int $userId, ?string $comments = null): Routing
    {
        $routing = $this->routingRepository->find($id);

        if (!$routing) {
            throw new InvalidArgumentException('Routing not found.');
        }

        if ($routing->isCancelled() || $routing->isHistorical()) {
            throw new InvalidArgumentException('This routing is already cancelled or historical.');
        }

        return DB::transaction(function () use ($routing, $userId, $comments) {
            $this->routingRepository->update($routing->id, ['status' => Routing::STATUS_CANCELLED]);

            RoutingApproval::create([
                'tenant_id'  => $routing->tenant_id,
                'routing_id' => $routing->id,
                'user_id'    => $userId,
                'action'     => RoutingApproval::ACTION_CANCELLED,
                'comments'   => $comments ?: 'Routing cancelled.',
            ]);

            return $routing->fresh();
        });
    }

    /**
     * Duplicate a routing as a new draft version.
     */
    public function duplicateVersion(int $id, string $newVersion, ?int $creatorUserId = null): Routing
    {
        $original = $this->routingRepository->getRoutingWithOperations($id);

        if (!$original) {
            throw new InvalidArgumentException('Source routing not found.');
        }

        $tenantId = $original->tenant_id;
        $this->checkRoutingConflicts($original->product_id, $newVersion, $tenantId);

        return DB::transaction(function () use ($original, $newVersion, $creatorUserId, $tenantId) {
            $newRouting = $this->routingRepository->create([
                'tenant_id'      => $tenantId,
                'routing_number' => $this->numberService->generateNextNumber($tenantId),
                'name'           => $original->name,
                'product_id'     => $original->product_id,
                'version'        => $newVersion,
                'revision'       => $original->revision + 1,
                'is_default'     => $original->is_default,
                'effective_from' => Carbon::now()->toDateString(),
                'effective_to'   => null,
                'status'         => Routing::STATUS_DRAFT,
                'description'    => "Duplicated from version {$original->version}. " . $original->description,
                'created_by'     => $creatorUserId,
            ]);

            // Copy all operations
            foreach ($original->operations as $op) {
                RoutingOperation::create(array_merge($op->toArray(), [
                    'id'         => null,
                    'tenant_id'  => $tenantId,
                    'routing_id' => $newRouting->id,
                    'created_at' => null,
                    'updated_at' => null,
                    'deleted_at' => null,
                ]));
            }

            RoutingApproval::create([
                'tenant_id'  => $tenantId,
                'routing_id' => $newRouting->id,
                'user_id'    => $creatorUserId,
                'action'     => RoutingApproval::ACTION_REVISION_CREATED,
                'comments'   => "Version {$newVersion} duplicated from version {$original->version}.",
            ]);

            event(new \App\Domains\Production\Events\RoutingRevisionCreated($newRouting));

            return $newRouting;
        });
    }

    /**
     * Guard against duplicate product+version combinations per tenant.
     */
    public function checkRoutingConflicts(int $productId, string $version, int $tenantId, ?int $ignoreId = null): void
    {
        $query = Routing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('version', $version)
            ->whereNotIn('status', [Routing::STATUS_CANCELLED]);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException(
                "A routing version '{$version}' already exists for this product."
            );
        }
    }

    /**
     * Sync routing operations — delete all then recreate (dynamic grid pattern).
     *
     * @param RoutingOperation[] $operationDTOs
     */
    private function syncOperations(Routing $routing, array $operationDTOs, int $tenantId): void
    {
        // Hard delete (forceDelete) operations since sequence unique constraint
        // would conflict with soft-deleted rows if sequences are reused
        $routing->operations()->forceDelete();

        foreach ($operationDTOs as $index => $opDto) {
            $seq = $opDto->sequence ?: (($index + 1) * 10);
            RoutingOperation::create(array_merge($opDto->toArray(), [
                'tenant_id'        => $tenantId,
                'routing_id'       => $routing->id,
                'sequence'         => $seq,
                'operation_number' => $opDto->operation_number ?: 'OP-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            ]));
        }
    }

    /**
     * Validate that a machine belongs to the specified work center.
     * Used by form requests and UI validation.
     */
    public function validateMachineBelongsToWorkCenter(int $machineId, int $workCenterId): bool
    {
        $machine = Machine::find($machineId);
        return $machine && $machine->work_center_id === $workCenterId;
    }
}
