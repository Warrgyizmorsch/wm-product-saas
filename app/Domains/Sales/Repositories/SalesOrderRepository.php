<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SalesOrderRepository
{
    public function find(int $id): ?SalesOrder
    {
        return SalesOrder::query()->with(['customer', 'salesPerson', 'quotation', 'items.product', 'items.warehouse'])->find($id);
    }

    public function findWithDetails(int $id): SalesOrder
    {
        return SalesOrder::with([
            'customer', 
            'salesPerson', 
            'quotation', 
            'items.product', 
            'items.warehouse',
            'materialRequirements.items', 
            'dispatches.items.product',
            'dispatches.transporter',
            'invoices.items', 
            'allocations.payment', 
            'returns.items',
            'productionOrders.product',
        ])->findOrFail($id);
    }

    public function create(array $data): SalesOrder
    {
        return SalesOrder::query()->create($data);
    }

    public function update(SalesOrder $order, array $data): bool
    {
        return $order->update($data);
    }

    public function delete(SalesOrder $order): ?bool
    {
        return $order->delete();
    }

    public function count(): int
    {
        return SalesOrder::query()->count();
    }

    public function latest(): Collection
    {
        return SalesOrder::query()
            ->with(['customer', 'salesPerson'])
            ->orderBy('order_date', 'desc')
            ->orderBy('sales_order_number', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get paginated sales orders with filters.
     */
    public function getPaginatedOrders(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = SalesOrder::query()->with(['customer', 'quotation', 'invoices', 'dispatches']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('sales_order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($custQ) use ($search) {
                      $custQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'order_date';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['sales_order_number', 'order_date', 'total_amount', 'status'];

        if ($sortBy === 'order_date') {
            $query->orderBy('order_date', $sortOrder)
                  ->orderBy('sales_order_number', $sortOrder === 'asc' ? 'asc' : 'desc')
                  ->orderBy('id', $sortOrder === 'asc' ? 'asc' : 'desc');
        } elseif ($sortBy === 'sales_order_number') {
            $query->orderBy('sales_order_number', $sortOrder)
                  ->orderBy('order_date', 'desc')
                  ->orderBy('id', $sortOrder);
        } elseif (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder)
                  ->orderBy('sales_order_number', 'desc')
                  ->orderBy('id', 'desc');
        } else {
            $query->orderBy('order_date', 'desc')
                  ->orderBy('sales_order_number', 'desc')
                  ->orderBy('id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Confirm Sales Order and auto-generate Material Requirement for physical items only.
     * Service products do not require material requisition or inventory reservation.
     */
    public function confirmOrder(SalesOrder $order, string $nextRequirementNumber = ''): ?MaterialRequirement
    {
        return DB::transaction(function () use ($order, $nextRequirementNumber) {
            $order->update([
                'status' => 'Confirmed',
            ]);

            // Filter out service items - in ERPs, service items do NOT generate material requirement / inventory reservation
            $physicalItems = $order->items->filter(fn($item) => !$item->isService());

            // If order has ONLY service items, NO material requirement is generated!
            if ($physicalItems->isEmpty()) {
                return null;
            }

            if (empty($nextRequirementNumber)) {
                $nextRequirementNumber = app(\App\Domains\Sales\Services\MaterialRequirementService::class)->getNextRequirementNumber();
            }

            $delivery = MaterialRequirement::where('sales_order_id', $order->id)->first();
            if (!$delivery) {
                $delivery = MaterialRequirement::create([
                    'tenant_id' => $order->tenant_id,
                    'company_id' => $order->company_id,
                    'branch_id' => $order->branch_id,
                    'sales_order_id' => $order->id,
                    'requirement_number' => $nextRequirementNumber,
                    'requirement_date' => now(),
                    'status' => 'Pending',
                ]);

                $defaultWarehouseId = Warehouse::where('tenant_id', $order->tenant_id)
                    ->orderBy('is_default', 'desc')
                    ->first()?->id ?? 1;

                foreach ($physicalItems as $soItem) {
                    MaterialRequirementItem::create([
                        'tenant_id' => $order->tenant_id,
                        'company_id' => $order->company_id,
                        'branch_id' => $order->branch_id,
                        'material_requirement_id' => $delivery->id,
                        'sales_order_item_id' => $soItem->id,
                        'product_id' => $soItem->product_id,
                        'warehouse_id' => $soItem->warehouse_id ?? $defaultWarehouseId,
                        'quantity' => $soItem->quantity,
                        'quantity_ordered' => $soItem->quantity,
                        'quantity_reserved' => 0.0000,
                        'status' => 'Pending',
                    ]);
                }
            }

            return $delivery;
        });
    }

    /**
     * Cancel Sales Order with cascading cleanup of open dispatch orders and reservations.
     *
     * @throws \Exception
     */
    public function cancelOrder(SalesOrder $order, ?string $reason = null): bool
    {
        if (in_array($order->status, ['Shipped', 'Delivered'])) {
            throw new \Exception('Cannot cancel a Shipped or Delivered Sales Order.');
        }

        if ($order->status === 'Partially Shipped') {
            throw new \Exception('Partially Shipped orders cannot be cancelled directly. Please Short-Close the remaining backorder instead.');
        }

        return DB::transaction(function () use ($order, $reason) {
            $dispatchService = app(\App\Domains\Sales\Services\DispatchOrderService::class);

            // Cancel any open (Pending/Confirmed) dispatch orders
            foreach ($order->dispatches as $dispatch) {
                if (in_array($dispatch->status, ['Pending', 'Confirmed'])) {
                    $dispatchService->cancelDispatchOrder($dispatch, 'Parent Sales Order was cancelled.');
                }
            }

            // Cancel any active Material Requirements & release MR reservations
            foreach ($order->materialRequirements as $mr) {
                if ($mr->status !== 'Cancelled') {
                    $mr->update(['status' => 'Cancelled']);
                }
            }

            // Cancel all items and update status
            foreach ($order->items as $item) {
                $item->update([
                    'cancelled_qty' => $item->quantity,
                    'allocated_qty' => 0,
                    'status' => 'Cancelled',
                ]);
            }

            return $order->update([
                'status' => 'Cancelled',
                'closure_reason' => $reason,
            ]);
        });
    }

    /**
     * Short-Close remaining unfulfilled quantity on Sales Order.
     *
     * @throws \Exception
     */
    public function shortCloseOrder(SalesOrder $order, string $reason): bool
    {
        if (in_array($order->status, ['Draft', 'Cancelled', 'Closed', 'Short-Closed'])) {
            throw new \Exception("Cannot Short-Close an order in {$order->status} status.");
        }

        return DB::transaction(function () use ($order, $reason) {
            $dispatchService = app(\App\Domains\Sales\Services\DispatchOrderService::class);

            // Cancel any open un-shipped dispatch orders
            foreach ($order->dispatches as $dispatch) {
                if (in_array($dispatch->status, ['Pending', 'Confirmed'])) {
                    $dispatchService->cancelDispatchOrder($dispatch, 'Parent Sales Order was short-closed.');
                }
            }

            // Mark remaining open quantity as cancelled on each item
            foreach ($order->items as $item) {
                $ordered = (float) $item->quantity;
                $dispatched = (float) $item->dispatched_qty;
                $rem = max(0.0, $ordered - $dispatched);

                $item->update([
                    'cancelled_qty' => $rem,
                    'allocated_qty' => 0,
                ]);
                $item->refreshStatus();
            }

            return $order->update([
                'status' => 'Short-Closed',
                'closure_reason' => $reason,
            ]);
        });
    }
}
