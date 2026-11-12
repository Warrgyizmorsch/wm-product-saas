<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionBomApproval;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Repositories\ProductionBomRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionBomService
{
    public function __construct(
        private readonly ProductionBomRepositoryInterface $bomRepository,
        private readonly ProductionBomNumberService $numberService,
        private ?BomLifecycleService $bomLifecycleService = null,
        private ?RoutingLifecycleService $routingLifecycleService = null
    ) {
        $this->bomLifecycleService = $bomLifecycleService ?? app(BomLifecycleService::class);
        $this->routingLifecycleService = $routingLifecycleService ?? app(RoutingLifecycleService::class);
    }

    /**
     * Helper formula to compute materials requirements including scrap loss.
     */
    public function calculateRequiredMaterial(float $componentQty, float $scrapPercentage): float
    {
        return $componentQty + ($componentQty * ($scrapPercentage / 100));
    }

    /**
     * Create a new Bill of Materials draft.
     */
    public function create(ProductionBomDTO $dto, ?int $creatorUserId = null): ProductionBom
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $bomNumber = trim($dto->bom_number ?? '');

        // BM-IMPL-03 #4: a Duplicate / Revise copy is a new VERSION of the source BOM — same BOM number
        $sourceBom = $dto->source_bom_id
            ? ProductionBom::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($dto->source_bom_id)
            : null;
        if ($sourceBom && ($bomNumber === '' || $bomNumber === 'AUTO' || $bomNumber === $sourceBom->bom_number)) {
            $bomNumber = $sourceBom->bom_number;
        } elseif (empty($bomNumber) || $bomNumber === 'AUTO') {
            $bomNumber = $this->numberService->generateNextNumber($tenantId, null, $dto->product_id);
        } else {
            if (!$this->numberService->validateNumber($bomNumber, $tenantId)) {
                throw new InvalidArgumentException("BOM number format is invalid. Use alphanumeric characters, hyphens, underscores or slashes.");
            }
            if ($this->numberService->isDuplicate($bomNumber, $tenantId)) {
                throw new InvalidArgumentException("A BOM with number '{$bomNumber}' already exists.");
            }
        }

        if (empty($dto->routing_id)) {
            throw new InvalidArgumentException("Linked Routing is required when creating a BOM.");
        }

        $routing = Routing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($dto->routing_id);
        if (!$routing || in_array($routing->status, [Routing::STATUS_HISTORICAL, Routing::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException("Selected routing is invalid or no longer active.");
        }

        $this->checkBomConflicts($dto->product_id, $dto->version, null, $tenantId);
        $this->bomLifecycleService->validateEffectiveDates(
            $dto->effective_date,
            $dto->expiry_date,
            $dto->product_id,
            null,
            $tenantId,
            $dto->bom_type
        );

        return DB::transaction(function () use ($dto, $bomNumber, $creatorUserId, $tenantId, $sourceBom) {
            $bomData = array_merge($dto->toArray(), [
                'tenant_id' => $tenantId,
                'bom_number' => $bomNumber,
                'usage_context' => $dto->usage_context ?? 'manufacturing',
                'status' => 'draft',
                'created_by' => $creatorUserId,
                'revision' => $sourceBom ? ((int) $sourceBom->revision + 1) : 0,
            ]);

            $bom = $this->bomRepository->create($bomData);

            $this->persistBomItems($bom, $dto->items, $tenantId);

            $bom->load(['items', 'operationMaterials']);
            $bom->refreshWarningStatus();

            // Create Approval History Log
            ProductionBomApproval::create([
                'tenant_id' => $tenantId,
                'bom_id' => $bom->id,
                'user_id' => $creatorUserId,
                'action' => 'Created',
                'comments' => 'Initial BOM draft created.',
            ]);

            event(new \App\Domains\Production\Events\BomCreated($bom));

            if ($sourceBom) {
                ProductionBomApproval::create([
                    'tenant_id' => $tenantId,
                    'bom_id'    => $bom->id,
                    'user_id'   => $creatorUserId,
                    'action'    => 'Revision Created',
                    'comments'  => "Version {$bom->version} created from version {$sourceBom->version}.",
                ]);
                event(new \App\Domains\Production\Events\BomVersionCreated($bom));
            }

            return $bom;
        });
    }

    /**
     * Update an existing draft BOM.
     */
    public function update(int $id, ProductionBomDTO $dto): ProductionBom
    {
        $bom = $this->bomRepository->find($id);

        if (!$bom) {
            throw new InvalidArgumentException("BOM not found.");
        }

        if (!$bom->isDraft() && !$bom->isUnderRevision()) {
            throw new InvalidArgumentException("Only draft BOMs or BOMs under revision can be updated.");
        }

        $tenantId = $bom->tenant_id;

        if (empty($dto->routing_id)) {
            throw new InvalidArgumentException("Linked Routing is required when updating a BOM.");
        }

        $routing = Routing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->find($dto->routing_id);
        if (!$routing || in_array($routing->status, [Routing::STATUS_HISTORICAL, Routing::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException("Selected routing is invalid or no longer active.");
        }

        $this->checkBomConflicts($dto->product_id, $dto->version, $id, $tenantId);
        $this->bomLifecycleService->validateEffectiveDates(
            $dto->effective_date,
            $dto->expiry_date,
            $dto->product_id,
            $id,
            $tenantId,
            $dto->bom_type
        );

        return DB::transaction(function () use ($bom, $dto, $tenantId) {
            $bomData = array_merge($dto->toArray(), [
                'usage_context' => $dto->usage_context ?? 'manufacturing',
            ]);
            $bom->update($bomData);

            // Recreate items to handle dynamic grids correctly
            RoutingOperationMaterial::where('bom_id', $bom->id)->delete();
            $bom->items()->delete();

            $this->persistBomItems($bom, $dto->items, $tenantId);

            $bom->load(['items', 'operationMaterials']);
            $bom->refreshWarningStatus();

            return $bom;
        });
    }

    /**
     * Submit BOM for review and approval.
     */
    public function submitApproval(int $id): ProductionBom
    {
        $bom = $this->bomRepository->find($id);

        if (!$bom) {
            throw new InvalidArgumentException("BOM not found.");
        }

        if (!$bom->isDraft() && !$bom->isUnderRevision()) {
            throw new InvalidArgumentException("Only draft BOMs can be submitted for approval.");
        }

        $hasPrimaryAllocation = RoutingOperationMaterial::where('bom_id', $bom->id)
            ->where('is_alternative', false)
            ->exists();
        if (!$hasPrimaryAllocation) {
            throw new InvalidArgumentException("Cannot submit BOM for approval: At least one primary material allocation is required.");
        }

        // RT-IMPL-09: materials on an operation removed from the routing must be moved / removed first
        if ($removed = ProductionBom::deletedOperationMessages($bom->id)) {
            throw new InvalidArgumentException(implode(' ', $removed));
        }

        return DB::transaction(function () use ($bom) {
            $bom->update(['status' => 'pending_approval']);
            $bom->refreshWarningStatus();

            ProductionBomApproval::create([
                'tenant_id' => $bom->tenant_id,
                'bom_id' => $bom->id,
                'user_id' => auth()->id(), // BM-IMPL-02 #2: real user, null when run by a job / console
                'action' => 'Submitted',
                'comments' => 'Submitted for engineering review and approval.',
            ]);

            return $bom;
        });
    }

    /**
     * Approve BOM and deactivate any older active version.
     */
    public function approve(int $id, ?int $approverUserId): ProductionBom
    {
        $bom = $this->bomRepository->find($id);

        if (!$bom) {
            throw new InvalidArgumentException("BOM not found.");
        }

        if (!$bom->isPendingApproval()) {
            throw new InvalidArgumentException("Only BOMs pending approval can be approved.");
        }

        return DB::transaction(function () use ($id, $approverUserId) {
            $bom = ProductionBom::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($id);

            if (!$bom->isPendingApproval()) {
                throw new InvalidArgumentException("Only BOMs pending approval can be approved.");
            }

            // RT-IMPL-09 / BM-IMPL-13 #3: materials on an operation removed from the routing must be moved / removed first
            $deletedOps = ProductionBom::deletedOperationNumbers($bom->id);
            if (!empty($deletedOps)) {
                $opLabel = implode(', ', $deletedOps);
                throw new InvalidArgumentException("Operation {$opLabel} was removed from the routing — send the BOM back to draft and fix it.");
            }

            // BM-IMPL-08 #6: a scheduled sub-BOM moves the parent's start to its own start (parent start = max)
            $bom->load('operationMaterials');
            $parentStart = Carbon::parse($bom->effective_date ?: Carbon::today())->toDateString();
            $latestChildStart = ProductionBom::withoutGlobalScopes()
                ->whereIn('id', $bom->operationMaterials->pluck('child_bom_id')->filter()->unique())
                ->where('status', ProductionBom::STATUS_SCHEDULED)
                ->max('effective_date');
            if ($latestChildStart && Carbon::parse($latestChildStart)->toDateString() > $parentStart) {
                $parentStart = Carbon::parse($latestChildStart)->toDateString();
                $bom->update(['effective_date' => $parentStart]);
            }

            // BM-IMPL-02 #3: routing active, or scheduled to start on / before the BOM start
            $routing = $bom->routing;
            $routingStart = $routing?->effective_from ? Carbon::parse($routing->effective_from)->toDateString() : null;
            $routingOk = $routing && (
                $routing->status === Routing::STATUS_ACTIVE
                || ($routing->status === Routing::STATUS_SCHEDULED && $routingStart !== null && $routingStart <= $parentStart)
            );
            if (!$routingOk) {
                $label = $routing ? "Routing {$routing->routing_number} is {$routing->status}" . ($routingStart ? " (starts {$routingStart})" : '') : 'The linked routing no longer exists';
                throw new InvalidArgumentException("{$label}; the BOM can only be approved when the Routing is active or starts on or before {$parentStart}.");
            }

            // BM-IMPL-08: validate explicit sub-BOM links before approval
            foreach ($bom->operationMaterials as $opMat) {
                if ($opMat->child_bom_id) {
                    $this->validateChildBomLink(
                        (int) $opMat->child_bom_id,
                        (int) $opMat->material_id,
                        $bom->id,
                        Carbon::parse($bom->effective_date)->toDateString(),
                        $bom->expiry_date ? Carbon::parse($bom->expiry_date)->toDateString() : null,
                        $bom->tenant_id
                    );
                }
            }

            // 1. Centralized lifecycle transition on approval: sets previous active version's expiry date
            // to the new version's start date and activates the new version
            $deactivatedBomIds = $this->bomLifecycleService->transitionOnApproval($bom, $approverUserId);

            // [INVERSION-DECOUPLE-TRACK-03] Old BOM-to-Routing cascades commented out
            /*
            // 2. Centralized archival of connected routings
            if (!empty($deactivatedBomIds)) {
                $this->routingLifecycleService->archiveRoutingsForDeactivatedBoms(
                    $deactivatedBomIds,
                    $bom->product_id,
                    $approverUserId,
                    'Automatically archived because connected BOM was superseded and deactivated.',
                    $bom->effective_date ? \Illuminate\Support\Carbon::parse($bom->effective_date)->toDateString() : null
                );
            }

            // 3. Synchronize unsubmitted draft routings to point to the newly approved BOM
            $this->routingLifecycleService->syncDraftRoutingsToApprovedBom($bom);
            */

            // 4. Log approval history
            ProductionBomApproval::create([
                'tenant_id' => $bom->tenant_id,
                'bom_id' => $bom->id,
                'user_id' => $approverUserId,
                'action' => 'Approved',
                'comments' => 'BOM version approved and set active.',
            ]);

            $bom->refreshWarningStatus();

            // 5. Update warning status for any parent BOMs referencing this component
            $this->updateParentBomWarningsForComponent($bom->product_id, $bom->id);

            event(new \App\Domains\Production\Events\BomApproved($bom));

            return $bom;
        });
    }

    /**
     * BM-IMPL-19 #2: header-only update (API PATCH without items) — allocations and master rows stay as they are.
     * Changing the routing needs the items too (their operations belong to the routing).
     */
    public function updateHeader(int $id, array $data): ProductionBom
    {
        $bom = $this->bomRepository->find($id);
        if (!$bom) {
            throw new InvalidArgumentException("BOM not found.");
        }
        if (!$bom->isDraft() && !$bom->isUnderRevision()) {
            throw new InvalidArgumentException("Only draft BOMs or BOMs under revision can be updated.");
        }

        $allowed = ['bom_name', 'bom_type', 'usage_context', 'base_quantity', 'base_uom_id', 'version', 'revision_reason', 'effective_date', 'expiry_date', 'notes', 'routing_id'];
        $header = array_intersect_key($data, array_flip($allowed));

        if (array_key_exists('routing_id', $header) && (int) $header['routing_id'] !== (int) $bom->routing_id) {
            throw new InvalidArgumentException("Send the items together with a new routing — the materials' operations belong to the routing.");
        }
        unset($header['routing_id']);

        $version = $header['version'] ?? $bom->version;
        $this->checkBomConflicts($bom->product_id, $version, $bom->id, $bom->tenant_id);
        $this->bomLifecycleService->validateEffectiveDates(
            $header['effective_date'] ?? ($bom->effective_date ? Carbon::parse($bom->effective_date)->toDateString() : null),
            array_key_exists('expiry_date', $header) ? $header['expiry_date'] : ($bom->expiry_date ? Carbon::parse($bom->expiry_date)->toDateString() : null),
            $bom->product_id,
            $bom->id,
            $bom->tenant_id,
            $header['bom_type'] ?? $bom->bom_type
        );

        $bom->update($header);

        return $bom->fresh();
    }

    /**
     * Reject a pending BOM.
     */
    public function reject(int $id, ?int $approverUserId, ?string $comments = null): ProductionBom
    {
        $bom = $this->bomRepository->find($id);

        if (!$bom) {
            throw new InvalidArgumentException("BOM not found.");
        }

        if (!$bom->isPendingApproval()) {
            throw new InvalidArgumentException("Only BOMs pending approval can be rejected.");
        }

        return DB::transaction(function () use ($bom, $approverUserId, $comments) {
            $bom->update(['status' => 'draft']); // returns to draft for editing

            ProductionBomApproval::create([
                'tenant_id' => $bom->tenant_id,
                'bom_id' => $bom->id,
                'user_id' => $approverUserId,
                'action' => 'Rejected',
                'comments' => $comments ?: 'BOM rejected during review.',
            ]);

            // [INVERSION-DECOUPLE-TRACK-03] Routing does not track BOM; rejecting BOM no longer auto-rejects Routings
            /*
            // If a draft BOM gets rejected, the routing connected to that draft BOM also gets automatically rejected
            $linkedRoutings = Routing::withoutGlobalScopes()
                ->where(function ($q) use ($bom) {
                    $q->where('bom_id', $bom->id)
                      ->orWhere(function ($sub) use ($bom) {
                          $sub->whereNull('bom_id')
                              ->where('product_id', $bom->product_id);
                      });
                })
                ->where('status', Routing::STATUS_PENDING_APPROVAL)
                ->get();

            foreach ($linkedRoutings as $linkedRouting) {
                $linkedRouting->update(['status' => Routing::STATUS_DRAFT]);

                RoutingApproval::create([
                    'tenant_id' => $linkedRouting->tenant_id,
                    'routing_id' => $linkedRouting->id,
                    'user_id' => $approverUserId,
                    'action' => RoutingApproval::ACTION_REJECTED,
                    'comments' => 'Automatically rejected because connected BOM (' . $bom->bom_number . ') was rejected.',
                ]);
            }
            */

            return $bom;
        });
    }

    /**
     * Cancel an active or pending BOM.
     */
    public function cancel(int $id, ?int $userId, ?string $comments = null): ProductionBom
    {
        $bom = $this->bomRepository->find($id);

        if (!$bom) {
            throw new InvalidArgumentException("BOM not found.");
        }

        return DB::transaction(function () use ($bom, $userId, $comments) {
            $this->bomLifecycleService->transitionOnCancellation($bom, $userId, $comments);

            // [INVERSION-DECOUPLE-TRACK-03] Routing does not track BOM; cancelling BOM no longer cancels Routings
            /*
            // Handle connected routings on BOM cancellation:
            // Scheduled routings get cancelled with reason, active routings get historical (current flow)
            $this->routingLifecycleService->handleRoutingsOnBomCancellation(
                $bom,
                $userId,
                $comments
            );
            */

            // Notify parent BOMs using this component that its sub-BOM was cancelled
            $this->updateParentBomWarningsForComponent($bom->product_id, $bom->id);

            return $bom->fresh();
        });
    }

    /**
     * BM-IMPL-03 #5: Duplicate / Revise saves nothing. Read-only copy of a BOM as the create payload (header + every
     * allocation with its alternative link as client keys); the master list is rebuilt from the allocations on save.
     * Used by the web create form (copy mode) and the API duplicate / clone / revision endpoints.
     */
    public function buildCopyPayload(int $id, string $newVersion): array
    {
        $source = $this->bomRepository->getBomWithComponents($id);
        if (!$source) {
            throw new InvalidArgumentException("Source BOM not found.");
        }

        $this->checkBomConflicts($source->product_id, $newVersion, null, $source->tenant_id);

        $routing = $source->routing;
        $routingAllowed = $routing && !in_array($routing->status, [Routing::STATUS_HISTORICAL, Routing::STATUS_CANCELLED], true);
        $deletedOpIds = RoutingOperation::onlyTrashed()->whereIn('id', $source->operationMaterials->pluck('routing_operation_id'))->pluck('id')->all();

        $warnings = [];
        if (!$routingAllowed) {
            $warnings[] = $routing
                ? "Routing {$routing->routing_number} is {$routing->status} — choose another routing."
                : 'The source routing no longer exists — choose another routing.';
        }
        if ($deletedOpIds) {
            $warnings[] = 'Some materials sit on operations removed from the routing — move or remove them before saving.';
        }

        return [
            'source_bom_id'  => $source->id,
            'source_version' => $source->version,
            'bom_number'     => $source->bom_number,
            'bom_name'       => $source->bom_name,
            'bom_type'       => $source->bom_type,
            'usage_context'  => $source->usage_context,
            'product_id'     => $source->product_id,
            'routing_id'     => $routingAllowed ? $source->routing_id : null,
            'base_quantity'  => (float) $source->base_quantity,
            'base_uom_id'    => $source->base_uom_id,
            'version'        => $newVersion,
            'effective_date' => Carbon::today()->toDateString(),
            'expiry_date'    => null,
            'notes'          => "Duplicated from version {$source->version}." . ($source->notes ? ' ' . $source->notes : ''),
            'items'          => $source->operationMaterials->map(fn ($om) => [
                'row_uid'                   => 'copy_' . $om->id,
                'routing_operation_id'      => $om->routing_operation_id,
                'deleted_operation'         => in_array($om->routing_operation_id, $deletedOpIds, true),
                'material_id'               => $om->material_id,
                'quantity'                  => (float) $om->quantity,
                'uom_id'                    => $om->uom_id,
                'material_scrap_percentage' => (float) ($om->material_scrap_percentage ?? 0),
                'is_alternative'            => (bool) $om->is_alternative,
                'replaces_row_uid'          => $om->replaces_routing_material_id ? 'copy_' . $om->replaces_routing_material_id : null,
                'priority'                  => $om->is_alternative ? $om->priority : null,
                'child_bom_id'              => $om->child_bom_id,
                'sequence'                  => $om->sequence,
                'notes'                     => $om->notes,
            ])->values()->all(),
            'warnings'       => $warnings,
        ];
    }

    /**
     * Persist BOM items using the 2-tier architecture:
     * - Table 1 (production_bom_items): Master recipe summary (1 unique row per material per BOM).
     * - Table 2 (production_routing_operation_materials): Workstation operation allocations.
     */
    protected function persistBomItems(ProductionBom $bom, array $itemDtos, int $tenantId): void
    {
        if (empty($itemDtos)) {
            return;
        }

        // Preload alternative routing operations to accurately reflect effective alternative demand
        $opIds = array_filter(array_map(fn($d) => $d->routing_operation_id, $itemDtos));
        $altOpIds = !empty($opIds)
            ? RoutingOperation::whereIn('id', $opIds)->where('is_alternative', true)->pluck('id')->all()
            : [];
        $altOpIdsMap = array_fill_keys(array_map('strval', $altOpIds), true);

        // STEP 1: Group allocations by material_id to build the Master Recipe Summary (Tab 2)
        $materialGroups = [];
        foreach ($itemDtos as $itemDto) {
            $materialGroups[$itemDto->material_id][] = $itemDto;
        }

        $materialToBomItemMap = [];
        foreach ($materialGroups as $materialId => $dtos) {
            $totalQuantity = 0.0;
            $alternativeQuantity = 0.0;
            $grossQuantity = 0.0;
            $hasPrimary = false;
            $hasAlternative = false;
            $uomId = $dtos[0]->uom_id;

            foreach ($dtos as $dto) {
                $isEffectiveAlt = !empty($dto->is_alternative) || isset($altOpIdsMap[(string) $dto->routing_operation_id]);
                if ($isEffectiveAlt) {
                    $hasAlternative = true;
                    $alternativeQuantity += (float) $dto->quantity; // BM-IMPL-04: alternative bucket
                } else {
                    $hasPrimary = true;
                    $totalQuantity += (float) $dto->quantity;
                    $grossQuantity += (float) $dto->quantity * (1 + ((float) ($dto->material_scrap_percentage ?? 0)) / 100);
                }
            }

            $allocationRole = 'primary';
            if ($hasPrimary && $hasAlternative) {
                $allocationRole = 'mixed';
            } elseif ($hasAlternative) {
                $allocationRole = 'alternative';
            }

            // Create 1 consolidated master summary row per unique material
            $bomItem = ProductionBomItem::create([
                'tenant_id'                 => $tenantId,
                'bom_id'                    => $bom->id,
                'material_id'               => $materialId,
                'quantity'                  => $totalQuantity,
                'alternative_quantity'      => round($alternativeQuantity, 6),
                'gross_quantity'            => round($grossQuantity, 6),
                'uom_id'                    => $uomId,
                'allocation_role'           => $allocationRole,
                'material_scrap_percentage' => 0.00,
            ]);

            $materialToBomItemMap[$materialId] = $bomItem;
        }

        // STEP 2: Clear and recreate operation allocations in production_routing_operation_materials (Tab 1)
        RoutingOperationMaterial::where('bom_id', $bom->id)->delete();

        $uidToOpMatMap = [];
        $createdList = [];

        // Pass 1: Create all operation material records
        foreach ($itemDtos as $index => $itemDto) {
            $childBomId = $itemDto->child_bom_id;

            if ($childBomId) {
                $this->validateChildBomLink(
                    $childBomId,
                    $itemDto->material_id,
                    $bom->id,
                    Carbon::parse($bom->effective_date)->toDateString(),
                    $bom->expiry_date ? Carbon::parse($bom->expiry_date)->toDateString() : null,
                    $tenantId
                );
            }
            // BM-IMPL-08 #5: no silent sub-BOM pick — the form pre-selects it visibly per material

            $parentBomItem = $materialToBomItemMap[$itemDto->material_id] ?? null;

            // Priority: strictly null for primary component; integer for alternative
            $priority = !empty($itemDto->is_alternative) ? $itemDto->priority : null; // BM-IMPL-07 #3: validated, no default
            $scrapPct = (float) ($itemDto->material_scrap_percentage ?? 0.00);
            $grossQty = RoutingOperationMaterial::calculateGrossQuantity((float) $itemDto->quantity, $scrapPct);

            $createdOpMat = RoutingOperationMaterial::create([
                'tenant_id'                    => $tenantId,
                'bom_id'                       => $bom->id,
                'bom_item_id'                  => $parentBomItem?->id,
                'routing_operation_id'         => $itemDto->routing_operation_id,
                'material_id'                  => $itemDto->material_id,
                'child_bom_id'                 => $childBomId,
                'quantity'                     => (float) $itemDto->quantity,
                'gross_quantity'               => $grossQty,
                'uom_id'                       => $itemDto->uom_id,
                'is_alternative'               => (bool) $itemDto->is_alternative,
                'priority'                     => $priority,
                'replaces_routing_material_id' => null, // resolved in Pass 2
                'material_scrap_percentage'    => $scrapPct,
                'sequence'                     => $itemDto->sequence ?: ($index + 1),
                'notes'                        => $itemDto->notes,
                'consumption_type'             => null,
            ]);

            if (!empty($itemDto->row_uid)) {
                $uidToOpMatMap[$itemDto->row_uid] = $createdOpMat;
            }
            if (!empty($itemDto->id)) {
                $uidToOpMatMap[(string) $itemDto->id] = $createdOpMat;
            }
            $uidToOpMatMap[(string) $createdOpMat->id] = $createdOpMat;

            $createdList[] = [
                'dto'   => $itemDto,
                'model' => $createdOpMat,
            ];
        }

        // Pass 2: Resolve replaces_routing_material_id for alternative components
        foreach ($createdList as $entry) {
            $itemDto = $entry['dto'];
            $model = $entry['model'];

            if ($itemDto->is_alternative) {
                $targetModel = null;
                if (!empty($itemDto->replaces_row_uid) && isset($uidToOpMatMap[$itemDto->replaces_row_uid])) {
                    $targetModel = $uidToOpMatMap[$itemDto->replaces_row_uid];
                } elseif (!empty($itemDto->replaces_item_id) && isset($uidToOpMatMap[(string) $itemDto->replaces_item_id])) {
                    $targetModel = $uidToOpMatMap[(string) $itemDto->replaces_item_id];
                } elseif (!empty($itemDto->replaces_item_id)) {
                    $existing = RoutingOperationMaterial::where('bom_id', $bom->id)->find($itemDto->replaces_item_id);
                    if ($existing && !$existing->is_alternative && $existing->id !== $model->id) {
                        $targetModel = $existing;
                    }
                }

                if ($targetModel && $targetModel->id !== $model->id) {
                    if ($targetModel->bom_id === $bom->id
                        && (int) $targetModel->routing_operation_id === (int) $model->routing_operation_id
                        && !$targetModel->is_alternative) {
                        $model->update(['replaces_routing_material_id' => $targetModel->id]);
                    }
                }
            }
        }
    }

    /**
     * single level calculator fallback
     */
    public function calculateRequirements(int $bomId, float $parentQuantity): array
    {
        $bom = $this->bomRepository->getBomWithComponents($bomId);

        if (!$bom) {
            throw new InvalidArgumentException("BOM not found.");
        }

        $requirements = [];
        $baseQty = $bom->base_quantity > 0 ? $bom->base_quantity : 1.0;
        $multiplier = $parentQuantity / $baseQty;

        $allocations = $bom->operationMaterials->isNotEmpty()
            ? $bom->operationMaterials->where('is_alternative', false)
            : $bom->items;

        foreach ($allocations as $item) {
            $scrapPct = (float) ($item->material_scrap_percentage ?? 0.0);
            $netQty = (float) $item->quantity * $multiplier;
            $grossPerBase = isset($item->gross_quantity) && $item->gross_quantity !== null
                ? (float) $item->gross_quantity
                : RoutingOperationMaterial::calculateGrossQuantity((float) $item->quantity, $scrapPct);
            $grossQty = round($grossPerBase * $multiplier, 4);

            $requirements[] = [
                'material_id' => $item->material_id,
                'material_name' => $item->material->name,
                'material_sku' => $item->material->sku,
                'net_quantity' => $netQty,
                'gross_quantity' => $grossQty,
                'wastage_percentage' => $scrapPct, // compatibility fallback
                'material_scrap_percentage' => $scrapPct,
                'uom_code' => $item->uom?->code ?? $item->material?->uom?->code ?? 'PCS',
                'is_alternative' => method_exists($item, 'isAlternative') ? $item->isAlternative() : (bool) ($item->is_alternative ?? false),
                'replaces_item_id' => null,
                'priority' => $item->priority ?? null,
            ];
        }

        return $requirements;
    }

    public function checkBomConflicts(int $productId, string $version, ?int $ignoreBomId = null, ?int $tenantId = null): void
    {
        $query = ProductionBom::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->where('version', $version);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($ignoreBomId !== null) {
            $query->where('id', '!=', $ignoreBomId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException("A BOM version '{$version}' already exists for this product.");
        }
    }

    public function validateChildBomLink(
        int $childBomId,
        int $materialProductId,
        ?int $parentBomId,
        string $parentEffectiveDate,
        ?string $parentExpiryDate,
        ?int $tenantId = null
    ): void {
        if ($parentBomId && $childBomId === $parentBomId) {
            throw new InvalidArgumentException("A BOM cannot link to itself as a child BOM.");
        }

        $childBom = ProductionBom::withoutGlobalScopes()->find($childBomId);
        if (!$childBom || ($tenantId !== null && (int) $childBom->tenant_id !== $tenantId)) {
            throw new InvalidArgumentException("Linked child BOM not found.");
        }

        if ($childBom->product_id !== $materialProductId) {
            throw new InvalidArgumentException("Linked child BOM product does not match component material product.");
        }

        if ($parentBomId) {
            $visited = [$parentBomId => true];
            $this->detectCircularChildLink($childBomId, $parentBomId, $visited);
        }

        if (!in_array($childBom->status, [ProductionBom::STATUS_APPROVED, ProductionBom::STATUS_ACTIVE, ProductionBom::STATUS_SCHEDULED], true)) {
            throw new InvalidArgumentException("Linked child BOM ({$childBom->bom_number}) must be approved, scheduled, or active.");
        }

        $childEffective = $childBom->effective_date ? Carbon::parse($childBom->effective_date)->toDateString() : null;
        if ($childEffective && $childEffective > $parentEffectiveDate) {
            throw new InvalidArgumentException("Child BOM effective date ({$childEffective}) must be less than or equal to parent BOM effective date ({$parentEffectiveDate}).");
        }

        if ($parentExpiryDate) {
            $childExpiry = $childBom->expiry_date ? Carbon::parse($childBom->expiry_date)->toDateString() : null;
            if ($childExpiry !== null && $childExpiry < $parentExpiryDate) {
                throw new InvalidArgumentException("Child BOM expiry date ({$childExpiry}) must outlast parent BOM expiry date ({$parentExpiryDate}).");
            }
        }
    }

    private function detectCircularChildLink(int $currentBomId, int $parentBomId, array $visited): void
    {
        if ($currentBomId === $parentBomId) {
            throw new InvalidArgumentException("Circular child BOM dependency detected.");
        }

        if (isset($visited[$currentBomId])) {
            return;
        }
        $visited[$currentBomId] = true;

        $bom = ProductionBom::withoutGlobalScopes()->with(['operationMaterials', 'items'])->find($currentBomId);
        if ($bom) {
            $links = $bom->operationMaterials->isNotEmpty() ? $bom->operationMaterials : $bom->items;
            foreach ($links as $row) {
                if (!empty($row->child_bom_id)) {
                    $this->detectCircularChildLink((int) $row->child_bom_id, $parentBomId, $visited);
                }
            }
        }
    }

    /**
     * Update warning status for all parent BOMs that use a specific component product or child BOM.
     */
    public function updateParentBomWarningsForComponent(int $productId, ?int $bomId = null): void
    {
        $opMatBomIds = RoutingOperationMaterial::withoutGlobalScopes()
            ->where(function ($q) use ($productId, $bomId) {
                $q->where('material_id', $productId);
                if ($bomId) {
                    $q->orWhere('child_bom_id', $bomId);
                }
            })
            ->pluck('bom_id');

        $itemBomIds = ProductionBomItem::withoutGlobalScopes()
            ->where(function ($q) use ($productId, $bomId) {
                $q->where('material_id', $productId);
                if ($bomId) {
                    $q->orWhere('child_bom_id', $bomId);
                }
            })
            ->pluck('bom_id');

        $parentBomIds = $opMatBomIds
            ->merge($itemBomIds)
            ->unique()
            ->filter();

        foreach ($parentBomIds as $parentBomId) {
            $parentBom = ProductionBom::withoutGlobalScopes()->find($parentBomId);
            if ($parentBom) {
                $parentBom->refreshWarningStatus();
            }
        }
    }

    /**
     * Validate that a BOM and every sub-assembly BOM in its execution tree are active/approved
     * and non-expired (BM-IMPL-08 #12).
     *
     * @throws \InvalidArgumentException
     */
    public function validateBomTreeForExecution(ProductionBom $bom, int $tenantId, array &$visited = []): void
    {
        if (isset($visited[$bom->id])) {
            return;
        }
        $visited[$bom->id] = true;

        $isExpired = $bom->expiry_date && \Carbon\Carbon::parse($bom->expiry_date)->lt(now()->startOfDay());
        if (!in_array($bom->status, ['approved', 'active'], true) || $isExpired) {
            $reason = $isExpired ? 'expired' : "status '{$bom->status}'";
            throw new \InvalidArgumentException(
                "BOM {$bom->bom_number} v{$bom->version} is not active ({$reason}) and cannot be executed in production."
            );
        }

        $bom->loadMissing(['operationMaterials.material', 'items.material']);
        $allocations = ($bom->operationMaterials->isNotEmpty() ? $bom->operationMaterials : $bom->items)
            ->where('is_alternative', false);

        foreach ($allocations as $row) {
            $material = $row->material;
            $compName = $material?->name ?? "Material #{$row->material_id}";

            if (!empty($row->child_bom_id)) {
                $childBom = ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->find($row->child_bom_id);

                if (!$childBom) {
                    throw new \InvalidArgumentException(
                        "Sub-assembly '{$compName}' references a missing sub-BOM (#{$row->child_bom_id})."
                    );
                }

                $childExpired = $childBom->expiry_date && \Carbon\Carbon::parse($childBom->expiry_date)->lt(now()->startOfDay());
                if (!in_array($childBom->status, ['approved', 'active'], true) || $childExpired) {
                    $reason = $childExpired ? 'expired' : "status '{$childBom->status}'";
                    throw new \InvalidArgumentException(
                        "Sub-assembly '{$compName}' references inactive sub-BOM {$childBom->bom_number} v{$childBom->version} ({$reason}). Activate or replace the sub-BOM before creating production orders or plans."
                    );
                }

                $this->validateBomTreeForExecution($childBom, $tenantId, $visited);
            } elseif (
                $material
                && in_array($material->type, ['semi_finished', 'semi_finished_goods'], true)
                && in_array($material->supplier_method ?? 'manufacture', ['manufacture', 'both'], true)
            ) {
                $activeChildBom = ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $material->id)
                    ->whereIn('status', ['approved', 'active'])
                    ->where(function ($q) {
                        $q->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>=', now()->toDateString());
                    })
                    ->orderByRaw("CASE WHEN bom_type = 'manufacturing' THEN 1 WHEN bom_type = 'subcontracting' THEN 2 ELSE 3 END")
                    ->first();

                if (!$activeChildBom) {
                    throw new \InvalidArgumentException(
                        "Manufactured sub-assembly '{$compName}' has no active or approved sub-BOM. Create and activate a sub-BOM before executing production."
                    );
                }

                $this->validateBomTreeForExecution($activeChildBom, $tenantId, $visited);
            }
        }
    }
}
