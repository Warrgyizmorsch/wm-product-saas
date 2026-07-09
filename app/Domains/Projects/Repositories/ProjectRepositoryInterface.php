<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface
{
    public function getAll(array $filters = []): Collection;

    public function find(int $id): ?Project;

    public function create(array $data): Project;

    public function update(int $id, array $data): Project;

    public function delete(int $id): bool;

    public function latestCode(): ?string;

    public function countByStatus(string $status): int;
}
