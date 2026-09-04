<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Sales\Models\DispatchOrder;
use App\Domains\Sales\Models\DispatchOrderItem;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DispatchOrderRepository
{
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DispatchOrder::query()->with(['customer', 'salesOrder.customer', 'materialRequirement']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('dispatch_number', 'like', "%{$search}%")
                  ->orWhere('shipping_agent', 'like', "%{$search}%")
                  ->orWhere('vehicle_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('salesOrder', function ($sq) use ($search) {
                      $sq->where('sales_order_number', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy    = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        if (in_array($sortBy, ['dispatch_number', 'dispatch_date', 'status', 'created_at'])) {
            $query->orderBy($sortBy, strtolower($sortOrder) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function getAllDispatches(): Collection
    {
        return DispatchOrder::with(['salesOrder.customer', 'materialRequirement'])
            ->latest()
            ->get();
    }

    public function getPendingDOs(int $limit = 5): Collection
    {
        return MaterialRequirement::with(['salesOrder.customer'])
            ->whereNotIn('id', DispatchOrder::pluck('material_requirement_id'))
            ->whereNotIn('status', ['Cancelled', 'Delivered'])
            ->latest()
            ->take($limit)
            ->get();
    }

    public function getAllPendingMaterialRequirements(): Collection
    {
        return MaterialRequirement::with([
            'salesOrder.customer',
            'items.product',
            'items.warehouse',
        ])
        ->whereNotIn('status', ['Cancelled'])
        ->latest()
        ->get();
    }

    public function find(int $id): ?DispatchOrder
    {
        return DispatchOrder::with(['customer', 'salesOrder', 'items.product', 'items.warehouse'])->find($id);
    }

    public function getDispatchedQtyForMRItem(int $mrItemId): float
    {
        $mrItem = MaterialRequirementItem::with('materialRequirement')->find($mrItemId);
        if (!$mrItem) {
            return (float) DispatchOrderItem::whereHas('dispatchOrder', function ($q) {
                $q->where('status', '!=', 'Cancelled');
            })
            ->where('material_requirement_item_id', $mrItemId)
            ->sum(DB::raw('COALESCE(NULLIF(quantity_dispatched, 0), quantity_ordered)'));
        }

        $soId = $mrItem->materialRequirement?->sales_order_id;
        $productId = $mrItem->product_id;

        return (float) DispatchOrderItem::whereHas('dispatchOrder', function ($q) use ($soId) {
            $q->where('status', '!=', 'Cancelled');
            if ($soId) {
                $q->where('sales_order_id', $soId);
            }
        })
        ->where(function($q) use ($mrItemId, $productId) {
            $q->where('material_requirement_item_id', $mrItemId);
            if ($productId) {
                $q->orWhere('product_id', $productId);
            }
        })
        ->sum(DB::raw('COALESCE(NULLIF(quantity_dispatched, 0), quantity_ordered)'));
    }

    public function getDispatchedQtyForInvoiceItem(int $invoiceItemId): float
    {
        return (float) DispatchOrderItem::whereHas('dispatchOrder', function ($q) {
            $q->where('status', '!=', 'Cancelled');
        })
        ->where('invoice_item_id', $invoiceItemId)
        ->sum('quantity_dispatched');
    }

    public function getDispatchedQtyForSalesOrder(int $salesOrderId): float
    {
        return (float) DispatchOrderItem::whereHas('dispatchOrder', function ($q) use ($salesOrderId) {
            $q->where('sales_order_id', $salesOrderId)->where('status', 'Dispatched');
        })->sum('quantity_dispatched');
    }

    public function createDispatchOrder(array $dispatchData, array $itemsData): DispatchOrder
    {
        return DB::transaction(function () use ($dispatchData, $itemsData) {
            $dispatch = DispatchOrder::create($dispatchData);

            foreach ($itemsData as $item) {
                DispatchOrderItem::create([
                    'dispatch_order_id' => $dispatch->id,
                    'material_requirement_item_id' => $item['material_requirement_item_id'] ?? null,
                    'invoice_item_id' => $item['invoice_item_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'quantity_ordered' => $item['quantity'],
                    'quantity_dispatched' => $item['quantity'],
                    'serial_numbers' => $item['serial_numbers'] ?? null,
                    'batch_number' => $item['batch_number'] ?? null,
                ]);
            }

            return $dispatch;
        });
    }

    public function getNextDispatchNumber(): string
    {
        $count = DispatchOrder::whereYear('created_at', now()->year)->count() + 1;
        return 'DISP-' . now()->format('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
