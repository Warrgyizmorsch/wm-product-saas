<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderOperationQualityPlan;
use App\Domains\Production\Models\ProductionQualityPlan;
use App\Domains\Production\Models\RoutingOperationQualityPlan;
use Illuminate\Support\Collection;

/**
 * Order-level Quality Plan snapshot (docs/production_quality_rules.md QR-14 / QR-17):
 *  - routing copy: when an order operation is built from a routing operation whose QC gate is on, its linked plans are
 *    copied to production_order_operation_quality_plans (source "routing").
 *    QM-13 (2026-11-26): if a linked plan version is no longer approved (newer version / historical), creating the
 *    order is BLOCKED for now — the production-wide lifecycle rule (use old version vs block) is decided later.
 *    Orders that already exist (copied on first QC use) keep the linked version as it is.
 *  - looped semi: the LAST maker operation of a sub-assembly set made inside the order gets the semi's approved
 *    product plan (source "sub_assembly") and its QC gate switched on — for this order only, never in the routing.
 */
class OrderOperationQualityService
{
    /**
     * Copy the routing operation's plans onto an order operation.
     * $blockOutdated = true while building a NEW order: an outdated linked plan stops the order with a clear message.
     */
    public function copyRoutingPlans(ProductionOrderOperation $op, bool $blockOutdated = true): void
    {
        if (!$op->routing_operation_id) {
            return;
        }
        // the gate is read the same way as everywhere else (order operation flag OR its routing operation flag)
        if (!$op->quality_required && !($op->routingOperation?->quality_required ?? false)) {
            return;
        }

        $links = RoutingOperationQualityPlan::where('routing_operation_id', $op->routing_operation_id)
            ->with(['plan', 'routingOperation.routing'])
            ->orderBy('sort_order')
            ->get();

        foreach ($links as $i => $link) {
            $plan = $link->plan;
            if (!$plan) {
                continue;
            }
            if ($blockOutdated && (!$plan->isApproved() || $plan->trashed())) {
                $this->throwOutdated($link, $plan);
            }
            ProductionOrderOperationQualityPlan::firstOrCreate(
                ['production_order_operation_id' => $op->id, 'quality_plan_id' => $plan->id],
                [
                    'tenant_id'    => $op->tenant_id,
                    'is_mandatory' => (bool) $link->is_mandatory,
                    'source'       => ProductionOrderOperationQualityPlan::SOURCE_ROUTING,
                    'sort_order'   => $i + 1,
                ]
            );
        }
    }

    /**
     * QR-17 / A4: the final maker operation of a sub-assembly set gets every approved product plan of the semi it makes
     * (mandatory) and its QC gate on. Returns true when QC was added.
     */
    public function applySubAssemblyQc(ProductionOrderOperation $finalMaker): bool
    {
        $productId = $finalMaker->source_product_id;
        if (!$productId) {
            return false;
        }

        $plans = $this->approvedProductPlans($finalMaker->tenant_id, (int) $productId);
        if ($plans->isEmpty()) {
            return false;
        }

        $offset = (int) ProductionOrderOperationQualityPlan::where('production_order_operation_id', $finalMaker->id)->max('sort_order');
        foreach ($plans->values() as $i => $plan) {
            ProductionOrderOperationQualityPlan::firstOrCreate(
                ['production_order_operation_id' => $finalMaker->id, 'quality_plan_id' => $plan->id],
                [
                    'tenant_id'    => $finalMaker->tenant_id,
                    'is_mandatory' => true,
                    'source'       => ProductionOrderOperationQualityPlan::SOURCE_SUB_ASSEMBLY,
                    'sort_order'   => $offset + $i + 1,
                ]
            );
        }

        if (!$finalMaker->quality_required) {
            $finalMaker->quality_required = true;
            $finalMaker->save();
        }

        return true;
    }

    /** True when the operation's QC exists only because it makes a looped semi (QR-05 warning text). */
    public function isSubAssemblyQc(ProductionOrderOperation $op): bool
    {
        return ProductionOrderOperationQualityPlan::where('production_order_operation_id', $op->id)
            ->where('source', ProductionOrderOperationQualityPlan::SOURCE_SUB_ASSEMBLY)
            ->exists();
    }

    /**
     * The plans an order operation must run (snapshot), with their plan + parameters loaded.
     *
     * @return Collection<int, ProductionOrderOperationQualityPlan>
     */
    public function plansFor(ProductionOrderOperation $op): Collection
    {
        return ProductionOrderOperationQualityPlan::where('production_order_operation_id', $op->id)
            ->with('plan.parameters')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($link) => $link->plan !== null)
            ->values();
    }

    /**
     * The checklist an operation's QC must cover: its snapshot rows; orders built before the snapshot existed get their
     * routing operation's plans copied on first use.
     *
     * @return Collection<int, ProductionOrderOperationQualityPlan>
     */
    public function checklistFor(ProductionOrderOperation $op): Collection
    {
        $links = $this->plansFor($op);
        if ($links->isEmpty()) {
            $this->copyRoutingPlans($op, false); // existing order: keep the linked version as it is (QM-13)
            $links = $this->plansFor($op);
        }

        return $links;
    }

    /** A4: every approved product-scope plan of a product. */
    public function approvedProductPlans(int $tenantId, int $productId): Collection
    {
        return ProductionQualityPlan::where('tenant_id', $tenantId)
            ->approved()
            ->where('type', ProductionQualityPlan::SCOPE_PRODUCT)
            ->where('product_id', $productId)
            ->with('parameters')
            ->orderBy('name')
            ->get();
    }

    /** The approved version of a plan's family (the plan itself when it is approved). */
    /** QM-13: a new order can't be built while the routing points at a QC plan version that is no longer approved. */
    private function throwOutdated(RoutingOperationQualityPlan $link, ProductionQualityPlan $plan): never
    {
        $op = $link->routingOperation;
        $routing = $op?->routing;
        $where = trim(($routing ? 'Routing ' . ($routing->routing_number ?: $routing->name) : 'The routing')
            . ($op ? ' · Op ' . ($op->operation_number ?: $op->sequence) . ' ' . $op->name : ''));

        $current = $plan->latestApprovedVersion();
        $state = $current && $current->id !== $plan->id
            ? "is outdated — version {$current->version} is the approved one"
            : 'is ' . strtolower($plan->statusLabel()) . ' and has no approved version';

        throw new \InvalidArgumentException(
            "{$where} uses QC plan {$plan->name} v{$plan->version}, which {$state}. Update the routing's QC plans before creating this order."
        );
    }
}
