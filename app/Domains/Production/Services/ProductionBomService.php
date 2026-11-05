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
        if (empty($bomNumber) || $bomNumber === 'AUTO') {
            $bomNumber = $this->numberService->generateNextNumber($tenantId, null, $dto->product_id);
        } else {
            if (!$this->numberService->validateNumber($bomNumber, $tenantId)) {
                throw new InvalidArgumentException("BOM number format is invalid. Use alphanumeric characters, hyphens, underscores or slashes.");
            }
            if ($this->numberService->isDuplicate($bomNumber, $tenantId)) {
                throw new InvalidArgumentException("A BOM with number '{$bomNumber}' already exists.");
            }
        }

        $this->checkBomConflicts($dto->product_id, $dto->version);
        $this->bomLifecycleService->validateEffectiveDates(
            $dto->effective_date,
            $dto->expiry_date,
            $dto->product_id,
            null,
            $tenantId,
            $dto->bom_type
        );

        return DB::transaction(function () use ($dto, $bomNumber, $creatorUserId, $tenantId) {
            $bomData = array_merge($dto->toArray(), [
                'tenant_id' => $tenantId,
                'bom_number' => $bomNumber,
                'usage_context' => $dto->usage_context ?? 'manufacturing',
                'status' => 'draft',
                'created_by' => $creatorUserId,
                'revision' => 0,
            ]);

            $bom = $this->bomRepository->create($bomData);

            $this->persistBomItems($bom, $dto->items, $tenantId);

            // Create Approval History Log
            ProductionBomApproval::create([
                'tenant_id' => $tenantId,
                'bom_id' => $bom->id,
                'user_id' => $creatorUserId,
                'action' => 'Created',
                'comments' => 'Initial BOM draft created.',
            ]);

            event(new \App\Domains\Production\Events\BomCreated($bom));

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

        $this->checkBomConflicts($dto->product_id, $dto->version, $id);
        $this->bomLifecycleService->validateEffectiveDates(
            $dto->effective_date,
            $dto->expiry_date,
            $dto->product_id,
            $id,
            $bom->tenant_id,
            $dto->bom_type
        );

        $tenantId = $bom->tenant_id;

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

        return DB::transaction(function () use ($bom) {
            $bom->update(['status' => 'pending_approval']);

            ProductionBomApproval::create([
                'tenant_id' => $bom->tenant_id,
                'bom_id' => $bom->id,
                'user_id' => auth()->id() ?: 1,
                'action' => 'Submitted',
                'comments' => 'Submitted for engineering review and approval.',
            ]);

            return $bom;
        });
    }

    /**
     * Approve BOM and deactivate any older active version.
     */
    public function approve(int $id, int $approverUserId): ProductionBom
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

            // [TRACK-06] Inverted approval check: BOM requires its linked routing to be active/approved
            $routing = $bom->routing;
            if (!$routing || !in_array($routing->status, [Routing::STATUS_ACTIVE, Routing::STATUS_SCHEDULED, 'approved'], true)) {
                throw new InvalidArgumentException("Cannot approve BOM: The linked Routing must be approved and active first.");
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
     * Reject a pending BOM.
     */
    public function reject(int $id, int $approverUserId, ?string $comments = null): ProductionBom
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
    public function cancel(int $id, int $userId, ?string $comments = null): ProductionBom
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
     * Duplicate a BOM as a new draft/revision.
     */
    public function duplicateVersion(int $id, string $newVersion, ?int $creatorUserId = null): ProductionBom
    {
        $original = $this->bomRepository->getBomWithComponents($id);

        if (!$original) {
            throw new InvalidArgumentException("Source BOM not found.");
        }

        $tenantId = $original->tenant_id;
        $this->checkBomConflicts($original->product_id, $newVersion);
        $this->bomLifecycleService->validateEffectiveDates(
            Carbon::now()->toDateString(),
            null,
            $original->product_id,
            null,
            $tenantId,
            $original->bom_type
        );

        return DB::transaction(function () use ($original, $newVersion, $creatorUserId, $tenantId) {
            $newBom = ProductionBom::create([
                'tenant_id' => $tenantId,
                'bom_number' => $original->bom_number,
                'bom_name' => $original->bom_name,
                'bom_type' => $original->bom_type,
                'usage_context' => $original->usage_context ?? 'manufacturing',
                'product_id' => $original->product_id,
                'routing_id' => $original->routing_id, // [TRACK-10] Duplicate preserves routing link
                'base_quantity' => $original->base_quantity,
                'base_uom_id' => $original->base_uom_id,
                'version' => $newVersion,
                'revision' => $original->revision + 1,
                'effective_date' => Carbon::now()->toDateString(),
                'status' => 'draft',
                'notes' => "Duplicated from version {$original->version}. " . $original->notes,
                'created_by' => $creatorUserId,
            ]);

            $oldToNewBomItemIdMap = [];
            // Pass 1: Clone Master BOM Items (production_bom_items)
            foreach ($original->items as $item) {
                $newItem = ProductionBomItem::create([
                    'tenant_id'                 => $tenantId,
                    'bom_id'                    => $newBom->id,
                    'material_id'               => $item->material_id,
                    'quantity'                  => $item->quantity,
                    'uom_id'                    => $item->uom_id,
                    'allocation_role'           => $item->allocation_role ?? 'primary',
                    'material_scrap_percentage' => $item->material_scrap_percentage ?? 0.00,
                ]);

                $oldToNewBomItemIdMap[$item->id] = $newItem;
            }

            foreach ($original->items as $item) {
                if ($item->replaces_item_id && isset($oldToNewBomItemIdMap[$item->replaces_item_id])) {
                    $oldToNewBomItemIdMap[$item->id]->replaces_item_id = $oldToNewBomItemIdMap[$item->replaces_item_id]->id;
                }
            }

            // Pass 2: Clone Operation Allocations (production_routing_operation_materials)
            $oldToNewOpMatMap = [];
            $createdOpMatPairs = [];

            foreach ($original->operationMaterials as $opMat) {
                if ($opMat->child_bom_id) {
                    $this->validateChildBomLink(
                        $opMat->child_bom_id,
                        $opMat->material_id,
                        $newBom->id,
                        Carbon::parse($newBom->effective_date)->toDateString(),
                        $newBom->expiry_date ? Carbon::parse($newBom->expiry_date)->toDateString() : null
                    );
                }

                $newOpMat = RoutingOperationMaterial::create([
                    'tenant_id'                    => $tenantId,
                    'bom_id'                       => $newBom->id,
                    'bom_item_id'                  => isset($oldToNewBomItemIdMap[$opMat->bom_item_id]) ? $oldToNewBomItemIdMap[$opMat->bom_item_id]->id : null,
                    'routing_operation_id'         => $opMat->routing_operation_id,
                    'material_id'                  => $opMat->material_id,
                    'child_bom_id'                 => $opMat->child_bom_id,
                    'quantity'                     => $opMat->quantity,
                    'quantity_type'                => $opMat->quantity_type ?? 'fixed',
                    'formula'                      => $opMat->formula,
                    'uom_id'                       => $opMat->uom_id,
                    'is_alternative'               => (bool) $opMat->is_alternative,
                    'priority'                     => $opMat->priority,
                    'replaces_routing_material_id' => null, // resolved below
                    'material_scrap_percentage'    => $opMat->material_scrap_percentage ?? 0.00,
                    'sequence'                     => $opMat->sequence,
                    'notes'                        => $opMat->notes,
                    'consumption_type'             => $opMat->consumption_type,
                ]);

                $oldToNewOpMatMap[$opMat->id] = $newOpMat->id;
                $createdOpMatPairs[] = ['old' => $opMat, 'new' => $newOpMat];
            }

            // Pass 3: Re-link alternative replacements
            foreach ($createdOpMatPairs as $pair) {
                $oldOpMat = $pair['old'];
                $newOpMat = $pair['new'];

                if ($oldOpMat->is_alternative && $oldOpMat->replaces_routing_material_id) {
                    $targetNewId = $oldToNewOpMatMap[$oldOpMat->replaces_routing_material_id] ?? null;
                    if ($targetNewId) {
                        $newOpMat->update(['replaces_routing_material_id' => $targetNewId]);
                    }
                }
            }

            // Log revision creation
            ProductionBomApproval::create([
                'tenant_id' => $tenantId,
                'bom_id' => $newBom->id,
                'user_id' => $creatorUserId,
                'action' => 'Revision Created',
                'comments' => "Revision version {$newVersion} created from version {$original->version}.",
            ]);

            event(new \App\Domains\Production\Events\BomVersionCreated($newBom));

            return $newBom;
        });
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
            $hasPrimary = false;
            $hasAlternative = false;
            $uomId = $dtos[0]->uom_id;

            foreach ($dtos as $dto) {
                $totalQuantity += (float) $dto->quantity;
                $isEffectiveAlt = !empty($dto->is_alternative) || isset($altOpIdsMap[(string) $dto->routing_operation_id]);
                if ($isEffectiveAlt) {
                    $hasAlternative = true;
                } else {
                    $hasPrimary = true;
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
                    $bom->expiry_date ? Carbon::parse($bom->expiry_date)->toDateString() : null
                );
            } else {
                // Auto-bind to the currently active/approved BOM for manufactured sub-assemblies
                $defaultChildBom = ProductionBom::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $itemDto->material_id)
                    ->whereIn('status', ['approved', 'active'])
                    ->where(function ($q) {
                        $q->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>=', Carbon::today()->toDateString());
                    })
                    ->orderByDesc('id')
                    ->first();

                if ($defaultChildBom) {
                    $childBomId = $defaultChildBom->id;
                }
            }

            $parentBomItem = $materialToBomItemMap[$itemDto->material_id] ?? null;

            // Priority: strictly null for primary component; integer for alternative
            $priority = !empty($itemDto->is_alternative) ? ($itemDto->priority ?? 1) : null;

            $createdOpMat = RoutingOperationMaterial::create([
                'tenant_id'                    => $tenantId,
                'bom_id'                       => $bom->id,
                'bom_item_id'                  => $parentBomItem?->id,
                'routing_operation_id'         => $itemDto->routing_operation_id,
                'material_id'                  => $itemDto->material_id,
                'child_bom_id'                 => $childBomId,
                'quantity'                     => (float) $itemDto->quantity,
                'quantity_type'                => $itemDto->quantity_type ?? 'fixed',
                'formula'                      => $itemDto->formula,
                'uom_id'                       => $itemDto->uom_id,
                'is_alternative'               => (bool) $itemDto->is_alternative,
                'priority'                     => $priority,
                'replaces_routing_material_id' => null, // resolved in Pass 2
                'material_scrap_percentage'    => $itemDto->material_scrap_percentage ?? 0.00,
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
                    if ($targetModel->bom_id === $bom->id && !$targetModel->is_alternative) {
                        $model->update(['replaces_routing_material_id' => $targetModel->id]);
                    }
                }
            }
        }
    }

    /**
     * [TRACK-02] Sync BOM component allocations into production_routing_operation_materials.
     * Maintained for backwards-compatibility; persistBomItems now handles native 2-tier storage.
     */
    public function syncRoutingOperationMaterials(ProductionBom $bom): void
    {
        // Handled directly inside persistBomItems
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

        foreach ($bom->items as $item) {
            // Formula: Required Qty = (Quantity * Multiplier) * (1 + Scrap%/100)
            $netQty = $item->quantity * $multiplier;
            $grossQty = $this->calculateRequiredMaterial($netQty, $item->material_scrap_percentage);

            $requirements[] = [
                'material_id' => $item->material_id,
                'material_name' => $item->material->name,
                'material_sku' => $item->material->sku,
                'net_quantity' => $netQty,
                'gross_quantity' => $grossQty,
                'wastage_percentage' => $item->material_scrap_percentage, // compatibility fallback
                'material_scrap_percentage' => $item->material_scrap_percentage,
                'uom_code' => $item->uom?->code ?? $item->material?->uom?->code ?? 'PCS',
                'is_alternative' => $item->isAlternative(),
                'replaces_item_id' => null,
                'priority' => null,
            ];
        }

        return $requirements;
    }

    public function checkBomConflicts(int $productId, string $version, ?int $ignoreBomId = null): void
    {
        $query = ProductionBom::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->where('version', $version);

        if ($ignoreBomId !== null) {
            $query->where('id', '!=', $ignoreBomId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException("A BOM version '{$version}' already exists for this product.");
        }
    }

    private function validateChildBomLink(
        int $childBomId,
        int $materialProductId,
        ?int $parentBomId,
        string $parentEffectiveDate,
        ?string $parentExpiryDate
    ): void {
        if ($parentBomId && $childBomId === $parentBomId) {
            throw new InvalidArgumentException("A BOM cannot link to itself as a child BOM.");
        }

        $childBom = ProductionBom::withoutGlobalScopes()->find($childBomId);
        if (!$childBom) {
            throw new InvalidArgumentException("Linked child BOM not found.");
        }

        if ($childBom->product_id !== $materialProductId) {
            throw new InvalidArgumentException("Linked child BOM product does not match component material product.");
        }

        if ($parentBomId) {
            $visited = [$parentBomId => true];
            $this->detectCircularChildLink($childBomId, $parentBomId, $visited);
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

        $bom = ProductionBom::withoutGlobalScopes()->with('items')->find($currentBomId);
        if ($bom) {
            foreach ($bom->items as $item) {
                if ($item->child_bom_id) {
                    $this->detectCircularChildLink($item->child_bom_id, $parentBomId, $visited);
                }
            }
        }
    }

    /**
     * Update warning status for all parent BOMs that use a specific component product or child BOM.
     */
    public function updateParentBomWarningsForComponent(int $productId, ?int $bomId = null): void
    {
        $parentBomIds = ProductionBomItem::withoutGlobalScopes()
            ->where(function ($q) use ($productId, $bomId) {
                $q->where('material_id', $productId);
                if ($bomId) {
                    $q->orWhere('child_bom_id', $bomId);
                }
            })
            ->pluck('bom_id')
            ->unique()
            ->filter();

        foreach ($parentBomIds as $parentBomId) {
            $parentBom = ProductionBom::withoutGlobalScopes()->find($parentBomId);
            if ($parentBom) {
                $parentBom->refreshWarningStatus();
            }
        }
    }
}
