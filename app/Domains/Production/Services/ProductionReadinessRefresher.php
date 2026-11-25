<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use Illuminate\Support\Facades\Log;

/**
 * PX-10 (docs/production_execution_rules_draft.md): operation readiness is recomputed on EVENTS and by a scheduled sweep —
 * never because someone opened the order page. Every entry point is failure-safe: a readiness refresh must never break
 * the action (issue, machine save, downtime) that triggered it.
 */
class ProductionReadinessRefresher
{
    public function forOrder(int $orderId): void
    {
        try {
            app(ProductionOrderService::class)->reconcileOperationReadiness($orderId);
        } catch (\Throwable $e) {
            Log::warning('Readiness refresh failed for order ' . $orderId . ': ' . $e->getMessage());
        }
    }

    /** All active (released / in progress) orders that have an unfinished operation on this machine. */
    public function forMachine(int $tenantId, int $machineId): void
    {
        try {
            $orderIds = ProductionOrderOperation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('machine_id', $machineId)
                ->whereNotIn('status', [
                    ProductionOrderOperation::STATUS_COMPLETED,
                    ProductionOrderOperation::STATUS_SKIPPED,
                    ProductionOrderOperation::STATUS_CANCELLED,
                ])
                ->pluck('production_order_id')
                ->unique();

            foreach ($orderIds as $orderId) {
                $this->forOrder((int) $orderId);
            }
        } catch (\Throwable $e) {
            Log::warning('Readiness refresh failed for machine ' . $machineId . ': ' . $e->getMessage());
        }
    }

    /** Scheduled sweep: every released / in-progress order. Returns the number of orders refreshed. */
    public function sweep(?int $tenantId = null): int
    {
        $count = 0;
        ProductionOrder::withoutGlobalScopes()
            ->whereIn('status', [ProductionOrder::STATUS_RELEASED, ProductionOrder::STATUS_IN_PROGRESS])
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    $this->forOrder((int) $order->id);
                    $count++;
                }
            });

        return $count;
    }
}
