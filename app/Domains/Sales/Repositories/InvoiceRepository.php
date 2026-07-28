<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Sales\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceRepository
{
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::query()->with(['customer', 'salesOrder']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
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

    public function find(int $id): ?Invoice
    {
        return Invoice::with(['customer', 'salesOrder', 'items.product', 'allocations.payment'])->find($id);
    }

    public function create(array $data): Invoice
    {
        return Invoice::create($data);
    }
}
