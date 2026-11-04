<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\StockAdjustment;
use App\Domains\Inventory\Models\StockTransfer;
use App\Domains\Platform\Services\NotificationRuleService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;

class InventoryNotificationService
{
    /**
     * 1. Low Stock Alert (item available stock drops below reorder point).
     */
    public static function notifyStockLow(
        Product $product,
        float $currentStock,
        float $reorderPoint,
        ?string $warehouseName = null
    ): void {
        try {
            $uomName = $product->uom?->name ?? 'Units';
            $sku = $product->sku ?? $product->code ?? 'N/A';
            $warehouse = $warehouseName ?: 'All Warehouses';
            $actionUrl = route('inventory.products.index');

            NotificationRuleService::trigger(
                eventKey: 'inventory.stock.low',
                data: [
                    'item_name'     => $product->name,
                    'sku'           => $sku,
                    'current_stock' => (string) $currentStock,
                    'reorder_point' => (string) $reorderPoint,
                    'uom'           => $uomName,
                    'warehouse'     => $warehouse,
                ],
                actionUrl: $actionUrl,
                subjectModel: $product
            );
        } catch (\Throwable $e) {
            Log::warning("Inventory Notification failed (inventory.stock.low): " . $e->getMessage());
        }
    }

    /**
     * 2. Out of Stock Alert (item physical inventory drops to 0).
     */
    public static function notifyOutOfStock(
        Product $product,
        ?string $warehouseName = null
    ): void {
        try {
            $uomName = $product->uom?->name ?? 'Units';
            $sku = $product->sku ?? $product->code ?? 'N/A';
            $warehouse = $warehouseName ?: 'All Warehouses';
            $actionUrl = route('inventory.products.index');

            NotificationRuleService::trigger(
                eventKey: 'inventory.stock.out_of_stock',
                data: [
                    'item_name' => $product->name,
                    'sku'       => $sku,
                    'warehouse' => $warehouse,
                    'uom'       => $uomName,
                ],
                actionUrl: $actionUrl,
                subjectModel: $product
            );
        } catch (\Throwable $e) {
            Log::warning("Inventory Notification failed (inventory.stock.out_of_stock): " . $e->getMessage());
        }
    }

    /**
     * 3. Stock Transfer Created / Requested.
     */
    public static function notifyTransferCreated(StockTransfer $transfer): void
    {
        try {
            $transfer->loadMissing(['fromWarehouse', 'toWarehouse']);
            $fromName = $transfer->fromWarehouse?->name ?? 'Origin Warehouse';
            $toName = $transfer->toWarehouse?->name ?? 'Destination Warehouse';
            $createdBy = auth()->user()?->name ?? 'Storekeeper';
            $itemsCount = (string) $transfer->items()->count();
            $actionUrl = route('inventory.transfers.show', $transfer->id);

            NotificationRuleService::trigger(
                eventKey: 'inventory.transfer.created',
                data: [
                    'transfer_no'        => $transfer->transfer_number,
                    'from_warehouse'     => $fromName,
                    'to_warehouse'       => $toName,
                    'items_count'        => $itemsCount,
                    'created_by'         => $createdBy,
                    'created_by_user_id' => $transfer->created_by,
                    'assigned_user_id'   => $transfer->created_by,
                ],
                actionUrl: $actionUrl,
                subjectModel: $transfer
            );
        } catch (\Throwable $e) {
            Log::warning("Inventory Notification failed (inventory.transfer.created): " . $e->getMessage());
        }
    }

