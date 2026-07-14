<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Collection;

interface ChartOfAccountRepositoryInterface
{
    public function getAll(array $filters = []): Collection;

    public function find(int $id): ?ChartOfAccount;

    public function findByCode(string $code, int $tenantId, ?int $ignoreId = null): ?ChartOfAccount;

    public function create(array $data): ChartOfAccount;

    public function update(int $id, array $data): ChartOfAccount;

    public function delete(int $id): bool;

    public function getActive(): Collection;

    public function getByType(string $type): Collection;
}
