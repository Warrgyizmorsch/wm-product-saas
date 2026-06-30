<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\Routing;
use Illuminate\Database\Eloquent\Collection;

interface RoutingRepositoryInterface
{
    public function getAll(array $filters = []): Collection;

    public function find(int $id): ?Routing;

    public function getRoutingWithOperations(int $id): ?Routing;

    public function create(array $data): Routing;

    public function update(int $id, array $data): Routing;

    public function delete(int $id): bool;

    public function getActiveRouting(int $productId): ?Routing;

    public function getPrimaryActiveRouting(int $productId): ?Routing;

    public function findByRoutingNumber(string $number, int $tenantId, ?int $ignoreId = null): ?Routing;

    public function getLastSequenceNumber(int $tenantId): int;
}
