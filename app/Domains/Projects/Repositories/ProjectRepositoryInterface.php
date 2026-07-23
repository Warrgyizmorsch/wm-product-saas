<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectRepositoryInterface
{
    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function find(int $id): ?Project;

    public function create(array $data): Project;

    public function update(int $id, array $data): Project;

    public function delete(int $id): bool;

    public function latestCode(): ?string;

    public function countAll(): int;

    public function countByStatus(string $status): int;
}
