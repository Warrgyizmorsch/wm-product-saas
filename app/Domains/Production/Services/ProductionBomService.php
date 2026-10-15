<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionBomApproval;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use App\Domains\Production\Repositories\ProductionBomRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionBomService
{
    public function __construct(
        private readonly ProductionBomRepositoryInterface $bomRepository,
        private readonly ProductionBomNumberService $numberService
    ) {
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

        $tenantId = $bom->tenant_id;

        return DB::transaction(function () use ($bom, $dto, $tenantId) {
            $bomData = array_merge($dto->toArray(), [
                'usage_context' => $dto->usage_context ?? 'manufacturing',
            ]);
            $bom->update($bomData);

            // Recreate items to handle dynamic grids correctly
            $bom->items()->delete();

            $this->persistBomItems($bom, $dto->items, $tenantId);

            return $bom->load('items');
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

            // 1. Deactivate previously approved BOMs for the same product and BOM type
            $deactivateQuery = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $bom->tenant_id)
                ->where('product_id', $bom->product_id)
                ->where('status', 'approved')
                ->where('id', '!=', $bom->id);

            if (!empty($bom->bom_type)) {
                $deactivateQuery->where('bom_type', $bom->bom_type);
            }

            $deactivatedBomIds = (clone $deactivateQuery)->pluck('id')->toArray();

            $deactivateQuery->update([
                'status' => 'inactive',
                'expiry_date' => Carbon::now()->toDateString(),
            ]);

            // When a BOM becomes inactive, the routing connected to that BOM also becomes historical
            if (!empty($deactivatedBomIds)) {
                $linkedRoutings = Routing::withoutGlobalScopes()
                    ->where(function ($q) use ($deactivatedBomIds, $bom) {
                        $q->whereIn('bom_id', $deactivatedBomIds)
                          ->orWhere(function ($sub) use ($bom) {
                              $sub->whereNull('bom_id')
                                  ->where('product_id', $bom->product_id);
                          });
                    })
                    ->where('status', Routing::STATUS_ACTIVE)
                    ->get();

                foreach ($linkedRoutings as $linkedRouting) {
                    $linkedRouting->update([
                        'status' => Routing::STATUS_HISTORICAL,
                        'effective_to' => Carbon::now()->toDateString(),
                    ]);

                    RoutingApproval::create([
                        'tenant_id' => $linkedRouting->tenant_id,
                        'routing_id' => $linkedRouting->id,
                        'user_id' => $approverUserId,
                        'action' => RoutingApproval::ACTION_ARCHIVED,
                        'comments' => 'Automatically archived because connected BOM was superseded and deactivated.',
                    ]);
                }
            }

            // 2. Approve current BOM
            $bom->update([
                'status' => 'approved',
                'approved_by' => $approverUserId,
                'approved_at' => Carbon::now(),
            ]);

            // 3. Log approval history
            ProductionBomApproval::create([
                'tenant_id' => $bom->tenant_id,
                'bom_id' => $bom->id,
                'user_id' => $approverUserId,
                'action' => 'Approved',
                'comments' => 'BOM version approved and set active.',
            ]);

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
            $bom->update(['status' => 'cancelled']);

            ProductionBomApproval::create([
                'tenant_id' => $bom->tenant_id,
                'bom_id' => $bom->id,
                'user_id' => $userId,
                'action' => 'Cancelled',
                'comments' => $comments ?: 'BOM cancelled by user.',
            ]);

            // Archive any active routings connected to this cancelled BOM
            $linkedRoutings = Routing::withoutGlobalScopes()
                ->where(function ($q) use ($bom) {
                    $q->where('bom_id', $bom->id)
                      ->orWhere(function ($sub) use ($bom) {
                          $sub->whereNull('bom_id')
                              ->where('product_id', $bom->product_id);
                      });
                })
                ->where('status', Routing::STATUS_ACTIVE)
                ->get();

            foreach ($linkedRoutings as $linkedRouting) {
                $linkedRouting->update([
                    'status' => Routing::STATUS_HISTORICAL,
                    'effective_to' => Carbon::now()->toDateString(),
                ]);

                RoutingApproval::create([
                    'tenant_id' => $linkedRouting->tenant_id,
                    'routing_id' => $linkedRouting->id,
                    'user_id' => $userId,
                    'action' => RoutingApproval::ACTION_ARCHIVED,
                    'comments' => 'Automatically archived because connected BOM (' . $bom->bom_number . ') was cancelled.',
                ]);
            }

            return $bom;
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

        return DB::transaction(function () use ($original, $newVersion, $creatorUserId, $tenantId) {
            $newBom = ProductionBom::create([
                'tenant_id' => $tenantId,
                'bom_number' => $original->bom_number,
                'bom_name' => $original->bom_name,
                'bom_type' => $original->bom_type,
                'usage_context' => $original->usage_context ?? 'manufacturing',
                'product_id' => $original->product_id,
                'base_quantity' => $original->base_quantity,
                'base_uom_id' => $original->base_uom_id,
                'version' => $newVersion,
                'revision' => $original->revision + 1,
                'effective_date' => Carbon::now()->toDateString(),
                'status' => 'draft',
                'notes' => "Duplicated from version {$original->version}. " . $original->notes,
                'created_by' => $creatorUserId,
            ]);

            $oldToNewIdMap = [];
            $createdPairs = [];

            // Pass 1: Clone all component rows
            foreach ($original->items as $item) {
                if ($item->child_bom_id) {
                    $this->validateChildBomLink(
                        $item->child_bom_id,
                        $item->material_id,
                        $newBom->id,
                        Carbon::parse($newBom->effective_date)->toDateString(),
                        $newBom->expiry_date ? Carbon::parse($newBom->expiry_date)->toDateString() : null
                    );
                }

                $newItem = ProductionBomItem::create([
                    'tenant_id' => $tenantId,
                    'bom_id' => $newBom->id,
                    'material_id' => $item->material_id,
                    'child_bom_id' => $item->child_bom_id,
                    'quantity' => $item->quantity,
                    'quantity_type' => $item->quantity_type ?? 'fixed',
                    'formula' => $item->formula,
                    'uom_id' => $item->uom_id,
                    'material_scrap_percentage' => $item->material_scrap_percentage,
                    'is_alternative' => (bool) $item->is_alternative,
                    'replaces_item_id' => null, // resolved in Pass 2
                    'priority' => $item->priority ?? 1,
                    'sequence' => $item->sequence,
                    'notes' => $item->notes,
                ]);

                $oldToNewIdMap[$item->id] = $newItem->id;
                $createdPairs[] = ['old' => $item, 'new' => $newItem];
            }

            // Pass 2: Re-link alternative components to cloned primary items
            foreach ($createdPairs as $pair) {
                $oldItem = $pair['old'];
                $newItem = $pair['new'];

                if ($oldItem->is_alternative && $oldItem->replaces_item_id) {
                    $targetNewId = $oldToNewIdMap[$oldItem->replaces_item_id] ?? null;
                    if ($targetNewId) {
                        $newItem->update(['replaces_item_id' => $targetNewId]);
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
     * Persist BOM items using a two-pass algorithm to resolve primary/alternative links.
     */
    protected function persistBomItems(ProductionBom $bom, array $itemDtos, int $tenantId): void
    {
        $uidToItemMap = [];
        $createdList = [];

        // Pass 1: create all items with base attributes
        foreach ($itemDtos as $index => $itemDto) {
            if ($itemDto->child_bom_id) {
                $this->validateChildBomLink(
                    $itemDto->child_bom_id,
                    $itemDto->material_id,
                    $bom->id,
                    Carbon::parse($bom->effective_date)->toDateString(),
                    $bom->expiry_date ? Carbon::parse($bom->expiry_date)->toDateString() : null
                );
            }

            $itemData = $itemDto->toArray();
            unset($itemData['row_uid'], $itemData['replaces_row_uid']);

            $itemData = array_merge($itemData, [
                'tenant_id' => $tenantId,
                'bom_id' => $bom->id,
                'sequence' => $itemDto->sequence ?: ($index + 1),
                'replaces_item_id' => null, // resolved in pass 2
                'priority' => $itemDto->priority ?? 1,
            ]);

            $createdItem = ProductionBomItem::create($itemData);

            if (!empty($itemDto->row_uid)) {
                $uidToItemMap[$itemDto->row_uid] = $createdItem;
            }
            if (!empty($itemDto->id)) {
                $uidToItemMap[(string) $itemDto->id] = $createdItem;
            }
            $uidToItemMap[(string) $createdItem->id] = $createdItem;

            $createdList[] = [
                'dto' => $itemDto,
                'model' => $createdItem,
            ];
        }

        // Pass 2: resolve replaces_item_id for alternative components
        foreach ($createdList as $entry) {
            $itemDto = $entry['dto'];
            $model = $entry['model'];

            if ($itemDto->is_alternative) {
                $targetModel = null;
                if (!empty($itemDto->replaces_row_uid) && isset($uidToItemMap[$itemDto->replaces_row_uid])) {
                    $targetModel = $uidToItemMap[$itemDto->replaces_row_uid];
                } elseif (!empty($itemDto->replaces_item_id) && isset($uidToItemMap[(string) $itemDto->replaces_item_id])) {
                    $targetModel = $uidToItemMap[(string) $itemDto->replaces_item_id];
                } elseif (!empty($itemDto->replaces_item_id)) {
                    $existing = ProductionBomItem::where('bom_id', $bom->id)->find($itemDto->replaces_item_id);
                    if ($existing && !$existing->is_alternative && $existing->id !== $model->id) {
                        $targetModel = $existing;
                    }
                }

                if ($targetModel && $targetModel->id !== $model->id) {
                    if ($targetModel->bom_id === $bom->id && !$targetModel->is_alternative) {
                        $model->update(['replaces_item_id' => $targetModel->id]);
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
                'is_alternative' => (bool) $item->is_alternative,
                'replaces_item_id' => $item->replaces_item_id,
                'priority' => $item->priority,
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
}
