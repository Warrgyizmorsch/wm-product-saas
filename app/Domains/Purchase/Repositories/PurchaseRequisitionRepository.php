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
            ->with(['requester', 'sourceable']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where('requisition_number', 'like', $search);
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['id', 'requisition_number', 'requisition_date', 'status'];

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
            ->with(['requester', 'sourceable']);

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where('requisition_number', 'like', $search);
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

                    $pendingItems[] = [
                        'item_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $product->name,
                        'sku' => $product->sku ?: 'No SKU',
                        'uom' => $item->uom->code ?? $product->uom->code ?? 'PCS',
                        'requisition_number' => $pr->requisition_number,
                        'requisition_id' => $pr->id,
                        'requisition_date' => $pr->requisition_date,
                        'quantity_requested' => (float) $item->quantity,
                        'quantity_ordered' => $alreadyOrderedQty,
                        'quantity_pending' => $pendingQty,
                        'estimated_cost' => (float) $item->estimated_cost,
                        'warehouse_id' => $item->warehouse_id,
                        'warehouse_name' => $item->warehouse->name ?? '—',
                        'vendor_id' => $vendor?->id ?? null,
                        'vendor_name' => $vendor?->name ?? 'No Supplier Assigned',
                        'vendor_code' => $vendor?->code ?? '',
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
