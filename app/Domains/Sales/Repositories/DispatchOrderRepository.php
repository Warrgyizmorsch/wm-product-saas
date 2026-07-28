<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Sales\Models\DispatchOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DispatchOrderRepository
{
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DispatchOrder::query()->with(['customer', 'salesOrder']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('dispatch_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?DispatchOrder
    {
        return DispatchOrder::with(['customer', 'salesOrder', 'items.product', 'items.warehouse'])->find($id);
    }

    public function create(array $data): DispatchOrder
    {
        return DispatchOrder::create($data);
    }
}
