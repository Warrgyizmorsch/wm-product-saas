<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\LedgerGroup;
use Illuminate\Database\Eloquent\Collection;

interface LedgerGroupRepositoryInterface
{
    public function getAll(): Collection;

    public function find(int $id): ?LedgerGroup;

    public function findByCode(string $code, int $tenantId, ?int $ignoreId = null): ?LedgerGroup;

    public function create(array $data): LedgerGroup;

    public function update(int $id, array $data): LedgerGroup;

    public function delete(int $id): bool;
}
