<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MaterialRequirementRepository
{
    public function getAll(): Collection
    {
        return MaterialRequirement::with('salesOrder.customer')->latest()->get();
    }

    /**
     * Get paginated material requirements with search, filters, and sorting.
     */
    public function getPaginatedRequirements(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = MaterialRequirement::query()
            ->with('salesOrder.customer');

        // Search Keywords (Requirement #, SO #, Customer Name, Carrier, Tracking)
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('requirement_number', 'like', "%{$search}%")
                  ->orWhere('carrier', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhereHas('salesOrder', function ($sq) use ($search) {
                      $sq->where('sales_order_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($cq) use ($search) {
                            $cq->where('name', 'like', "%{$search}%")
                              ->orWhere('company_name', 'like', "%{$search}%");
                        });
                  });
            });
        }

        // Status Filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Date Range Filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('requirement_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('requirement_date', '<=', $filters['date_to']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'requirement_number') {
            $query->orderBy('requirement_number', $sortOrder);
        } elseif ($sortBy === 'requirement_date') {
            $query->orderBy('requirement_date', $sortOrder);
        } elseif ($sortBy === 'status') {
            $query->orderBy('status', $sortOrder);
        } elseif ($sortBy === 'customer') {
            $query->join('sales_orders', 'material_requirements.sales_order_id', '=', 'sales_orders.id')
                  ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.id')
                  ->orderBy('customers.name', $sortOrder)
                  ->select('material_requirements.*');
        } else {
            $query->orderBy('id', $sortOrder);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?MaterialRequirement
    {
        return MaterialRequirement::with([
            'salesOrder.customer',
            'items.product',
            'items.warehouse',
        ])->find($id);
    }

    public function create(array $data): MaterialRequirement
    {
        return MaterialRequirement::create($data);
    }

    public function updateStatus(MaterialRequirement $requirement, string $status): bool
    {
        return $requirement->update(['status' => $status]);
    }

    public function delete(MaterialRequirement $requirement): ?bool
    {
        return $requirement->delete();
    }

    public function getCreateData(int $salesOrderId): array
    {
        $salesOrder = SalesOrder::with('items.product', 'items.warehouse', 'customer')->findOrFail($salesOrderId);

        $salesOrder->setRelation('items', $salesOrder->items->filter(function ($item) {
            return !$item->product || $item->product->supplier_method === 'buy' || is_null($item->product->supplier_method);
        }));

        if (!in_array($salesOrder->status, ['Confirmed', 'Partially Shipped'])) {
            abort(400, 'Material Requirements can only be created for Confirmed or Partially Shipped Sales Orders.');
        }

        $shippedQuantities = [];
        foreach ($salesOrder->items as $item) {
            $shippedQuantities[$item->id] = MaterialRequirementItem::query()
                ->whereHas('materialRequirement', function ($q) {
                    $q->where('status', 'Shipped');
                })
                ->where('sales_order_item_id', $item->id)
                ->sum('quantity');
        }

        $warehouses = Warehouse::query()->where('status', 'active')->orderBy('name')->get();

        $productIds = $salesOrder->items->pluck('product_id')->filter()->unique()->toArray();
        $stocks = ProductWarehouseStock::query()->whereIn('product_id', $productIds)->get();

        $stockMap = [];
        foreach ($stocks as $stock) {
            $stockMap[$stock->product_id][$stock->warehouse_id] = (float) $stock->available_qty;
        }

        return compact('salesOrder', 'shippedQuantities', 'warehouses', 'stockMap');
    }
}
