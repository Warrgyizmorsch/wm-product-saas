<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    /**
     * Same filtered, sorted query as getAll(), without pagination — for
     * consumers (e.g. exports) that need every matching row. Sort order,
     * including tiebreaking, is guaranteed identical to getAll() for the
     * same $filters.
     */
    public function getQuery(array $filters = []): Builder;

    public function find(int $id): ?Project;

    public function create(array $data): Project;

    public function update(int $id, array $data): Project;

    public function delete(int $id): bool;

    public function latestCode(): ?string;

    public function countAll(): int;

    public function countByStatus(string $status): int;
}
