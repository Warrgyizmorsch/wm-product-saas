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
            $cleanSoSearch = str_replace('SO-', '', $search);

            $query->where(function ($q) use ($search, $cleanSoSearch) {
                $q->where('dispatch_number', 'like', "%{$search}%")
                  ->orWhere('carrier', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('gate_pass_number', 'like', "%{$search}%")
                  ->orWhere('vehicle_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('salesOrder', function ($sq) use ($search, $cleanSoSearch) {
                      $sq->where('sales_order_number', 'like', "%{$search}%")
                         ->orWhere('sales_order_number', 'like', "%{$cleanSoSearch}%");
                  })
                  ->orWhereHas('materialRequirement', function ($mq) use ($search) {
                      $mq->where('requirement_number', 'like', "%{$search}%");
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
        return MaterialRequirement::with(['salesOrder.customer', 'items.product'])
            ->whereHas('salesOrder', function ($q) {
                $q->whereIn('status', ['Confirmed', 'Partially Shipped', 'In Fulfillment', 'Partially Delivered']);
            })
            ->whereNotIn('id', DispatchOrder::pluck('material_requirement_id'))
            ->whereIn('status', ['Released', 'Ready', 'Partially Ready', 'Picked', 'Packed', 'Partially Dispatched', 'Partially Delivered'])
            ->latest()
            ->get()
            ->filter(function ($mr) {
                if ($mr->salesOrder && $mr->salesOrder->hasOnlyServices()) {
                    return false;
                }
                return $mr->items->filter(fn($item) => $item->product && !$item->product->isService())->isNotEmpty();
            })
            ->take($limit)
            ->values();
    }

    public function getAllPendingMaterialRequirements(): Collection
    {
        return MaterialRequirement::with([
            'salesOrder.customer',
            'items.product',
            'items.warehouse',
        ])
        ->whereHas('salesOrder', function ($q) {
            $q->whereIn('status', ['Confirmed', 'Partially Shipped', 'In Fulfillment', 'Partially Delivered']);
        })
        ->whereIn('status', ['Released', 'Ready', 'Partially Ready', 'Picked', 'Packed', 'Partially Dispatched', 'Dispatched', 'Partially Delivered'])
        ->latest()
        ->get()
        ->filter(function ($mr) {
            if ($mr->salesOrder && $mr->salesOrder->hasOnlyServices()) {
                return false;
            }
            return $mr->items->filter(fn($item) => $item->product && !$item->product->isService())->isNotEmpty();
        })
        ->values();
    }

    public function getPendingSalesOrders(int $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $salesOrdersQuery = \App\Domains\Sales\Models\SalesOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['Confirmed', 'Partially Shipped', 'In Fulfillment', 'Partially Delivered'])
            ->with([
                'customer',
                'materialRequirements',
                'items.product.uom',
                'items.warehouse',
                'dispatches' => function ($q) {
                    $q->with(['items.product.uom'])->latest('dispatch_date')->latest('id');
                }
            ])
            ->latest('order_date');

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $cleanSoSearch = str_replace('SO-', '', $search);
            $salesOrdersQuery->where(function ($q) use ($search, $cleanSoSearch) {
                $q->where('sales_order_number', 'like', "%{$search}%")
                  ->orWhere('sales_order_number', 'like', "%{$cleanSoSearch}%")
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
            if ($order->hasOnlyServices()) {
                continue;
            }

            // Must be released by Store Incharge via Material Requirement if MR exists
            $mrs = $order->materialRequirements;
            $hasMr = $mrs && $mrs->isNotEmpty();
            if ($hasMr) {
                $isReleased = $mrs->contains(function ($mr) {
                    return in_array($mr->status, ['Released', 'Ready', 'Partially Ready', 'Picked', 'Packed', 'Dispatched', 'Partially Dispatched', 'Partially Delivered'])
                        || $mr->items->contains(fn($i) => (float)($i->quantity_released ?? 0) > 0);
                });
                if (!$isReleased) {
                    continue; // Skip until Store Incharge releases this requirement
                }
            }

            $mrItems = $hasMr ? $mrs->flatMap->items : collect();
            $orderDispatches = $dispatchedAggregates->get($order->id, collect());
            $totalOrdered = 0;
            $totalDispatched = 0;
            $itemsDetail = [];

            foreach ($order->items as $item) {
                if ($item->isService()) {
                    continue;
                }

                $matchingMrItem = null;
                if ($hasMr) {
                    $matchingMrItem = $mrItems->firstWhere('sales_order_item_id', $item->id)
                        ?? $mrItems->firstWhere('product_id', $item->product_id);

                    // If an MR exists, only show items that have been released by Store Incharge!
                    $releasedQty = (float)($matchingMrItem?->quantity_released ?? 0);
                    $isItemReleased = in_array($matchingMrItem?->status, ['Released', 'Picked', 'Packed', 'Dispatched', 'Partially Dispatched', 'Partially Delivered']) || $releasedQty > 0;

                    if (!$isItemReleased) {
                        continue; // Skip unreleased shortage items!
                    }

                    $effectiveOrdered = $releasedQty > 0 ? $releasedQty : (float)$item->quantity;
                } else {
                    $effectiveOrdered = (float)$item->quantity;
                }

                $dispRow = $orderDispatches->firstWhere('product_id', $item->product_id);
                $dispatched = $dispRow ? (float) $dispRow->total_dispatched : 0.0;
                $pending = max(0, $effectiveOrdered - $dispatched);

                $totalOrdered += $effectiveOrdered;
                $totalDispatched += min($effectiveOrdered, $dispatched);

                // Use the warehouse selected during MR release!
                $itemWarehouseId = $matchingMrItem?->warehouse_id ?: $item->warehouse_id;
                $itemWarehouseName = $matchingMrItem?->warehouse?->name ?: ($item->warehouse?->name ?? 'Main');
                $availStock = \App\Domains\Inventory\Services\StockService::getAvailableStock((int) $item->product_id, (int) $itemWarehouseId);

                $itemsDetail[] = [
                    'product_id'     => $item->product_id,
                    'product_name'   => $item->product?->name ?? $item->item_name ?? 'Item',
                    'sku'            => $item->product?->sku ?? '',
                    'uom'            => $item->product?->uom?->code ?? 'Units',
                    'warehouse_name' => $itemWarehouseName,
                    'ordered_qty'    => $effectiveOrdered,
                    'dispatched_qty' => $dispatched,
                    'pending_qty'    => $pending,
                    'available_stock'=> $availStock,
                ];
            }

            $totalPending = max(0, $totalOrdered - $totalDispatched);

            if ($totalPending > 0 && !empty($itemsDetail)) {
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
            ->whereIn('status', ['Confirmed', 'Partially Shipped', 'In Fulfillment', 'Partially Delivered'])
            ->with(['items.product', 'materialRequirements'])
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
            if ($order->hasOnlyServices()) {
                continue;
            }

            // Must be released by Store Incharge via Material Requirement if MR exists
            $mrs = $order->materialRequirements;
            $hasMr = $mrs && $mrs->isNotEmpty();
            if ($hasMr) {
                $isReleased = $mrs->contains(function ($mr) {
                    return in_array($mr->status, ['Released', 'Ready', 'Partially Ready', 'Picked', 'Packed', 'Dispatched', 'Partially Dispatched', 'Partially Delivered'])
                        || $mr->items->contains(fn($i) => (float)($i->quantity_released ?? 0) > 0);
                });
                if (!$isReleased) {
                    continue; // Skip until Store Incharge releases this requirement
                }
            }

            $mrItems = $hasMr ? $mrs->flatMap->items : collect();
            $orderDispatches = $dispatchedAggregates->get($order->id, collect());
            $totalOrdered = 0;
            $totalDispatched = 0;
            $hasPhysical = false;

            foreach ($order->items as $item) {
                if ($item->isService()) {
                    continue;
                }

                if ($hasMr) {
                    $matchingMrItem = $mrItems->firstWhere('sales_order_item_id', $item->id)
                        ?? $mrItems->firstWhere('product_id', $item->product_id);
                    $relQty = (float)($matchingMrItem?->quantity_released ?? 0);
                    $isRel = in_array($matchingMrItem?->status, ['Released', 'Picked', 'Packed', 'Dispatched']) || $relQty > 0;
                    if (!$isRel) {
                        continue;
                    }
                    $ordered = $relQty > 0 ? $relQty : (float)$item->quantity;
                } else {
                    $ordered = (float)$item->quantity;
                }

                $hasPhysical = true;
                $totalOrdered += $ordered;
                $dispRow = $orderDispatches->firstWhere('product_id', $item->product_id);
                $dispatched = $dispRow ? (float) $dispRow->total_dispatched : 0.0;
                $totalDispatched += min($ordered, $dispatched);
            }

            if ($hasPhysical && ($totalOrdered - $totalDispatched) > 0) {
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
        $soItemDispatched = (float) \App\Domains\Sales\Models\SalesOrderItem::where('sales_order_id', $salesOrderId)->sum('dispatched_qty');
        if ($soItemDispatched > 0) {
            return $soItemDispatched;
        }

        return (float) DispatchOrderItem::whereHas('dispatchOrder', function ($q) use ($salesOrderId) {
            $q->where('sales_order_id', $salesOrderId)
              ->whereIn('status', ['Shipped', 'Dispatched', 'Delivered']);
        })->sum(DB::raw('COALESCE(NULLIF(quantity_dispatched, 0), quantity_ordered)'));
    }

    public function createDispatchOrder(array $dispatchData, array $itemsData): DispatchOrder
    {
        return DB::transaction(function () use ($dispatchData, $itemsData) {
            $dispatch = DispatchOrder::create($dispatchData);

            foreach ($itemsData as $item) {
                $soItemId = $item['sales_order_item_id'] ?? null;
                if (!$soItemId && !empty($dispatch->sales_order_id)) {
                    $matchedSoItem = \App\Domains\Sales\Models\SalesOrderItem::where('sales_order_id', $dispatch->sales_order_id)
                        ->where('product_id', $item['product_id'])
                        ->whereRaw('(quantity - (dispatched_qty + cancelled_qty)) > 0')
                        ->first();
                    $soItemId = $matchedSoItem?->id ?? \App\Domains\Sales\Models\SalesOrderItem::where('sales_order_id', $dispatch->sales_order_id)
                        ->where('product_id', $item['product_id'])
                        ->first()?->id;
                }

                DispatchOrderItem::create([
                    'tenant_id' => $dispatch->tenant_id,
                    'company_id' => $dispatch->company_id,
                    'branch_id' => $dispatch->branch_id,
                    'dispatch_order_id' => $dispatch->id,
                    'sales_order_item_id' => $soItemId,
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

    public function getNextDispatchNumber(?int $tenantId = null, ?int $companyId = null, ?int $branchId = null): string
    {
        $year = now()->format('Y');
        $prefix = "DISP-{$year}-";

        $tenantId = $tenantId ?? (tenant_id() ?? auth()->user()?->tenant_id ?? 1);
        $companyId = $companyId ?? (company_id() ?? auth()->user()?->company_id);
        $branchId = $branchId ?? (branch_id() ?? auth()->user()?->branch_id);

        $query = DispatchOrder::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('dispatch_number', 'like', "{$prefix}%");

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $latest = $query->orderBy('id', 'desc')->first();

        $nextNum = 1;
        if ($latest) {
            $rawNum = $latest->getRawOriginal('dispatch_number') ?? $latest->dispatch_number;
            $lastNumStr = str_replace($prefix, '', $rawNum);
            $nextNum = intval($lastNumStr) + 1;
        }

        do {
            $candidate = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
            $nextNum++;

            $checkQuery = DispatchOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('dispatch_number', $candidate);

            if ($companyId !== null) {
                $checkQuery->where('company_id', $companyId);
            }
            if ($branchId !== null) {
                $checkQuery->where('branch_id', $branchId);
            }
        } while ($checkQuery->exists());

        return $candidate;
    }
}
