<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionBomApproval;
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
            $bomNumber = $this->numberService->generateNextNumber($tenantId);
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
                'status' => 'draft',
                'created_by' => $creatorUserId,
                'revision' => 0,
            ]);

            $bom = $this->bomRepository->create($bomData);

            foreach ($dto->items as $index => $itemDto) {
                ProductionBomItem::create(array_merge($itemDto->toArray(), [
                    'tenant_id' => $tenantId,
                    'bom_id' => $bom->id,
                    'sequence' => $itemDto->sequence ?: ($index + 1),
                ]));
            }

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
            $bomData = $dto->toArray();
            $bom->update($bomData);

            // Recreate items to handle dynamic grids correctly
            $bom->items()->delete();

            foreach ($dto->items as $index => $itemDto) {
                ProductionBomItem::create(array_merge($itemDto->toArray(), [
                    'tenant_id' => $tenantId,
                    'bom_id' => $bom->id,
                    'sequence' => $itemDto->sequence ?: ($index + 1),
                ]));
            }

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

        return DB::transaction(function () use ($bom, $approverUserId) {
            // 1. Deactivate previously approved BOMs for the same product
            ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $bom->tenant_id)
                ->where('product_id', $bom->product_id)
                ->where('status', 'approved')
                ->where('id', '!=', $bom->id)
                ->update([
                    'status' => 'inactive',
                    'expiry_date' => Carbon::now()->toDateString(),
                ]);

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

            foreach ($original->items as $item) {
                ProductionBomItem::create([
                    'tenant_id' => $tenantId,
                    'bom_id' => $newBom->id,
                    'material_id' => $item->material_id,
                    'quantity' => $item->quantity,
                    'uom_id' => $item->uom_id,
                    'material_scrap_percentage' => $item->material_scrap_percentage,
                    'is_alternative' => $item->is_alternative,
                    'alternative_group' => $item->alternative_group,
                    'priority' => $item->priority,
                    'sequence' => $item->sequence,
                    'effective_from' => $item->effective_from,
                    'effective_to' => $item->effective_to,
                    'notes' => $item->notes,
                ]);
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
                'uom_code' => $item->uom ? $item->uom->code : 'PCS',
                'is_alternative' => $item->is_alternative,
                'alternative_group' => $item->alternative_group,
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
}
