<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomApproval;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * BomLifecycleService
 *
 * Centralizes lifecycle status transitions, effective dates, expiry dates,
 * and version sequencing rules for Production Bills of Materials (BOMs).
 */
class BomLifecycleService
{
    /**
     * Validate effective start and expiry date rules.
     *
     * Rules:
     * 1. Start date must not be in the past (< today).
     * 2. Expiry date (if specified) must be on or after the start date.
     * 3. A new version must not start before the applicable active version's start date.
     *
     * @throws InvalidArgumentException
     */
    public function validateEffectiveDates(
        ?string $effectiveDate,
        ?string $expiryDate = null,
        int $productId = 0,
        ?int $ignoreBomId = null,
        ?int $tenantId = null,
        ?string $bomType = null
    ): void {
        $today = Carbon::today()->toDateString();
        $startDate = $effectiveDate ? Carbon::parse($effectiveDate)->toDateString() : $today;

        // Rule 1: Start date must not be in the past
        if ($startDate < $today) {
            throw new InvalidArgumentException(
                "The BOM start date ({$startDate}) cannot be in the past. Start date must be today ({$today}) or later."
            );
        }

        // Rule 2: Expiry date consistency
        if (!empty($expiryDate)) {
            $endDate = Carbon::parse($expiryDate)->toDateString();
            if ($endDate < $startDate) {
                throw new InvalidArgumentException(
                    "The BOM expiry date ({$endDate}) cannot be earlier than the effective start date ({$startDate})."
                );
            }
        }

        // Rule 3: Existing-version date rule
        // A new version cannot start before the applicable active version's start date
        if ($productId > 0) {
            $resolvedTenantId = $tenantId ?: (tenant_id() ?? 1);

            $activeQuery = ProductionBom::withoutGlobalScopes()
                ->where('tenant_id', $resolvedTenantId)
                ->where('product_id', $productId)
                ->whereIn('status', ['approved', 'active']);

            if (!empty($bomType)) {
                $activeQuery->where('bom_type', $bomType);
            }

            if ($ignoreBomId !== null) {
                $activeQuery->where('id', '!=', $ignoreBomId);
            }

            $activeBoms = $activeQuery->get();

            foreach ($activeBoms as $activeBom) {
                if ($activeBom->effective_date) {
                    $activeStartDate = Carbon::parse($activeBom->effective_date)->toDateString();
                    if ($startDate < $activeStartDate) {
                        throw new InvalidArgumentException(
                            "The BOM start date ({$startDate}) cannot be earlier than the existing active version's start date ({$activeStartDate})."
                        );
                    }
                }
            }
        }
    }

    /**
     * Transition a BOM to approved/active while retiring any active previous version.
     *
     * Rules:
     * - The previous active version's expiry date is set to the new version's start date.
     * - No validity gap between versions.
     * - Preserves the new version's configured start date.
     * - Safely and consistently updates all superseded active versions.
     *
     * @return int[] IDs of BOMs that were deactivated
     */
    public function transitionOnApproval(ProductionBom $bom, int $approverUserId): array
    {
        $today = Carbon::today()->toDateString();

        // Determine effective start date:
        // If planned start date is in the past (< today), back-dated entries are not allowed;
        // it automatically takes effect on the approval / action date (today).
        $plannedStartDate = $bom->effective_date
            ? Carbon::parse($bom->effective_date)->toDateString()
            : $today;

        $startDate = ($plannedStartDate < $today) ? $today : $plannedStartDate;
        $isFutureStart = ($startDate > $today);

        // Validate dates against active versions before applying approval transitions
        $this->validateEffectiveDates(
            $startDate,
            $bom->expiry_date ? Carbon::parse($bom->expiry_date)->toDateString() : null,
            $bom->product_id,
            $bom->id,
            $bom->tenant_id,
            $bom->bom_type
        );

        // 1. Identify currently active/scheduled version(s) for the same tenant and product
        $activeQuery = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $bom->tenant_id)
            ->where('product_id', $bom->product_id)
            ->whereIn('status', ['approved', 'active', 'scheduled'])
            ->where('id', '!=', $bom->id);

        if (!empty($bom->bom_type)) {
            $activeQuery->where('bom_type', $bom->bom_type);
        }

        $activeBoms = $activeQuery->get();
        $deactivatedBomIds = [];

