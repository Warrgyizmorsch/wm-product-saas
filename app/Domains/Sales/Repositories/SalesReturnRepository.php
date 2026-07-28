<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Sales\Models\SalesReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SalesReturnRepository
{
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SalesReturn::query()->with(['customer', 'salesOrder']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?SalesReturn
    {
        return SalesReturn::with(['customer', 'salesOrder', 'items.product', 'items.warehouse'])->find($id);
    }

    public function create(array $data): SalesReturn
    {
        return SalesReturn::create($data);
    }
}
