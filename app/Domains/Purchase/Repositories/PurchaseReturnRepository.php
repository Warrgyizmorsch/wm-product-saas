<?php

namespace App\Domains\Purchase\Repositories;

use App\Domains\Purchase\Models\PurchaseReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseReturnRepository
{
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseReturn::query()->with(['vendor', 'purchaseOrder']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?PurchaseReturn
    {
        return PurchaseReturn::with(['vendor', 'purchaseOrder', 'items.product', 'items.warehouse'])->find($id);
    }

    public function create(array $data): PurchaseReturn
    {
        return PurchaseReturn::create($data);
    }
}
