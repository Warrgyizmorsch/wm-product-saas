<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionEco;
use App\Domains\Production\Models\ProductionEcoApproval;
use App\Domains\Production\Models\ProductionEcoItem;
use App\Domains\Production\Models\Routing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionEcoService
{
    public function __construct(
        private readonly ProductionEcoNumberService $numberService,
        private readonly ProductionEcoImpactAnalysisService $impactAnalysisService
    ) {
    }

    /**
     * Create a new draft ECO.
     */
    public function createEco(array $data, ?int $userId = null): ProductionEco
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;
        $ecoNumber = $this->numberService->generateNextNumber($tenantId);

        $currentBom = ProductionBom::where('tenant_id', $tenantId)
            ->where('product_id', $data['product_id'])
            ->where('status', 'approved')
            ->orderBy('revision', 'desc')
            ->first();

        $currentRouting = Routing::where('tenant_id', $tenantId)
            ->where('product_id', $data['product_id'])
            ->where('status', Routing::STATUS_ACTIVE)
            ->orderBy('revision', 'desc')
            ->first();

        $isBomChange = in_array($data['change_type'], [ProductionEco::CHANGE_TYPE_BOM, ProductionEco::CHANGE_TYPE_BOM_AND_ROUTING], true);
        $isRoutingChange = in_array($data['change_type'], [ProductionEco::CHANGE_TYPE_ROUTING, ProductionEco::CHANGE_TYPE_BOM_AND_ROUTING], true);

        $proposedBom = !empty($data['proposed_bom_id']) ? ProductionBom::find($data['proposed_bom_id']) : null;
        $proposedRouting = !empty($data['proposed_routing_id']) ? Routing::find($data['proposed_routing_id']) : null;

        return DB::transaction(function () use ($data, $ecoNumber, $tenantId, $currentBom, $currentRouting, $proposedBom, $proposedRouting, $isBomChange, $isRoutingChange, $userId) {
            $eco = ProductionEco::create([
                'tenant_id' => $tenantId,
                'eco_number' => $ecoNumber,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'reason' => $data['reason'] ?? null,
                'change_type' => $data['change_type'],
                'product_id' => $data['product_id'],
                'current_bom_id' => $currentBom ? $currentBom->id : ($data['current_bom_id'] ?? null),
                'proposed_bom_id' => $isBomChange ? ($proposedBom ? $proposedBom->id : ($data['proposed_bom_id'] ?? null)) : null,
                'current_bom_revision' => $currentBom ? $currentBom->revision : 0,
                'proposed_bom_revision' => $isBomChange ? ($proposedBom ? $proposedBom->revision : ($currentBom ? $currentBom->revision + 1 : 1)) : ($currentBom ? $currentBom->revision : 0),
                'current_routing_id' => $currentRouting ? $currentRouting->id : ($data['current_routing_id'] ?? null),
                'proposed_routing_id' => $isRoutingChange ? ($proposedRouting ? $proposedRouting->id : ($data['proposed_routing_id'] ?? null)) : null,
                'current_routing_revision' => $currentRouting ? $currentRouting->revision : 0,
                'proposed_routing_revision' => $isRoutingChange ? ($proposedRouting ? $proposedRouting->revision : ($currentRouting ? $currentRouting->revision + 1 : 1)) : ($currentRouting ? $currentRouting->revision : 0),
                'effective_date' => $data['effective_date'] ?? Carbon::today()->toDateString(),
                'status' => ProductionEco::STATUS_DRAFT,
                'created_by' => $userId ?: (auth()->id() ?: 1),
            ]);

            // Save initial change line-items if provided
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    ProductionEcoItem::create([
                        'tenant_id' => $tenantId,
                        'eco_id' => $eco->id,
                        'entity_type' => $itemData['entity_type'],
                        'action_type' => $itemData['action_type'],
                        'target_id' => $itemData['target_id'] ?? null,
                        'old_value' => $itemData['old_value'] ?? null,
                        'new_value' => $itemData['new_value'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }
            }

            // Create initial log history
            ProductionEcoApproval::create([
                'tenant_id' => $tenantId,
                'eco_id' => $eco->id,
                'user_id' => $eco->created_by,
                'action' => 'Created',
                'comments' => 'Initial ECO draft created.',
            ]);

            return $eco;
        });
    }

    /**
     * Submit ECO for engineering review.
     */
    public function submitForReview(ProductionEco|int $ecoOrId, ?int $userId = null): ProductionEco
    {
        $eco = is_numeric($ecoOrId) ? ProductionEco::findOrFail($ecoOrId) : $ecoOrId;

        if (!$eco->isDraft()) {
            throw new InvalidArgumentException("Only DRAFT ECOs can be submitted for review.");
        }

        $userId = $userId ?: (auth()->id() ?: 1);

        $eco->update(['status' => ProductionEco::STATUS_UNDER_REVIEW]);

        ProductionEcoApproval::create([
            'tenant_id' => $eco->tenant_id,
            'eco_id' => $eco->id,
            'user_id' => $userId,
            'action' => 'Submitted',
            'comments' => 'Submitted for engineering change review.',
        ]);

        return $eco;
    }

    /**
     * Approve ECO.
     */
    public function approve(ProductionEco|int $ecoOrId, ?string $comments = null, ?int $userId = null): ProductionEco
    {
        $eco = is_numeric($ecoOrId) ? ProductionEco::findOrFail($ecoOrId) : $ecoOrId;

        if (!$eco->isUnderReview()) {
            throw new InvalidArgumentException("Only ECOs under review can be approved.");
        }

        $userId = $userId ?: (auth()->id() ?: 1);

        $eco->update([
            'status' => ProductionEco::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        ProductionEcoApproval::create([
            'tenant_id' => $eco->tenant_id,
            'eco_id' => $eco->id,
            'user_id' => $userId,
            'action' => 'Approved',
            'comments' => $comments ?: 'ECO approved by engineering authority.',
        ]);

        return $eco;
    }

    /**
     * Reject ECO.
     */
    public function reject(ProductionEco|int $ecoOrId, ?string $comments = null, ?int $userId = null): ProductionEco
    {
        $eco = is_numeric($ecoOrId) ? ProductionEco::findOrFail($ecoOrId) : $ecoOrId;

        if (!$eco->isUnderReview()) {
            throw new InvalidArgumentException("Only ECOs under review can be rejected.");
        }

        $userId = $userId ?: (auth()->id() ?: 1);

        $eco->update(['status' => ProductionEco::STATUS_REJECTED]);

        ProductionEcoApproval::create([
            'tenant_id' => $eco->tenant_id,
            'eco_id' => $eco->id,
            'user_id' => $userId,
            'action' => 'Rejected',
            'comments' => $comments ?: 'ECO rejected during engineering review.',
        ]);

        return $eco;
    }

    /**
     * Release ECO: activates proposed BOM/Routing revisions with effective date.
     */
    public function release(ProductionEco|int $ecoOrId, ?int $userId = null): ProductionEco
    {
        $eco = is_numeric($ecoOrId) ? ProductionEco::findOrFail($ecoOrId) : $ecoOrId;

        if (!$eco->isApproved()) {
            throw new InvalidArgumentException("Only APPROVED ECOs can be released.");
        }

        $userId = $userId ?: (auth()->id() ?: 1);
        $effectiveDate = $eco->effective_date ? $eco->effective_date->toDateString() : Carbon::today()->toDateString();

        return DB::transaction(function () use ($eco, $userId, $effectiveDate) {
            // 1. Release BOM revision if applicable
            if (in_array($eco->change_type, [ProductionEco::CHANGE_TYPE_BOM, ProductionEco::CHANGE_TYPE_BOM_AND_ROUTING], true)) {
                if ($eco->current_bom_id) {
                    $currentBom = ProductionBom::find($eco->current_bom_id);
                    if ($currentBom) {
                        $currentBom->update([
                            'status' => 'inactive',
                            'expiry_date' => $effectiveDate,
                        ]);
                    }
                }

                if ($eco->proposed_bom_id) {
                    $proposedBom = ProductionBom::find($eco->proposed_bom_id);
                    if ($proposedBom) {
                        $proposedBom->update([
                            'status' => 'approved',
                            'revision' => $eco->proposed_bom_revision,
                            'effective_date' => $effectiveDate,
                        ]);
                    }
                }
            }

            // 2. Release Routing revision if applicable
            if (in_array($eco->change_type, [ProductionEco::CHANGE_TYPE_ROUTING, ProductionEco::CHANGE_TYPE_BOM_AND_ROUTING], true)) {
                if ($eco->current_routing_id) {
                    $currentRouting = Routing::find($eco->current_routing_id);
                    if ($currentRouting) {
                        $currentRouting->update([
                            'status' => Routing::STATUS_HISTORICAL,
                            'effective_to' => $effectiveDate,
                        ]);
                    }
                }

                if ($eco->proposed_routing_id) {
                    $proposedRouting = Routing::find($eco->proposed_routing_id);
                    if ($proposedRouting) {
                        $proposedRouting->update([
                            'status' => Routing::STATUS_ACTIVE,
                            'revision' => $eco->proposed_routing_revision,
                            'effective_from' => $effectiveDate,
                        ]);
                    }
                }
            }

            // 3. Mark ECO as RELEASED
            $eco->update([
                'status' => ProductionEco::STATUS_RELEASED,
                'released_by' => $userId,
                'released_at' => now(),
            ]);

            ProductionEcoApproval::create([
                'tenant_id' => $eco->tenant_id,
                'eco_id' => $eco->id,
                'user_id' => $userId,
                'action' => 'Released',
                'comments' => "ECO released into production with effective date {$effectiveDate}.",
            ]);

            return $eco;
        });
    }

    /**
     * Close ECO.
     */
    public function close(ProductionEco|int $ecoOrId, ?int $userId = null): ProductionEco
    {
        $eco = is_numeric($ecoOrId) ? ProductionEco::findOrFail($ecoOrId) : $ecoOrId;

        if (!$eco->isReleased()) {
            throw new InvalidArgumentException("Only RELEASED ECOs can be closed.");
        }

        $userId = $userId ?: (auth()->id() ?: 1);

        $eco->update([
            'status' => ProductionEco::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        ProductionEcoApproval::create([
            'tenant_id' => $eco->tenant_id,
            'eco_id' => $eco->id,
            'user_id' => $userId,
            'action' => 'Closed',
            'comments' => 'ECO officially closed.',
        ]);

        return $eco;
    }

    /**
     * Cancel ECO.
     */
    public function cancel(ProductionEco|int $ecoOrId, ?string $reason = null, ?int $userId = null): ProductionEco
    {
        $eco = is_numeric($ecoOrId) ? ProductionEco::findOrFail($ecoOrId) : $ecoOrId;

        if ($eco->isReleased() || $eco->isClosed()) {
            throw new InvalidArgumentException("Released or Closed ECOs cannot be cancelled.");
        }

        $userId = $userId ?: (auth()->id() ?: 1);

        $eco->update(['status' => ProductionEco::STATUS_CANCELLED]);

        ProductionEcoApproval::create([
            'tenant_id' => $eco->tenant_id,
            'eco_id' => $eco->id,
            'user_id' => $userId,
            'action' => 'Cancelled',
            'comments' => $reason ?: 'ECO cancelled.',
        ]);

        return $eco;
    }

    /**
     * Run impact analysis on an ECO.
     */
    public function analyzeImpact(ProductionEco|int $ecoOrId): array
    {
        $eco = is_numeric($ecoOrId) ? ProductionEco::findOrFail($ecoOrId) : $ecoOrId;
        return $this->impactAnalysisService->analyzeImpact($eco);
    }
}
