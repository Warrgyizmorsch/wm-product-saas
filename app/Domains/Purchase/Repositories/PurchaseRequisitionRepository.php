<?php

namespace App\Domains\Purchase\Repositories;

use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseRequisitionRepository
{
    public function getPaginatedRequisitions(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $tenantId = require_tenant_id();

        $query = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with(['requester', 'sourceable', 'items.product','reminders.user']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source_type'])) {
            if ($filters['source_type'] === 'subcontract') {
                $query->whereIn('source_type', ['mo', 'ProductionOrder'])
                    ->where(function ($q) {
                        $q->where('notes', 'like', '%Subcontract%')
                          ->orWhereHas('items', function ($iq) {
                              $iq->whereHas('product', function ($pq) {
                                  $pq->where('product_type', 'service');
                              });
                          });
                    });
            } else {
                $query->where('source_type', $filters['source_type']);
            }
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where('requisition_number', 'like', $search);
        }

        if (!empty($filters['reminder_date_from'])) {
            $query->whereDate('last_reminded_at', '>=', $filters['reminder_date_from']);
        }

        if (!empty($filters['reminder_date_to'])) {
            $query->whereDate('last_reminded_at', '<=', $filters['reminder_date_to']);
        }

        if (isset($filters['has_reminders']) && $filters['has_reminders'] !== '') {
            if ($filters['has_reminders'] == '1') {
                $query->where('reminder_count', '>', 0);
            } elseif ($filters['has_reminders'] == '0') {
                $query->where('reminder_count', 0);
            }
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['id', 'requisition_number', 'requisition_date', 'status', 'last_reminded_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function getPendingApprovals(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $tenantId = require_tenant_id();

        $query = PurchaseRequisition::where('tenant_id', $tenantId)
            ->where('status', 'Draft')
            ->with(['requester', 'sourceable', 'reminders.user']);

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where('requisition_number', 'like', $search);
        }

        if (!empty($filters['reminder_date_from'])) {
            $query->whereDate('last_reminded_at', '>=', $filters['reminder_date_from']);
        }

        if (!empty($filters['reminder_date_to'])) {
            $query->whereDate('last_reminded_at', '<=', $filters['reminder_date_to']);
        }

        if (isset($filters['has_reminders']) && $filters['has_reminders'] !== '') {
            if ($filters['has_reminders'] == '1') {
                $query->where('reminder_count', '>', 0);
            } elseif ($filters['has_reminders'] == '0') {
                $query->where('reminder_count', 0);
            }
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['id', 'requisition_number', 'requisition_date'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?PurchaseRequisition
    {
        $tenantId = require_tenant_id();
        return PurchaseRequisition::where('tenant_id', $tenantId)->find($id);
    }

    public function findWithDetails(int $id): PurchaseRequisition
    {
        $tenantId = require_tenant_id();
        return PurchaseRequisition::where('tenant_id', $tenantId)
            ->with([
                'requester',
                'sourceable',
                'items.product',
                'items.warehouse'
            ])
            ->findOrFail($id);
    }

    public function getPendingItemsData(array $filters = []): array
    {
        $tenantId = require_tenant_id();

        $requisitions = PurchaseRequisition::where('tenant_id', $tenantId)
            ->where('status', 'Approved')
            ->with(['items.product.vendor', 'items.warehouse'])
            ->get();

        $pendingItems = [];

        foreach ($requisitions as $pr) {
            foreach ($pr->items as $item) {
                $alreadyOrderedQty = (float) $item->ordered_qty;
                $poOrderedQty = (float) PurchaseOrderItem::where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->whereHas('order', function ($q) use ($pr) {
                        $q->where('purchase_requisition_id', $pr->id)
                          ->where('status', 'Approved');
                    })
                    ->sum('quantity');

                $alreadyOrderedQty = max($alreadyOrderedQty, $poOrderedQty);
                $pendingQty = max(0.0, (float)$item->quantity - $alreadyOrderedQty);

                if ($pendingQty > 0.0001) {
                    $product = $item->product;
                    $vendor = null;

                    // Detect if this PR is for a Subcontract Service Operation
                    $isSubcontract = false;
                    $opSeq = null;
                    $opName = null;
                    $orderNum = null;
                    $opId = null;

                    if (in_array($pr->source_type, ['mo', 'ProductionOrder']) && $pr->source_id) {
                        $order = \App\Domains\Production\Models\ProductionOrder::find($pr->source_id);
                        if ($order) {
                            $orderNum = $order->order_number;
                            $extOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
                                ->where('production_order_id', $order->id)
                                ->where('is_external', true)
                                ->first();

                            if ($extOp && ($item->product_id === $extOp->subcontract_service_product_id || str_contains((string)$pr->notes, "Op #{$extOp->sequence}") || str_contains((string)$pr->notes, 'Subcontract Service'))) {
                                $isSubcontract = true;
                                $opSeq = $extOp->sequence;
                                $opName = $extOp->name;
                                $opId = $extOp->id;
                                if ($extOp->vendor_id) {
                                    $vendor = \App\Domains\Inventory\Models\Vendor::find($extOp->vendor_id);
                                }
                            }
                        }
                    }

                    if (!$vendor) {
                        if ($product->preferred_vendor_id) {
                            $vendor = $product->vendor;
                        } else {
                            $lastPoItem = PurchaseOrderItem::where('tenant_id', $tenantId)
                                ->where('product_id', $product->id)
                                ->whereHas('order', function ($q) {
                                    $q->where('status', 'Approved');
                                })
                                ->with('order.vendor')
                                ->orderBy('id', 'desc')
                                ->first();

                            $vendor = $lastPoItem?->order?->vendor;
                        }
                    }

                    $pendingItems[] = [
                        'item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $product->name,
                        'sku' => $product->sku ?: 'No SKU',
                        'uom' => $item->uom->code ?? $product->uom->code ?? 'PCS',
                        'requisition_number' => $pr->requisition_number,
                        'requisition_id' => $pr->id,
                        'requisition_date' => $pr->requisition_date,
                        'expected_date' => $pr->expected_date,
                        'quantity_requested' => (float) $item->quantity,
                        'quantity_ordered' => $alreadyOrderedQty,
                        'quantity_pending' => $pendingQty,
                        'estimated_cost' => (float) $item->estimated_cost,
                        'warehouse_id' => $item->warehouse_id,
                        'warehouse_name' => $item->warehouse->name ?? '—',
                        'vendor_id' => $vendor?->id ?? null,
                        'vendor_name' => $vendor?->name ?? 'No Supplier Assigned',
                        'vendor_code' => $vendor?->code ?? '',
                        'is_subcontract' => $isSubcontract,
                        'operation_sequence' => $opSeq,
                        'operation_name' => $opName,
                        'production_order_number' => $orderNum,
                        'production_order_operation_id' => $opId,
                    ];
                }
            }
        }

        $groupBy = $filters['group_by'] ?? 'supplier';
        $assignedItems = [];
        $unassignedItems = [];

        foreach ($pendingItems as $pi) {
            if ($pi['vendor_id']) {
                $assignedItems[] = $pi;
            } else {
                $unassignedItems[] = $pi;
            }
        }

        usort($assignedItems, function ($a, $b) {
            return strcmp($a['vendor_name'], $b['vendor_name']);
        });

        if ($groupBy === 'pr') {
            usort($pendingItems, function ($a, $b) {
                return strcmp($a['requisition_number'], $b['requisition_number']);
            });
        } elseif ($groupBy === 'date') {
            usort($pendingItems, function ($a, $b) {
                $aDate = $a['requisition_date'] ? $a['requisition_date']->toDateString() : '';
                $bDate = $b['requisition_date'] ? $b['requisition_date']->toDateString() : '';
                return strcmp($aDate, $bDate);
            });
        }

        $vendors = Vendor::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->get();

        return compact(
            'pendingItems',
            'assignedItems',
            'unassignedItems',
            'groupBy',
            'vendors'
        );
    }

    public function update(PurchaseRequisition $requisition, array $data): bool
    {
        return $requisition->update($data);
    }

    public function delete(PurchaseRequisition $requisition): ?bool
    {
        return \DB::transaction(function () use ($requisition) {
            $requisition->items()->delete();
            return $requisition->delete();
        });
    }
}
