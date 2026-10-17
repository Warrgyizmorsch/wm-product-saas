<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingApproval;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * RoutingLifecycleService
 *
 * Centralizes lifecycle status transitions, effective dates (from/to),
 * expiry dates, and version sequencing rules for Production Routings.
 */
class RoutingLifecycleService
{
    /**
     * Validate effective start (from) and end/expiry (to) date rules.
     *
     * Rules:
     * 1. Start date must not be in the past (< today).
     * 2. End date (effective_to), if specified, must be on or after the start date.
     * 3. A new version must not start before the applicable active version's start date.
     *
     * @throws InvalidArgumentException
     */
    public function validateEffectiveDates(
        ?string $effectiveFrom,
        ?string $effectiveTo = null,
        int $productId = 0,
        ?int $ignoreRoutingId = null,
        ?int $tenantId = null
    ): void {
        $today = Carbon::today()->toDateString();
        $startDate = $effectiveFrom ? Carbon::parse($effectiveFrom)->toDateString() : $today;

        // Rule 1: Start date must not be in the past
        if ($startDate < $today) {
            throw new InvalidArgumentException(
                "The routing start date ({$startDate}) cannot be in the past. Start date must be today ({$today}) or later."
            );
        }

        // Rule 2: End date consistency
        if (!empty($effectiveTo)) {
            $endDate = Carbon::parse($effectiveTo)->toDateString();
            if ($endDate < $startDate) {
                throw new InvalidArgumentException(
                    "The routing effective end date ({$endDate}) cannot be earlier than the effective start date ({$startDate})."
                );
            }
        }

        // Rule 3: Existing-version date rule
        // A new version cannot start before the applicable active version's start date
        if ($productId > 0) {
            $resolvedTenantId = $tenantId ?: (tenant_id() ?? 1);

            $activeQuery = Routing::withoutGlobalScopes()
                ->where('tenant_id', $resolvedTenantId)
                ->where('product_id', $productId)
                ->where('status', Routing::STATUS_ACTIVE);

            if ($ignoreRoutingId !== null) {
                $activeQuery->where('id', '!=', $ignoreRoutingId);
            }

            $activeRoutings = $activeQuery->get();

            foreach ($activeRoutings as $activeRouting) {
                if ($activeRouting->effective_from) {
                    $activeStartDate = Carbon::parse($activeRouting->effective_from)->toDateString();
                    if ($startDate < $activeStartDate) {
                        throw new InvalidArgumentException(
                            "The routing start date ({$startDate}) cannot be earlier than the existing active version's start date ({$activeStartDate})."
                        );
                    }
                }
            }
        }
    }

