<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\RoutingDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Repositories\RoutingRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RoutingService
{
    public function __construct(
        private readonly RoutingRepositoryInterface $routingRepository,
        private readonly RoutingNumberService $numberService,
        private readonly RoutingLifecycleService $routingLifecycleService
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
        $this->routingLifecycleService->validateEffectiveDates(
            $dto->effective_from,
            $dto->effective_to,
            $dto->product_id,
            null,
            $tenantId
        );

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
        $this->routingLifecycleService->validateEffectiveDates(
            $dto->effective_from,
            $dto->effective_to,
            $dto->product_id,
            $id,
            $routing->tenant_id
        );

        return DB::transaction(function () use ($routing, $dto) {
            $data = $dto->toArray();
            if (empty($data['routing_number'])) {
                unset($data['routing_number']);
            }

            $this->routingRepository->update($routing->id, $data);
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

        // Require linked active or draft BOM before routing can be submitted for approval
        $bom = $routing->bom;
        if (!$bom && $routing->product_id) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $routing->tenant_id)
                ->where('product_id', $routing->product_id)
                ->whereIn('status', ['approved', 'active', 'draft'])
                ->latest('id')
                ->first();
            if ($bom) {
                $routing->update(['bom_id' => $bom->id]);
            }
        }

        if (!$bom) {
            throw new InvalidArgumentException('Cannot submit routing for approval: A linked Bill of Materials (BOM) in active or draft status is required.');
        }

        if (!in_array($bom->status, ['approved', 'active', 'draft'])) {
            throw new InvalidArgumentException("Cannot submit routing for approval: The linked BOM must be active or draft (currently '{$bom->status}').");
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

        // Require linked BOM to be approved and active before routing can be approved
        $bom = $routing->bom;
        if (!$bom && $routing->product_id) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $routing->tenant_id)
                ->where('product_id', $routing->product_id)
                ->whereIn('status', ['approved', 'active'])
                ->latest('id')
                ->first();
            if ($bom) {
                $routing->update(['bom_id' => $bom->id]);
            }
        }

        if (!$bom || !in_array($bom->status, ['approved', 'active', 'scheduled'])) {
            throw new InvalidArgumentException('Cannot approve routing: The linked Bill of Materials (BOM) must be approved and active first.');
        }

        return DB::transaction(function () use ($routing, $approverUserId) {
            // Centralized routing approval lifecycle transition: archives previous active versions
            // and sets their effective_to date to the new version's effective_from date
            $this->routingLifecycleService->transitionOnApproval($routing, $approverUserId);

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
            $this->routingLifecycleService->transitionOnCancellation($routing, $userId, $comments);

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
        $this->routingLifecycleService->validateEffectiveDates(
            Carbon::now()->toDateString(),
            null,
            $original->product_id,
            null,
            $tenantId
        );

        return DB::transaction(function () use ($original, $newVersion, $creatorUserId, $tenantId) {
            $bomId = $original->bom_id;
            $currentBom = $original->bom_id ? \App\Domains\Production\Models\ProductionBom::where('tenant_id', $tenantId)->find($original->bom_id) : null;
            if (!$currentBom || in_array($currentBom->status, ['cancelled', 'inactive'])) {
                // If old routing had a cancelled/inactive BOM, automatically select an active/approved BOM
                $activeBom = \App\Domains\Production\Models\ProductionBom::where('tenant_id', $tenantId)
                    ->where('product_id', $original->product_id)
                    ->whereIn('status', ['approved', 'active'])
                    ->orderByDesc('id')
                    ->first();
                if ($activeBom) {
                    $bomId = $activeBom->id;
                } else {
                    $draftBom = \App\Domains\Production\Models\ProductionBom::where('tenant_id', $tenantId)
                        ->where('product_id', $original->product_id)
                        ->where('status', 'draft')
                        ->orderByDesc('id')
                        ->first();
                    $bomId = $draftBom ? $draftBom->id : null;
                }
            }

            $newRouting = $this->routingRepository->create([
                'tenant_id'      => $tenantId,
                'routing_number' => $this->numberService->generateNextNumber($tenantId),
                'name'           => $original->name,
                'product_id'             => $original->product_id,
                'bom_id'                 => $bomId,
                'material_quantity_mode' => $original->material_quantity_mode ?? 'per_unit',
                'version'                => $newVersion,
                'revision'       => $original->revision + 1,
                'is_default'     => $original->is_default,
                'effective_from' => Carbon::now()->toDateString(),
                'effective_to'   => null,
                'status'         => Routing::STATUS_DRAFT,
                'description'    => "Duplicated from version {$original->version}. " . $original->description,
                'created_by'     => $creatorUserId,
            ]);

            // Copy all operations and their materials
            foreach ($original->operations as $op) {
                $newOp = RoutingOperation::create(array_merge($op->toArray(), [
                    'id'         => null,
                    'tenant_id'  => $tenantId,
                    'routing_id' => $newRouting->id,
                    'created_at' => null,
                    'updated_at' => null,
                    'deleted_at' => null,
                ]));

                foreach ($op->materials as $mat) {
                    RoutingOperationMaterial::create([
                        'tenant_id'            => $tenantId,
                        'routing_operation_id' => $newOp->id,
                        'material_id'          => $mat->material_id,
                        'quantity'             => $mat->quantity,
                        'uom_id'               => $mat->uom_id,
                        'consumption_type'     => $mat->consumption_type,
                        'sequence'             => $mat->sequence,
                    ]);
                }
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

        $createdOps = [];
        $opsBySeq = [];

        foreach ($operationDTOs as $index => $opDto) {
            $seq = $opDto->sequence ?: (($index + 1) * 10);
            $data = array_merge($opDto->toArray(), [
                'tenant_id'        => $tenantId,
                'routing_id'       => $routing->id,
                'sequence'         => $seq,
                'operation_number' => $opDto->operation_number ?: 'OP-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            ]);
            unset($data['previous_operation_id']);

            $createdOp = RoutingOperation::create($data);
            $createdOps[] = ['model' => $createdOp, 'dto' => $opDto];
            $opsBySeq[$seq] = $createdOp->id;

            $materialsToSync = !empty($opDto->materials) 
                ? $opDto->materials 
                : (!empty($opDto->material_id) ? [['material_id' => $opDto->material_id, 'quantity' => 1.0]] : []);

            foreach ($materialsToSync as $matIndex => $mat) {
                if (empty($mat['material_id'])) {
                    continue;
                }
                $matProd = \App\Domains\Inventory\Models\Product::find($mat['material_id']);
                $uomId = !empty($mat['uom_id']) ? (int) $mat['uom_id'] : ($matProd?->uom_id ?? \App\Domains\Inventory\Models\Uom::first()?->id ?? 1);
                $qty = isset($mat['quantity']) ? (float) $mat['quantity'] : 1.0;

                \App\Domains\Production\Models\RoutingOperationMaterial::create([
                    'tenant_id'            => $tenantId,
                    'routing_operation_id' => $createdOp->id,
                    'material_id'          => (int) $mat['material_id'],
                    'quantity'             => $qty,
                    'uom_id'               => $uomId,
                    'consumption_type'     => $mat['consumption_type'] ?? null,
                    'sequence'             => $matIndex + 1,
                ]);
            }
        }

        // Pass 2: Bind dependencies (previous_operation_id)
        foreach ($createdOps as $index => $item) {
            $model = $item['model'];
            $dto = $item['dto'];

            if ($dto->is_parallel) {
                // Parallel operations are independent of previous operations
                $model->previous_operation_id = null;
            } elseif ($dto->predecessor_sequence !== null) {
                if ($dto->predecessor_sequence <= 0) {
                    // Explicitly marked independent / none
                    $model->previous_operation_id = null;
                } else {
                    $model->previous_operation_id = $opsBySeq[$dto->predecessor_sequence] ?? null;
                }
            } elseif ($index > 0) {
                // Default sequential fallback: depends on the immediate preceding operation
                $prevModel = $createdOps[$index - 1]['model'];
                $model->previous_operation_id = $prevModel->id;
            } else {
                $model->previous_operation_id = null;
            }

            $model->save();
        }
    }

    /**
     * Validate that a machine belongs to the specified work center.
     * Used by form requests and UI validation.
     */
    public function validateMachineBelongsToWorkCenter(int $machineId, int $workCenterId): bool
    {
        $machine = Machine::with('section')->find($machineId);
        return (bool) ($machine && $machine->section?->work_center_id === $workCenterId);
    }
}
