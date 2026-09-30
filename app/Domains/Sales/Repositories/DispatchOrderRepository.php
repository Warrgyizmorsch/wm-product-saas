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

    public function getPendingSalesOrders(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $salesOrdersQuery = \App\Domains\Sales\Models\SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['Cancelled', 'cancelled']);
            })
            ->with(['customer', 'items.product.uom', 'items.warehouse'])
            ->latest('order_date');

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $salesOrdersQuery->where(function ($q) use ($search) {
                $q->where('sales_order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $allOrders = $salesOrdersQuery->get();

        $dispatchedAggregates = DispatchOrderItem::whereHas('dispatchOrder', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->where('status', '!=', 'Cancelled');
        })
        ->select('dispatch_orders.sales_order_id', 'dispatch_order_items.product_id', DB::raw('SUM(COALESCE(NULLIF(dispatch_order_items.quantity_dispatched, 0), dispatch_order_items.quantity_ordered)) as total_dispatched'))
        ->join('dispatch_orders', 'dispatch_orders.id', '=', 'dispatch_order_items.dispatch_order_id')
        ->groupBy('dispatch_orders.sales_order_id', 'dispatch_order_items.product_id')
        ->get()
        ->groupBy('sales_order_id');

        $pendingList = collect();

        foreach ($allOrders as $order) {
            $orderDispatches = $dispatchedAggregates->get($order->id, collect());
            $totalOrdered = 0;
            $totalDispatched = 0;
            $itemsDetail = [];

            foreach ($order->items as $item) {
                $ordered = (float) $item->quantity;
                $dispRow = $orderDispatches->firstWhere('product_id', $item->product_id);
                $dispatched = $dispRow ? (float) $dispRow->total_dispatched : 0.0;
                $pending = max(0, $ordered - $dispatched);

                $totalOrdered += $ordered;
                $totalDispatched += min($ordered, $dispatched);

                $availStock = \App\Domains\Inventory\Services\StockService::getAvailableStock((int) $item->product_id, (int) $item->warehouse_id);

                $itemsDetail[] = [
                    'product_id'     => $item->product_id,
                    'product_name'   => $item->product?->name ?? $item->item_name ?? 'Item',
                    'sku'            => $item->product?->sku ?? '',
                    'uom'            => $item->product?->uom?->code ?? 'Units',
                    'warehouse_name' => $item->warehouse?->name ?? 'Main',
                    'ordered_qty'    => $ordered,
                    'dispatched_qty' => $dispatched,
                    'pending_qty'    => $pending,
                    'available_stock'=> $availStock,
                ];
            }

            $totalPending = max(0, $totalOrdered - $totalDispatched);

            if ($totalPending > 0) {
                $progress = $totalOrdered > 0 ? round(($totalDispatched / $totalOrdered) * 100) : 0;
                $order->total_ordered_qty = $totalOrdered;
                $order->total_dispatched_qty = $totalDispatched;
                $order->total_pending_qty = $totalPending;
                $order->dispatch_progress = $progress;
                $order->dispatch_status_label = ($totalDispatched > 0) ? 'Partially Dispatched' : 'Pending Dispatch';
                $order->items_detail = $itemsDetail;

                $pendingList->push($order);
            }
        }

        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage('pending_page') ?: 1;
        $itemsForCurrentPage = $pendingList->slice(($page - 1) * $perPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $itemsForCurrentPage,
            $pendingList->count(),
            $perPage,
            $page,
            [
                'path'     => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'pageName' => 'pending_page',
            ]
        );
    }

    public function getPendingSalesOrdersCount(int $tenantId): int
    {
        $allOrders = \App\Domains\Sales\Models\SalesOrder::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhereNotIn('status', ['Cancelled', 'cancelled']);
            })
            ->with(['items'])
            ->get();

        $dispatchedAggregates = DispatchOrderItem::whereHas('dispatchOrder', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->where('status', '!=', 'Cancelled');
        })
        ->select('dispatch_orders.sales_order_id', 'dispatch_order_items.product_id', DB::raw('SUM(COALESCE(NULLIF(dispatch_order_items.quantity_dispatched, 0), dispatch_order_items.quantity_ordered)) as total_dispatched'))
        ->join('dispatch_orders', 'dispatch_orders.id', '=', 'dispatch_order_items.dispatch_order_id')
        ->groupBy('dispatch_orders.sales_order_id', 'dispatch_order_items.product_id')
        ->get()
        ->groupBy('sales_order_id');

        $count = 0;
        foreach ($allOrders as $order) {
            $orderDispatches = $dispatchedAggregates->get($order->id, collect());
            $totalOrdered = (float) $order->items->sum('quantity');
            $totalDispatched = 0;

            foreach ($order->items as $item) {
                $dispRow = $orderDispatches->firstWhere('product_id', $item->product_id);
                $dispatched = $dispRow ? (float) $dispRow->total_dispatched : 0.0;
                $totalDispatched += min((float) $item->quantity, $dispatched);
            }

            if (($totalOrdered - $totalDispatched) > 0) {
                $count++;
            }
        }

        return $count;
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