    /**
     * Transition a routing to approved/active while retiring any active previous version.
     *
     * Rules:
     * - The previous active version's effective_to is set to the new version's effective_from.
     * - No validity gap between versions.
     * - Preserves the new version's configured start date.
     * - Safely and consistently updates all superseded active versions.
     */
    public function transitionOnApproval(Routing $routing, int $approverUserId): void
    {
        $today = Carbon::today()->toDateString();

        // Determine effective start date:
        // If planned start date is in the past (< today), back-dated entries are not allowed;
        // it automatically takes effect on the approval / action date (today).
        $plannedStartDate = $routing->effective_from
            ? Carbon::parse($routing->effective_from)->toDateString()
            : $today;

        $startDate = ($plannedStartDate < $today) ? $today : $plannedStartDate;
        $isFutureStart = ($startDate > $today);

        // Validate dates against active versions before applying approval transitions
        $this->validateEffectiveDates(
            $startDate,
            $routing->effective_to ? Carbon::parse($routing->effective_to)->toDateString() : null,
            $routing->product_id,
            $routing->id,
            $routing->tenant_id
        );

        // 1. Identify currently active/scheduled version(s) for the same tenant and product
        $activeRoutings = Routing::withoutGlobalScopes()
            ->where('tenant_id', $routing->tenant_id)
            ->where('product_id', $routing->product_id)
            ->whereIn('status', [Routing::STATUS_ACTIVE, Routing::STATUS_SCHEDULED])
            ->where('id', '!=', $routing->id)
            ->get();

        foreach ($activeRoutings as $activeRouting) {
            if ($isFutureStart) {
                // Future start date: current active version remains ACTIVE until that future start date arrives!
                // Its effective_to is set to the new version's start date so there is no validity gap.
                $activeRouting->update([
                    'status'       => Routing::STATUS_ACTIVE,
                    'effective_to' => $startDate,
                ]);

                RoutingApproval::create([
                    'tenant_id'  => $activeRouting->tenant_id,
                    'routing_id' => $activeRouting->id,
                    'user_id'    => $approverUserId,
                    'action'     => RoutingApproval::ACTION_ARCHIVED,
                    'comments'   => "Scheduled to expire on {$startDate} when new routing version {$routing->version} takes effect.",
                ]);
            } else {
                // Immediate/today start: supersede current active version to historical
                $activeRouting->update([
                    'status'       => Routing::STATUS_HISTORICAL,
                    'effective_to' => $startDate,
                ]);

                RoutingApproval::create([
                    'tenant_id'  => $activeRouting->tenant_id,
                    'routing_id' => $activeRouting->id,
                    'user_id'    => $approverUserId,
                    'action'     => RoutingApproval::ACTION_ARCHIVED,
                    'comments'   => "Automatically archived and expired on {$startDate} by new routing version {$routing->version}.",
                ]);
            }
        }

        // 2. Activate or schedule the new version
        $newStatus = $isFutureStart ? Routing::STATUS_SCHEDULED : Routing::STATUS_ACTIVE;

        $updateData = [
            'status'         => $newStatus,
            'effective_from' => $startDate,
            'approved_by'    => $approverUserId,
            'approved_at'    => Carbon::now(),
        ];

        // If approval was granted after the planned start date (outside same-day buffer), append an explanatory note
        if ($plannedStartDate < $today) {
            $existingDesc = trim($routing->description ?? '');
            $delayNote = "Approval was granted on {$today} after the planned date ({$plannedStartDate}); effective start date was adjusted to {$today}.";
            $updateData['description'] = $existingDesc ? ($existingDesc . "\n" . $delayNote) : $delayNote;
        }

        // If the new routing had a past or conflicting end date, clear it; otherwise keep configured future end date
        if ($routing->effective_to && Carbon::parse($routing->effective_to)->toDateString() < $startDate) {
            $updateData['effective_to'] = null;
        }

        $routing->update($updateData);

        RoutingApproval::create([
            'tenant_id'  => $routing->tenant_id,
            'routing_id' => $routing->id,
            'user_id'    => $approverUserId,
            'action'     => RoutingApproval::ACTION_APPROVED,
            'comments'   => $isFutureStart
                ? "Routing approved and scheduled to take effect on {$startDate}."
                : 'Routing approved and set as active manufacturing process.',
        ]);
    }

    /**
     * Transition a routing on cancellation.
     *
     * Rules:
     * - Status is set to 'cancelled'.
     * - effective_to date is set to today (the cancellation date).
     */
    public function transitionOnCancellation(Routing $routing, int $userId, ?string $comments = null): void
    {
        $today = Carbon::now()->toDateString();

        $routing->update([
            'status'       => Routing::STATUS_CANCELLED,
            'effective_to' => $today,
        ]);

        RoutingApproval::create([
            'tenant_id'  => $routing->tenant_id,
            'routing_id' => $routing->id,
            'user_id'    => $userId,
            'action'     => RoutingApproval::ACTION_CANCELLED,
            'comments'   => $comments ?: 'Routing cancelled.',
        ]);
    }

    /**
     * Synchronize unsubmitted draft routings to point to the newly approved BOM.
     * Only routings in 'draft' status (not submitted for approval) get updated.
     * Submitted routings ('pending_approval') and active/historical routings are NOT modified.
     */
    public function syncDraftRoutingsToApprovedBom(ProductionBom $bom): void
    {
        $draftRoutings = Routing::withoutGlobalScopes()
            ->where('tenant_id', $bom->tenant_id)
            ->where('product_id', $bom->product_id)
            ->where('status', Routing::STATUS_DRAFT)
            ->get();

        foreach ($draftRoutings as $draftRouting) {
            $draftRouting->update([
                'bom_id' => $bom->id,
            ]);
        }
    }

