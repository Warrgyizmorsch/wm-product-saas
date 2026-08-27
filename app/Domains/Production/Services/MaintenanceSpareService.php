<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrder;
use App\Domains\Production\Models\ProductionMaintenanceWorkOrderSpare;
use App\Domains\Production\Repositories\MaintenanceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaintenanceSpareService
{
    public function __construct(
        private readonly MaintenanceRepositoryInterface $repository,
        private readonly ProductionEventService $eventService
    ) {}

    /**
     * Request a spare part on a Maintenance Work Order.
     */
    public function addSpareRequest(
        int $workOrderId,
        int $tenantId,
        int $productId,
        int $warehouseId,
        float $requestedQty
    ): ProductionMaintenanceWorkOrderSpare {
        if ($requestedQty <= 0) {
            throw new InvalidArgumentException("Requested quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($workOrderId, $tenantId, $productId, $warehouseId, $requestedQty) {
            $wo = $this->repository->findWorkOrderForLock($workOrderId, $tenantId);
            if (!$wo) {
                throw new InvalidArgumentException("Work Order #{$workOrderId} not found.");
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_COMPLETED || $wo->status === ProductionMaintenanceWorkOrder::STATUS_CANCELLED) {
                throw new InvalidArgumentException("Cannot add spare parts to a completed or cancelled Work Order.");
            }

            // Check if available stock exists in target warehouse
            $availableStock = StockService::getAvailableStock($productId, $warehouseId);
            if ($availableStock < $requestedQty) {
                throw new InvalidArgumentException("Insufficient stock in warehouse for product #{$productId}. Available: {$availableStock}, Requested: {$requestedQty}.");
            }

            return $this->repository->addWorkOrderSpare([
                'tenant_id'                  => $tenantId,
                'maintenance_work_order_id'  => $workOrderId,
                'product_id'                 => $productId,
                'warehouse_id'               => $warehouseId,
                'requested_qty'              => $requestedQty,
                'issued_qty'                 => 0.0000,
                'unit_cost'                  => 0.00,
                'total_cost'                 => 0.00,
            ]);
        });
    }

    /**
     * Issue a requested spare part for a Maintenance Work Order using StockService::recordOutflow().
     *
     * Validates stock availability, prevents negative stock, prevents duplicate issue,
     * calls StockService::recordOutflow(), records transaction, and updates WO spare costs.
     */
    public function issueSparePart(
        int $spareId,
        int $tenantId,
        float $issueQty,
        ?int $userId = null
    ): ProductionMaintenanceWorkOrderSpare {
        if ($issueQty <= 0) {
            throw new InvalidArgumentException("Issue quantity must be greater than zero.");
        }

        return DB::transaction(function () use ($spareId, $tenantId, $issueQty, $userId) {
            $spare = ProductionMaintenanceWorkOrderSpare::where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->findOrFail($spareId);

            $wo = $this->repository->findWorkOrderForLock($spare->maintenance_work_order_id, $tenantId);
            if (!$wo) {
                throw new InvalidArgumentException("Associated Work Order not found.");
            }

            if ($wo->status === ProductionMaintenanceWorkOrder::STATUS_COMPLETED || $wo->status === ProductionMaintenanceWorkOrder::STATUS_CANCELLED) {
                throw new InvalidArgumentException("Cannot issue spares for a completed or cancelled Work Order.");
            }

            // Prevent duplicate issue / over-issue
            $remainingToIssue = max(0.0, (float) $spare->requested_qty - (float) $spare->issued_qty);
            if ($remainingToIssue <= 0) {
                throw new InvalidArgumentException("This spare part request has already been fully issued.");
            }

            $actualIssueQty = min($issueQty, $remainingToIssue);

            // Verify physical stock availability via StockService
            $availableStock = StockService::getAvailableStock($spare->product_id, $spare->warehouse_id);
            if ($availableStock < $actualIssueQty) {
                throw new InvalidArgumentException("Cannot issue spare part: Insufficient stock available. Required: {$actualIssueQty}, Available: {$availableStock}.");
            }

            // Record outflow strictly via StockService
            $stockTxn = StockService::recordOutflow(
                $tenantId,
                $spare->product_id,
                $spare->warehouse_id,
                $actualIssueQty,
                'MaintenanceWorkOrder',
                $wo->id
            );

            $unitCost  = (float) $stockTxn->unit_cost;
            $totalCost = (float) ($stockTxn->total_cost ?? $stockTxn->total_value);

            $newIssuedQty = (float) $spare->issued_qty + $actualIssueQty;
            $newTotalCost = (float) $spare->total_cost + $totalCost;
            $newUnitCost  = $newIssuedQty > 0 ? round($newTotalCost / $newIssuedQty, 2) : $unitCost;

            $spare->update([
                'issued_qty'           => $newIssuedQty,
                'unit_cost'            => $newUnitCost,
                'total_cost'           => $newTotalCost,
                'stock_transaction_id' => $stockTxn->id,
            ]);

            // Rollup spare parts total on Maintenance Work Order
            $sumSparesCost = (float) ProductionMaintenanceWorkOrderSpare::where('tenant_id', $tenantId)
                ->where('maintenance_work_order_id', $wo->id)
                ->sum('total_cost');

            $wo->update([
                'spare_parts_cost' => $sumSparesCost,
                'total_cost'       => round((float) $wo->labor_cost + $sumSparesCost, 2),
            ]);

            $this->eventService->writeEvent($tenantId, [
                'machine_id'   => $wo->machine_id,
                'event_type'   => 'Spare Part Issued',
                'title'        => 'Maintenance Spare Issued',
                'description'  => "Issued {$actualIssueQty} of product #{$spare->product_id} for Work Order [{$wo->work_order_number}]. Cost: \${$totalCost}.",
                'severity'     => 'info',
                'event_source' => 'MaintenanceSpareService',
            ]);

            return $spare->fresh(['product', 'warehouse']);
        });
    }
}
