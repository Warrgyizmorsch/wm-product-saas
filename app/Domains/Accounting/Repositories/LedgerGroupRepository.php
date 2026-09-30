<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\LedgerGroup;
use Illuminate\Database\Eloquent\Collection;

class LedgerGroupRepository implements LedgerGroupRepositoryInterface
{
    public function getAll(): Collection
    {
        return LedgerGroup::query()->orderBy('code')->get();
    }

    public function find(int $id): ?LedgerGroup
    {
        return LedgerGroup::find($id);
    }

    public function findByCode(string $code, int $tenantId, ?int $ignoreId = null): ?LedgerGroup
    {
        $query = LedgerGroup::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->first();
    }

    public function create(array $data): LedgerGroup
    {
        return LedgerGroup::create($data);
    }

    public function update(int $id, array $data): LedgerGroup
    {
        $group = LedgerGroup::findOrFail($id);
        $group->update($data);

        return $group->fresh();
    }

    public function delete(int $id): bool
    {
        $group = LedgerGroup::findOrFail($id);

        return (bool) $group->delete();
    }
}
