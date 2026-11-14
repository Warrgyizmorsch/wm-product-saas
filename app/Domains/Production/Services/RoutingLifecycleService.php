<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomApproval;
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
     * (RT-IMPL-03: the old product-based rule 3 was removed — routings have no product; $productId is unused.)
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
    }

    /**
     * RT-IMPL-14 (RT-19 rule 6): routing-level approval snapshot — approver, time, Σ saved costs and every
     * operation's saved figures with the section snapshot number they came from. Numbers only (base currency).
     */
    public function buildApprovalCostSnapshot(Routing $routing, int $approverUserId): array
    {
        $ops = $routing->operations()->get();
        $primary = $ops->where('is_alternative', false);

        return [
            'approved_by'        => $approverUserId,
            'approved_at'        => Carbon::now()->toIso8601String(),
            'cost_per_unit'      => round((float) $primary->sum(fn($o) => (float) ($o->cost_per_unit ?? 0)), 4),
            'labor_cost_per_unit'   => round((float) $primary->sum(fn($o) => (float) ($o->labor_cost_per_unit ?? 0)), 4),
            'machine_cost_per_unit' => round((float) $primary->sum(fn($o) => (float) ($o->machine_cost_per_unit ?? 0)), 4),
            'setup_cost_per_run' => round((float) $primary->sum(fn($o) => $o->setupCostPerRun()), 4),
            'operations'         => $ops->map(fn($o) => [
                'id'                      => $o->id,
                'operation_number'        => $o->operation_number,
                'is_alternative'          => (bool) $o->is_alternative,
                'cost_per_unit'           => $o->cost_per_unit,
                'labor_cost_per_unit'     => $o->labor_cost_per_unit,
                'machine_cost_per_unit'   => $o->machine_cost_per_unit,
                'setup_cost_per_run'      => $o->setup_cost_per_run,
                'labor_cost_rate'         => $o->labor_cost_rate,
                'machine_cost_rate'       => $o->machine_cost_rate,
                'section_calculation_id'  => $o->section_calculation_id,
                'section_snapshot_number' => $o->section_snapshot_number,
            ])->values()->all(),
        ];
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
            0,
            $routing->id,
            $routing->tenant_id
        );

        // [DECOUPLE-MANUAL-LIFECYCLE] No product-linked auto-supersession.
        // Routings are standalone reusable process templates.
        // Approving a routing does NOT retire older versions automatically.
        // Planners manually inactivate or mark older routings as historical when replaced.

        // 2. Activate or schedule the new version
        $newStatus = $isFutureStart ? Routing::STATUS_SCHEDULED : Routing::STATUS_ACTIVE;

        $updateData = [
            'status'         => $newStatus,
            'effective_from' => $startDate,
            'approved_by'    => $approverUserId,
            'approved_at'    => Carbon::now(),
            // RT-IMPL-14 (RT-19 rule 6): costs as they stood at approval
            'cost_snapshot'  => $this->buildApprovalCostSnapshot($routing, $approverUserId),
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

        // RT-IMPL-06: one BOM handler for retire / expire / cancel
        $this->handleBomsOnRoutingEnded($routing, 'cancelled', $userId, $comments);
    }

    /**
     * RT-IMPL-06 (RT-05): one BOM handler for a routing that ended — retired, expired (end date passed) or cancelled.
     *   approved / scheduled BOM → inactive  + audit "Inactivated"
     *   pending approval BOM     → draft     + audit "Returned to Draft"
     *   draft BOM                → nothing stored (live routing advisory, RT-IMPL-05)
     * The routing reason goes into the BOM audit entry — never into the BOM warning field (component warnings only).
     */
    public function handleBomsOnRoutingEnded(Routing $routing, string $event, ?int $userId, ?string $comments = null): void
    {
        $reasonText = $comments ? " Reason: {$comments}" : '';
        $label = "linked routing ({$routing->routing_number} v{$routing->version}) was {$event}";

        $boms = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $routing->tenant_id)
            ->where('routing_id', $routing->id)
            ->whereIn('status', [ProductionBom::STATUS_APPROVED, 'active', 'scheduled', ProductionBom::STATUS_PENDING_APPROVAL])
            ->get();

        foreach ($boms as $bom) {
            if ($bom->status === ProductionBom::STATUS_PENDING_APPROVAL) {
                $bom->update(['status' => ProductionBom::STATUS_DRAFT]);
                $action = 'Returned to Draft';
                $comment = "Automatically returned to draft because {$label}.{$reasonText}";
            } else {
                $bom->update(['status' => ProductionBom::STATUS_INACTIVE]);
                $action = 'Inactivated';
                $comment = "Automatically inactivated because {$label}.{$reasonText}";
            }

            ProductionBomApproval::create([
                'tenant_id' => $bom->tenant_id,
                'bom_id'    => $bom->id,
                'user_id'   => $userId,
                'action'    => $action,
                'comments'  => $comment,
            ]);
        }
    }

    /**
     * RT-IMPL-06 (RT-05 rule 1): what Retire / Cancel would do to each linked BOM — shown in the confirmation.
     *
     * @return array<int, array{id:int, bom_number:string, version:?string, status:string, effect:string}>
     */
    public function bomImpact(Routing $routing): array
    {
        return ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $routing->tenant_id)
            ->where('routing_id', $routing->id)
            ->whereNotIn('status', [ProductionBom::STATUS_CANCELLED, ProductionBom::STATUS_INACTIVE])
            ->orderBy('bom_number')
            ->get()
            ->map(fn($bom) => [
                'id'         => $bom->id,
                'bom_number' => $bom->bom_number,
                'version'    => $bom->version,
                'status'     => $bom->status,
                'effect'     => match (true) {
                    $bom->status === ProductionBom::STATUS_PENDING_APPROVAL => 'Goes back to draft',
                    $bom->status === ProductionBom::STATUS_DRAFT            => 'No change — shows the routing status advisory',
                    default                                                 => 'Becomes inactive',
                },
            ])
            ->all();
    }

    /**
     * RT-IMPL-04 (RT-03 rule 5): run the date rules for every tenant (no tenant scope — explicit tenant ids).
     * End dates are checked first, so a routing whose start and end both passed ends as historical.
     *
     * @return array{activated:int, expired:int}
     */
    public function processEffectiveDates(?string $today = null): array
    {
        $today = $today ?: Carbon::today()->toDateString();
        $expired = 0;
        $activated = 0;

        Routing::withoutGlobalScopes()
            ->whereIn('status', [Routing::STATUS_ACTIVE, Routing::STATUS_SCHEDULED])
            ->whereNotNull('effective_to')
            ->whereDate('effective_to', '<', $today)
            ->orderBy('id')
            ->each(function (Routing $routing) use (&$expired) {
                \Illuminate\Support\Facades\DB::transaction(fn() => $this->retireExpired($routing));
                $expired++;
            });

        Routing::withoutGlobalScopes()
            ->where('status', Routing::STATUS_SCHEDULED)
            ->whereDate('effective_from', '<=', $today)
            ->orderBy('id')
            ->each(function (Routing $routing) use (&$activated) {
                $this->activateScheduled($routing);
                $activated++;
            });

        return ['activated' => $activated, 'expired' => $expired];
    }

    /**
     * RT-IMPL-04 (RT-03 rule 5): a scheduled routing whose start date has arrived becomes active.
     */
    public function activateScheduled(Routing $routing, ?int $userId = null): void
    {
        $routing->update(['status' => Routing::STATUS_ACTIVE]);

        RoutingApproval::create([
            'tenant_id'  => $routing->tenant_id,
            'routing_id' => $routing->id,
            'user_id'    => $userId,
            'action'     => RoutingApproval::ACTION_ACTIVATED,
            'comments'   => 'Activated (scheduled start date ' . optional($routing->effective_from)->toDateString() . ' reached).',
        ]);
    }

    /**
     * RT-IMPL-04 (RT-03 rule 5): an active / scheduled routing whose end date has passed becomes historical,
     * with the same BOM handling as Retire (RT-05).
     */
    public function retireExpired(Routing $routing, ?int $userId = null): void
    {
        $end = optional($routing->effective_to)->toDateString();
        $routing->update(['status' => Routing::STATUS_HISTORICAL]);

        RoutingApproval::create([
            'tenant_id'  => $routing->tenant_id,
            'routing_id' => $routing->id,
            'user_id'    => $userId,
            'action'     => RoutingApproval::ACTION_RETIRED,
            'comments'   => "Retired (end date {$end} passed).",
        ]);

        $this->handleBomsOnRoutingEnded($routing, 'expired', $userId, "end date {$end} passed");
    }

    /**
     * [TRACK-06] Handle connected BOMs when a routing is rejected during review.
     * Pending approval BOMs return to draft with a warning.
     */
    public function handleBomsOnRoutingRejection(Routing $routing, int $userId, ?string $comments = null): void
    {
        $reasonText = $comments ? " Reason: {$comments}" : '';

        $pendingBoms = ProductionBom::withoutGlobalScopes()
            ->where('tenant_id', $routing->tenant_id)
            ->where('routing_id', $routing->id)
            ->where('status', ProductionBom::STATUS_PENDING_APPROVAL)
            ->get();

        foreach ($pendingBoms as $bom) {
            // RT-IMPL-05: routing reasons go to the audit entry, not the BOM warning field (component warnings only)
            $bom->update(['status' => ProductionBom::STATUS_DRAFT]);

            ProductionBomApproval::create([
                'tenant_id' => $bom->tenant_id,
                'bom_id'    => $bom->id,
                'user_id'   => $userId,
                'action'    => 'Rejected',
                'comments'  => "Automatically returned to draft because linked routing ({$routing->routing_number}) was rejected.{$reasonText}",
            ]);
        }
    }

    // [INVERSION-DECOUPLE-TRACK-03] Old BOM-to-Routing cascade methods commented out
    /*
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

    public function handleRoutingsOnBomCancellation(
        ProductionBom $bom,
        int $userId,
        ?string $cancelReason = null
    ): void {
        $today = Carbon::now()->toDateString();
        $reasonText = $cancelReason ? " Reason: {$cancelReason}" : '';

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
    */

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
