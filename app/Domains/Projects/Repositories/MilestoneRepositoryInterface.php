<?php

namespace App\Domains\Projects\Repositories;

use App\Domains\Projects\Models\Milestone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface MilestoneRepositoryInterface
{
    public function getForProject(int $projectId): Collection;

    public function paginateAll(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Milestone;

    public function create(array $data): Milestone;

    public function update(int $id, array $data): Milestone;

    public function delete(int $id): bool;
}
