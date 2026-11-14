<?php

namespace App\Domains\Sales\Repositories;

use App\Domains\Sales\Models\CustomerPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerPaymentRepository
{
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $query = CustomerPayment::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer.crmAccount', 'allocations.invoice']);

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
        return CustomerPayment::withoutGlobalScope('rbac_scope')->with(['customer', 'allocations.invoice', 'allocations.salesOrder'])->find($id);
    }

    public function create(array $data): CustomerPayment
    {
        return CustomerPayment::create($data);
    }
}
