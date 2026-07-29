<?php

namespace App\Domains\Inventory\Repositories;

use App\Domains\Inventory\Models\Product;
use App\Domains\Accounting\Models\ChartOfAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    /**
     * Get paginated parent products for main index listing.
     */
    public function getPaginatedProducts(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Product::query()->whereNull('parent_id')->with(['uom', 'variants']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['item_type'])) {
            $query->where('item_type', $filters['item_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['name', 'sku', 'selling_price', 'cost_price', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?Product
    {
        return Product::find($id);
    }

    public function findWithDetails(Product $product): Product
    {
        $product->load([
            'uom', 
            'vendor', 
            'warehouseStocks.warehouse', 
            'variants.warehouseStocks.warehouse',
            'stockTransactions.warehouse',
            'serialNumbers.warehouse',
            'serialNumbers.batch',
            'serialNumbers.transactionIn',
            'serialNumbers.transactionOut',
            'batches.stockTransactions',
            'stockReservations.warehouse'
        ]);

        if ($product->variation_type === 'Variant') {
            $variantIds = $product->variants->pluck('id')->push($product->id);
            $transactions = \App\Domains\Inventory\Models\StockTransaction::with(['warehouse', 'product'])
                ->whereIn('product_id', $variantIds)
                ->latest()
                ->get();
            $product->setRelation('stockTransactions', $transactions);
        }

        return $product;
    }

    public function getAccountsData(): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $allAccounts = ChartOfAccount::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where('is_active', true)
            ->orderBy('code', 'asc')
            ->get();

        $salesAccounts = $allAccounts->where('type', 'income');
        if ($salesAccounts->isEmpty()) {
            $salesAccounts = $allAccounts;
        }

        $purchaseAccounts = $allAccounts->where('type', 'expense');
        if ($purchaseAccounts->isEmpty()) {
            $purchaseAccounts = $allAccounts;
        }

        $inventoryAccounts = $allAccounts->where('type', 'asset');
        if ($inventoryAccounts->isEmpty()) {
            $inventoryAccounts = $allAccounts;
        }

        return [
            'salesAccounts' => $salesAccounts,
            'purchaseAccounts' => $purchaseAccounts,
            'inventoryAccounts' => $inventoryAccounts,
        ];
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): bool
    {
        return $product->update($data);
    }

    public function delete(Product $product): ?bool
    {
        return $product->delete();
    }

    public function count(): int
    {
        return Product::count();
    }
}
