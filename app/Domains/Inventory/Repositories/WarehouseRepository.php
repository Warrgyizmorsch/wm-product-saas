<?php

namespace App\Domains\Inventory\Repositories;

use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

class WarehouseRepository
{
    public function getAll(): Collection
    {
        return Warehouse::query()->latest()->get();
    }

    public function getActive(): Collection
    {
        return Warehouse::query()->where('status', 'active')->orderBy('name')->get();
    }

    public function find(int $id): ?Warehouse
    {
        return Warehouse::find($id);
    }

    public function create(array $data): Warehouse
    {
        return Warehouse::create($data);
    }

    public function update(Warehouse $warehouse, array $data): bool
    {
        return $warehouse->update($data);
    }

    public function delete(Warehouse $warehouse): ?bool
    {
        // Guard against cascade deletion of historical stock transactions or active inventory (BUG-INV-DATA-01)
        if (\App\Domains\Inventory\Models\StockTransaction::where('warehouse_id', $warehouse->id)->exists()
            || \App\Domains\Inventory\Models\ProductWarehouseStock::where('warehouse_id', $warehouse->id)->where('quantity', '>', 0)->exists()
            || \App\Domains\Inventory\Models\Batch::where('warehouse_id', $warehouse->id)->where('quantity', '>', 0)->exists()
            || \App\Domains\Inventory\Models\SerialNumber::where('warehouse_id', $warehouse->id)->where('status', '!=', 'Sold')->exists()) {
            throw new \InvalidArgumentException('Cannot delete warehouse with existing stock history or active inventory.');
        }

        if ($warehouse->is_default) {
            $other = Warehouse::query()->where('id', '!=', $warehouse->id)->first();
            if ($other) {
                $other->update(['is_default' => true]);
            } else {
                return false;
            }
        }

        return $warehouse->delete();
    }

    public function setDefault(Warehouse $warehouse): void
    {
        Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
        $warehouse->update(['is_default' => true]);
    }
}