    /**
     * 4. Stock Transfer Dispatched (In-Transit).
     */
    public static function notifyTransferDispatched(StockTransfer $transfer): void
    {
        try {
            $transfer->loadMissing(['fromWarehouse', 'toWarehouse']);
            $fromName = $transfer->fromWarehouse?->name ?? 'Origin Warehouse';
            $toName = $transfer->toWarehouse?->name ?? 'Destination Warehouse';
            $dispatchedBy = auth()->user()?->name ?? 'Warehouse Supervisor';
            $itemsCount = (string) $transfer->items()->count();
            $actionUrl = route('inventory.transfers.show', $transfer->id);

            // Notify transfer requester if dispatched by another user
            if (!empty($transfer->created_by) && $transfer->created_by !== auth()->id()) {
                NotificationService::send(
                    user: $transfer->created_by,
                    title: "Transfer Dispatched: {$transfer->transfer_number}",
                    message: "Stock Transfer {$transfer->transfer_number} from {$fromName} to {$toName} has been dispatched by {$dispatchedBy}.",
                    actionUrl: $actionUrl,
                    module: 'inventory',
                    type: 'inventory_transfer_dispatched',
                    iconClass: 'feather-truck'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'inventory.transfer.dispatched',
                data: [
                    'transfer_no'        => $transfer->transfer_number,
                    'from_warehouse'     => $fromName,
                    'to_warehouse'       => $toName,
                    'items_count'        => $itemsCount,
                    'dispatched_by'      => $dispatchedBy,
                    'created_by_user_id' => $transfer->created_by,
                    'assigned_user_id'   => $transfer->created_by,
                    'exclude_user_ids'   => (!empty($transfer->created_by) && $transfer->created_by !== auth()->id()) ? [$transfer->created_by] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $transfer
            );
        } catch (\Throwable $e) {
            Log::warning("Inventory Notification failed (inventory.transfer.dispatched): " . $e->getMessage());
        }
    }

    /**
     * 5. Stock Transfer Received & Verified.
     */
    public static function notifyTransferReceived(StockTransfer $transfer): void
    {
        try {
            $transfer->loadMissing(['fromWarehouse', 'toWarehouse']);
            $fromName = $transfer->fromWarehouse?->name ?? 'Origin Warehouse';
            $toName = $transfer->toWarehouse?->name ?? 'Destination Warehouse';
            $receivedBy = auth()->user()?->name ?? 'Warehouse Staff';
            $actionUrl = route('inventory.transfers.show', $transfer->id);

            // Direct in-app alert to transfer requester
            if (!empty($transfer->created_by)) {
                NotificationService::send(
                    user: $transfer->created_by,
                    title: "Transfer Received: {$transfer->transfer_number}",
                    message: "Stock Transfer {$transfer->transfer_number} from {$fromName} has been fully received at {$toName} by {$receivedBy}.",
                    actionUrl: $actionUrl,
                    module: 'inventory',
                    type: 'inventory_transfer_received',
                    iconClass: 'feather-check-square'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'inventory.transfer.received',
                data: [
                    'transfer_no'        => $transfer->transfer_number,
                    'from_warehouse'     => $fromName,
                    'to_warehouse'       => $toName,
                    'received_by'        => $receivedBy,
                    'created_by_user_id' => $transfer->created_by,
                    'assigned_user_id'   => $transfer->created_by,
                    'exclude_user_ids'   => !empty($transfer->created_by) ? [$transfer->created_by] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $transfer
            );
        } catch (\Throwable $e) {
            Log::warning("Inventory Notification failed (inventory.transfer.received): " . $e->getMessage());
        }
    }

    /**
     * 6. Stock Transfer Cancelled.
     */
    public static function notifyTransferCancelled(StockTransfer $transfer, ?string $reason = null): void
    {
        try {
            $transfer->loadMissing(['fromWarehouse', 'toWarehouse']);
            $fromName = $transfer->fromWarehouse?->name ?? 'Origin Warehouse';
            $toName = $transfer->toWarehouse?->name ?? 'Destination Warehouse';
            $cancelledBy = auth()->user()?->name ?? 'Supervisor';
            $actionUrl = route('inventory.transfers.show', $transfer->id);

            // Direct alert to creator if cancelled by someone else
            if (!empty($transfer->created_by) && $transfer->created_by !== auth()->id()) {
                NotificationService::send(
                    user: $transfer->created_by,
                    title: "Transfer Cancelled: {$transfer->transfer_number}",
                    message: "Stock Transfer {$transfer->transfer_number} ({$fromName} -> {$toName}) was cancelled by {$cancelledBy}." . ($reason ? " Reason: {$reason}" : ''),
                    actionUrl: $actionUrl,
                    module: 'inventory',
                    type: 'inventory_transfer_cancelled',
                    iconClass: 'feather-x-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'inventory.transfer.cancelled',
                data: [
                    'transfer_no'        => $transfer->transfer_number,
                    'from_warehouse'     => $fromName,
                    'to_warehouse'       => $toName,
                    'cancelled_by'       => $cancelledBy,
                    'reason'             => $reason ?? 'Cancelled by user',
                    'created_by_user_id' => $transfer->created_by,
                    'assigned_user_id'   => $transfer->created_by,
                    'exclude_user_ids'   => (!empty($transfer->created_by) && $transfer->created_by !== auth()->id()) ? [$transfer->created_by] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $transfer
            );
        } catch (\Throwable $e) {
            Log::warning("Inventory Notification failed (inventory.transfer.cancelled): " . $e->getMessage());
        }
    }

    /**
     * 7. Stock Adjustment Pending Approval.
     */
    public static function notifyAdjustmentPending(StockAdjustment $adjustment): void
    {
        try {
            $adjustment->loadMissing(['warehouse']);
            $whName = $adjustment->warehouse?->name ?? 'Warehouse';
            $createdBy = auth()->user()?->name ?? 'Storekeeper';
            $actionUrl = route('inventory.adjustments.show', $adjustment->id);

            NotificationRuleService::trigger(
                eventKey: 'inventory.adjustment.pending',
                data: [
                    'doc_no'             => $adjustment->adjustment_number,
                    'warehouse'          => $whName,
                    'reason'             => $adjustment->reason ?? 'Stock Count Variance',
                    'created_by'         => $createdBy,
                    'created_by_user_id' => $adjustment->created_by,
                    'assigned_user_id'   => $adjustment->created_by,
                ],
                actionUrl: $actionUrl,
                subjectModel: $adjustment
            );
        } catch (\Throwable $e) {
            Log::warning("Inventory Notification failed (inventory.adjustment.pending): " . $e->getMessage());
        }
    }

    /**
     * 8. Stock Adjustment Approved.
     */
    public static function notifyAdjustmentApproved(StockAdjustment $adjustment): void
    {
        try {
            $adjustment->loadMissing(['warehouse']);
            $whName = $adjustment->warehouse?->name ?? 'Warehouse';
            $approvedBy = auth()->user()?->name ?? 'Manager';
            $actionUrl = route('inventory.adjustments.show', $adjustment->id);

            // Direct in-app + Pusher alert to person who submitted the adjustment
            if (!empty($adjustment->created_by)) {
                NotificationService::send(
                    user: $adjustment->created_by,
                    title: "Adjustment Approved: {$adjustment->adjustment_number}",
                    message: "Stock Adjustment {$adjustment->adjustment_number} ({$whName}) has been approved by {$approvedBy}.",
                    actionUrl: $actionUrl,
                    module: 'inventory',
                    type: 'inventory_adjustment_approved',
                    iconClass: 'feather-check-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'inventory.adjustment.approved',
                data: [
                    'doc_no'             => $adjustment->adjustment_number,
                    'warehouse'          => $whName,
                    'reason'             => $adjustment->reason ?? 'Stock Count Variance',
                    'approved_by'        => $approvedBy,
                    'created_by_user_id' => $adjustment->created_by,
                    'assigned_user_id'   => $adjustment->created_by,
                    'exclude_user_ids'   => !empty($adjustment->created_by) ? [$adjustment->created_by] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $adjustment
            );
        } catch (\Throwable $e) {
            Log::warning("Inventory Notification failed (inventory.adjustment.approved): " . $e->getMessage());
        }
    }

    /**
     * 9. Stock Adjustment Cancelled.
     */
    public static function notifyAdjustmentCancelled(StockAdjustment $adjustment, ?string $reason = null): void
    {
        try {
            $adjustment->loadMissing(['warehouse']);
            $whName = $adjustment->warehouse?->name ?? 'Warehouse';
            $cancelledBy = auth()->user()?->name ?? 'Manager';
            $actionUrl = route('inventory.adjustments.show', $adjustment->id);

            // Direct in-app alert to person who submitted the adjustment
            if (!empty($adjustment->created_by) && $adjustment->created_by !== auth()->id()) {
                NotificationService::send(
                    user: $adjustment->created_by,
                    title: "Adjustment Cancelled: {$adjustment->adjustment_number}",
                    message: "Stock Adjustment {$adjustment->adjustment_number} ({$whName}) was cancelled by {$cancelledBy}." . ($reason ? " Reason: {$reason}" : ''),
                    actionUrl: $actionUrl,
                    module: 'inventory',
                    type: 'inventory_adjustment_cancelled',
                    iconClass: 'feather-slash'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'inventory.adjustment.cancelled',
                data: [
                    'doc_no'             => $adjustment->adjustment_number,
                    'warehouse'          => $whName,
                    'reason'             => $reason ?? ($adjustment->reason ?? 'Adjustment Cancelled'),
                    'cancelled_by'       => $cancelledBy,
                    'created_by_user_id' => $adjustment->created_by,
                    'assigned_user_id'   => $adjustment->created_by,
                    'exclude_user_ids'   => (!empty($adjustment->created_by) && $adjustment->created_by !== auth()->id()) ? [$adjustment->created_by] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $adjustment
            );
        } catch (\Throwable $e) {
            Log::warning("Inventory Notification failed (inventory.adjustment.cancelled): " . $e->getMessage());
        }
    }

    /**
     * 10. Batch Expiring Soon (FEFO).
     */
    public static function notifyBatchExpiring(Batch $batch, int $daysLeft): void
    {
        try {
            $batch->loadMissing(['product', 'warehouse']);
            $itemName = $batch->product?->name ?? 'Item';
            $batchNo = $batch->batch_number ?? 'N/A';
            $expiryDate = $batch->expiry_date ? $batch->expiry_date->format('d M Y') : 'Unknown';
            $whName = $batch->warehouse?->name ?? 'Main Warehouse';
            $actionUrl = route('inventory.batches.index');

            NotificationRuleService::trigger(
                eventKey: 'inventory.batch.expiring',
                data: [
                    'item_name'   => $itemName,
                    'batch_no'    => $batchNo,
                    'expiry_date' => $expiryDate,
                    'days_left'   => (string) $daysLeft,
                    'quantity'    => (string) $batch->available_qty,
                    'warehouse'   => $whName,
                ],
                actionUrl: $actionUrl,
                subjectModel: $batch
            );
        } catch (\Throwable $e) {
            Log::warning("Inventory Notification failed (inventory.batch.expiring): " . $e->getMessage());
        }
    }
}

