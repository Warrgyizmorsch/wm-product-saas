<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Sales\Models\CustomerPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerPaymentRepository
{
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CustomerPayment::query()->with(['customer', 'allocations.invoice']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?CustomerPayment
    {
        return CustomerPayment::with(['customer', 'allocations.invoice'])->find($id);
    }

    public function create(array $data): CustomerPayment
    {
        return CustomerPayment::create($data);
    }
}