    /**
     * Archive active routings when their connected BOM is deactivated or superseded.
     */
    public function archiveRoutingsForDeactivatedBoms(
        array|int $deactivatedBomIds,
        int $productId,
        int $userId,
        string $comments,
        ?string $effectiveTo = null
    ): void {
        $bomIds = (array) $deactivatedBomIds;
        $endDate = $effectiveTo ?: Carbon::now()->toDateString();

        $linkedRoutings = Routing::withoutGlobalScopes()
            ->where(function ($q) use ($bomIds, $productId) {
                $q->whereIn('bom_id', $bomIds)
                  ->orWhere(function ($sub) use ($productId) {
                      $sub->whereNull('bom_id')
                          ->where('product_id', $productId);
                  });
            })
            ->where('status', Routing::STATUS_ACTIVE)
            ->get();

        foreach ($linkedRoutings as $linkedRouting) {
            $linkedRouting->update([
                'status'       => Routing::STATUS_HISTORICAL,
                'effective_to' => $endDate,
            ]);

            RoutingApproval::create([
                'tenant_id'  => $linkedRouting->tenant_id,
                'routing_id' => $linkedRouting->id,
                'user_id'    => $userId,
                'action'     => RoutingApproval::ACTION_ARCHIVED,
                'comments'   => $comments,
            ]);
        }
    }

    /**
     * Handle connected routings when a BOM gets cancelled.
     *
     * Rules:
     * - Any connected routing that is scheduled ('scheduled') must be cancelled ('cancelled')
     *   along with the cancellation reason provided for the BOM.
     * - Any connected routing that is active ('active') becomes historical ('historical')
     *   with effective_to set to today (current flow).
     */
    public function handleRoutingsOnBomCancellation(
        ProductionBom $bom,
        int $userId,
        ?string $cancelReason = null
    ): void {
        $today = Carbon::now()->toDateString();
        $reasonText = $cancelReason ? " Reason: {$cancelReason}" : '';

        // 1. Scheduled routings -> cancelled with BOM cancellation reason
        $scheduledRoutings = Routing::withoutGlobalScopes()
            ->where('tenant_id', $bom->tenant_id)
            ->where(function ($q) use ($bom) {
                $q->where('bom_id', $bom->id)
                  ->orWhere(function ($sub) use ($bom) {
                      $sub->whereNull('bom_id')
                          ->where('product_id', $bom->product_id);
                  });
            })
            ->where('status', Routing::STATUS_SCHEDULED)
            ->get();

        foreach ($scheduledRoutings as $routing) {
            $routing->update([
                'status'       => Routing::STATUS_CANCELLED,
                'effective_to' => $today,
            ]);

            RoutingApproval::create([
                'tenant_id'  => $routing->tenant_id,
                'routing_id' => $routing->id,
                'user_id'    => $userId,
                'action'     => RoutingApproval::ACTION_CANCELLED,
                'comments'   => "Automatically cancelled because connected BOM ({$bom->bom_number}) was cancelled.{$reasonText}",
            ]);
        }

        // 2. Active routings -> historical (existing flow)
        $activeRoutings = Routing::withoutGlobalScopes()
            ->where('tenant_id', $bom->tenant_id)
            ->where(function ($q) use ($bom) {
                $q->where('bom_id', $bom->id)
                  ->orWhere(function ($sub) use ($bom) {
                      $sub->whereNull('bom_id')
                          ->where('product_id', $bom->product_id);
                  });
            })
            ->where('status', Routing::STATUS_ACTIVE)
            ->get();

        foreach ($activeRoutings as $routing) {
            $routing->update([
                'status'       => Routing::STATUS_HISTORICAL,
                'effective_to' => $today,
            ]);

            RoutingApproval::create([
                'tenant_id'  => $routing->tenant_id,
                'routing_id' => $routing->id,
                'user_id'    => $userId,
                'action'     => RoutingApproval::ACTION_ARCHIVED,
                'comments'   => "Automatically archived because connected BOM ({$bom->bom_number}) was cancelled.{$reasonText}",
            ]);
        }
    }

    /**
     * Transition routings on Engineering Change Order (ECO) release.
     */
    public function transitionOnEcoRelease(
        Routing $proposedRouting,
        ?Routing $currentRouting,
        string $effectiveDate,
        int $userId,
        ?int $revision = null
    ): void {
        if ($currentRouting) {
            $currentRouting->update([
                'status'       => Routing::STATUS_HISTORICAL,
                'effective_to' => $effectiveDate,
            ]);

            RoutingApproval::create([
                'tenant_id'  => $currentRouting->tenant_id,
                'routing_id' => $currentRouting->id,
                'user_id'    => $userId,
                'action'     => RoutingApproval::ACTION_ARCHIVED,
                'comments'   => "Archived and expired on {$effectiveDate} via ECO release.",
            ]);
        }

        $updateData = [
            'status'         => Routing::STATUS_ACTIVE,
            'effective_from' => $effectiveDate,
            'effective_to'   => null,
        ];
        if ($revision !== null) {
            $updateData['revision'] = $revision;
        }

        $proposedRouting->update($updateData);
    }
}
