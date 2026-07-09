<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\Routing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class RoutingRepository implements RoutingRepositoryInterface
{
    public function getAll(array $filters = []): Collection
    {
        $query = Routing::query()
            ->with(['product', 'creator'])
            ->withCount('operations');

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('routing_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search): void {
                      $pq->where('name', 'like', "%{$search}%")
                         ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('routing_number')->get();
    }

    public function paginateAll(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Routing::query()
            ->with(['product', 'creator'])
            ->withCount('operations');

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('routing_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search): void {
                      $pq->where('name', 'like', "%{$search}%")
                         ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('routing_number')->paginate($perPage);
    }

    public function find(int $id): ?Routing
    {
        return Routing::find($id);
    }

    public function getRoutingWithOperations(int $id): ?Routing
    {
        return Routing::with([
            'product',
            'operations.workCenter',
            'operations.machine',
            'creator',
            'approver',
            'approvals.user',
        ])->find($id);
    }

    public function create(array $data): Routing
    {
        return Routing::create($data);
    }

    public function update(int $id, array $data): Routing
    {
        $routing = Routing::findOrFail($id);
        $routing->update($data);
        return $routing->fresh();
    }

    public function delete(int $id): bool
    {
        $routing = Routing::findOrFail($id);
        return (bool) $routing->delete(); // SoftDeletes
    }

    public function getActiveRouting(int $productId): ?Routing
    {
        return Routing::query()
            ->active()
            ->where('product_id', $productId)
            ->orderByDesc('is_default')
            ->first();
    }

    public function getPrimaryActiveRouting(int $productId): ?Routing
    {
        return Routing::query()
            ->active()
            ->where('product_id', $productId)
            ->where('is_default', true)
            ->first();
    }

    public function findByRoutingNumber(string $number, int $tenantId, ?int $ignoreId = null): ?Routing
    {
        $query = Routing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('routing_number', $number);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->first();
    }

    public function getLastSequenceNumber(int $tenantId): int
    {
        // Get the highest numeric sequence from routing_number for this tenant/year
        $year = Carbon::now()->year;
        $prefix = "RTG-{$year}-";

        $last = Routing::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('routing_number', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(routing_number, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
            ->value('routing_number');

        if (!$last) {
            return 0;
        }

        return (int) substr($last, strlen($prefix));
    }
}
