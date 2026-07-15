<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Collection;

class ChartOfAccountRepository implements ChartOfAccountRepositoryInterface
{
    public function getAll(array $filters = []): Collection
    {
        $query = ChartOfAccount::query();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('code')->get();
    }

    public function find(int $id): ?ChartOfAccount
    {
        return ChartOfAccount::find($id);
    }

    public function findByCode(string $code, int $tenantId, ?int $ignoreId = null): ?ChartOfAccount
    {
        $query = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->first();
    }

    public function create(array $data): ChartOfAccount
    {
        return ChartOfAccount::create($data);
    }

    public function update(int $id, array $data): ChartOfAccount
    {
        $account = ChartOfAccount::findOrFail($id);
        $account->update($data);

        return $account->fresh();
    }

    public function delete(int $id): bool
    {
        $account = ChartOfAccount::findOrFail($id);

        return (bool) $account->delete();
    }

    public function getActive(): Collection
    {
        return ChartOfAccount::active()->orderBy('code')->get();
    }

    public function getByType(string $type): Collection
    {
        return ChartOfAccount::ofType($type)->orderBy('code')->get();
    }
}