        foreach ($activeBoms as $activeBom) {
            if ($isFutureStart) {
                // Future start date: current active version remains ACTIVE until that future start date arrives!
                // Its expiry_date is set to the new version's start date so there is no validity gap.
                $activeBom->update([
                    'status'      => 'approved',
                    'expiry_date' => $startDate,
                ]);

                ProductionBomApproval::create([
                    'tenant_id' => $activeBom->tenant_id,
                    'bom_id'    => $activeBom->id,
                    'user_id'   => $approverUserId,
                    'action'    => 'Scheduled Expiry',
                    'comments'  => "Scheduled to expire on {$startDate} when new BOM version {$bom->version} takes effect.",
                ]);
            } else {
                // Immediate/today start: supersede current active version to inactive
                $activeBom->update([
                    'status'      => 'inactive',
                    'expiry_date' => $startDate,
                ]);

                ProductionBomApproval::create([
                    'tenant_id' => $activeBom->tenant_id,
                    'bom_id'    => $activeBom->id,
                    'user_id'   => $approverUserId,
                    'action'    => 'Deactivated',
                    'comments'  => "Automatically superseded and expired on {$startDate} by new BOM version {$bom->version}.",
                ]);

                $deactivatedBomIds[] = $activeBom->id;
            }
        }

        // 2. Activate or schedule the new version
        $newStatus = $isFutureStart ? 'scheduled' : 'approved';

        $updateData = [
            'status'         => $newStatus,
            'effective_date' => $startDate,
            'approved_by'    => $approverUserId,
            'approved_at'    => Carbon::now(),
        ];

        // If approval was granted after the planned start date (outside same-day buffer), append an explanatory note
        if ($plannedStartDate < $today) {
            $existingNotes = trim($bom->notes ?? '');
            $delayNote = "Approval was granted on {$today} after the planned date ({$plannedStartDate}); effective start date was adjusted to {$today}.";
            $updateData['notes'] = $existingNotes ? ($existingNotes . "\n" . $delayNote) : $delayNote;
        }

        // If the new BOM had a past or conflicting expiry, clear it; otherwise keep configured future expiry
        if ($bom->expiry_date && Carbon::parse($bom->expiry_date)->toDateString() < $startDate) {
            $updateData['expiry_date'] = null;
        }

        $bom->update($updateData);

        return $deactivatedBomIds;
    }

    /**
     * Transition a BOM on cancellation.
     *
     * Rules:
     * - Status is set to 'cancelled'.
     * - Expiry date is set to today (the cancellation date).
     */
    public function transitionOnCancellation(ProductionBom $bom, int $userId, ?string $comments = null): void
    {
        $today = Carbon::now()->toDateString();

        $bom->update([
            'status'      => 'cancelled',
            'expiry_date' => $today,
        ]);

        ProductionBomApproval::create([
            'tenant_id' => $bom->tenant_id,
            'bom_id'    => $bom->id,
            'user_id'   => $userId,
            'action'    => 'Cancelled',
            'comments'  => $comments ?: 'BOM cancelled by user.',
        ]);
    }

    /**
     * Transition BOMs on Engineering Change Order (ECO) release.
     */
    public function transitionOnEcoRelease(
        ProductionBom $proposedBom,
        ?ProductionBom $currentBom,
        string $effectiveDate,
        int $userId,
        ?int $revision = null
    ): void {
        if ($currentBom) {
            $currentBom->update([
                'status'      => 'inactive',
                'expiry_date' => $effectiveDate,
            ]);

            ProductionBomApproval::create([
                'tenant_id' => $currentBom->tenant_id,
                'bom_id'    => $currentBom->id,
                'user_id'   => $userId,
                'action'    => 'Deactivated',
                'comments'  => "Deactivated and expired on {$effectiveDate} via ECO release.",
            ]);
        }

        $updateData = [
            'status'         => 'approved',
            'effective_date' => $effectiveDate,
            'expiry_date'    => null,
        ];
        if ($revision !== null) {
            $updateData['revision'] = $revision;
        }

        $proposedBom->update($updateData);

        // Synchronize unsubmitted draft routings to point to the newly approved BOM
        app(RoutingLifecycleService::class)->syncDraftRoutingsToApprovedBom($proposedBom);
    }
}
