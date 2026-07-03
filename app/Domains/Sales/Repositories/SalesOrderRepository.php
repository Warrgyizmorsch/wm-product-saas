<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Sales\Models\SalesOrder;

class SalesOrderRepository
{
    public function create(array $data): SalesOrder
    {
        return SalesOrder::query()->create($data);
    }

    public function find(int $id): ?SalesOrder
    {
        return SalesOrder::query()->with(['customer', 'salesPerson', 'quotation', 'items.product'])->find($id);
    }

    public function count(): int
    {
        return SalesOrder::query()->count();
    }

    public function latest(): \Illuminate\Database\Eloquent\Collection
    {
        return SalesOrder::query()
            ->with(['customer', 'salesPerson'])
            ->latest()
            ->get();
    }
}
