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
            ->latest()
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
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['sales_order_number', 'order_date', 'total_amount', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Confirm Sales Order and auto-generate Material Requirement.
     */
    public function confirmOrder(SalesOrder $order, string $nextRequirementNumber): MaterialRequirement
    {
        return DB::transaction(function () use ($order, $nextRequirementNumber) {
            $order->update([
                'status' => 'Confirmed',
            ]);

            $delivery = MaterialRequirement::create([
                'tenant_id' => $order->tenant_id,
                'sales_order_id' => $order->id,
                'requirement_number' => $nextRequirementNumber,
                'requirement_date' => now(),
                'status' => 'Pending',
            ]);

            $defaultWarehouseId = Warehouse::where('tenant_id', $order->tenant_id)
                ->orderBy('is_default', 'desc')
                ->first()?->id ?? 1;

            foreach ($order->items as $soItem) {
                MaterialRequirementItem::create([
                    'tenant_id' => $order->tenant_id,
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

            return $delivery;
        });
    }

    /**
     * Cancel Sales Order.
     */
    public function cancelOrder(SalesOrder $order): bool
    {
        return $order->update(['status' => 'Cancelled']);
    }
}
