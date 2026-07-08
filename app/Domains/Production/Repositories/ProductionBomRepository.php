<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionBom;
use Illuminate\Database\Eloquent\Collection;

class ProductionBomRepository implements ProductionBomRepositoryInterface
{
    public function getAll(array $filters = []): Collection
    {
        $query = ProductionBom::query()->with(['product', 'creator']);

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('bom_number', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('bom_number')->get();
    }

    public function paginateAll(array $filters = [], int $perPage = 10): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = ProductionBom::query()->with(['product', 'creator']);

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('bom_number', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        $sortBy = $filters['sort_by'] ?? 'bom_number';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        if (!in_array($sortBy, ['bom_number', 'bom_name', 'base_quantity'])) {
            $sortBy = 'bom_number';
        }
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'asc';
        }

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    public function find(int $id): ?ProductionBom
    {
        return ProductionBom::find($id);
    }

    public function create(array $data): ProductionBom
    {
        return ProductionBom::create($data);
    }

    public function update(int $id, array $data): ProductionBom
    {
        $bom = ProductionBom::findOrFail($id);
        $bom->update($data);
        return $bom;
    }

    public function delete(int $id): bool
    {
        $bom = ProductionBom::findOrFail($id);
        return $bom->delete();
    }

    public function getActiveBom(int $productId): ?ProductionBom
    {
        return ProductionBom::query()
            ->active()
            ->where('product_id', $productId)
            ->first();
    }

    public function getBomWithComponents(int $id): ?ProductionBom
    {
        return ProductionBom::query()
            ->with(['product', 'items.material', 'items.uom', 'creator', 'approver'])
            ->find($id);
    }
}
