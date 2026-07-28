<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\MaterialRequirementItem;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use Illuminate\Database\Eloquent\Collection;

class MaterialRequirementRepository
{
    public function getAll(): Collection
    {
        return MaterialRequirement::with('salesOrder.customer')->latest()->get();
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
