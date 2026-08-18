<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductionPlanRepositoryInterface
{
    public function paginatePlans(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?ProductionPlan;

    public function findPlanWithRequirements(int $id): ?ProductionPlan;

    public function createPlan(array $data): ProductionPlan;

    public function updatePlan(int $id, array $data): ProductionPlan;

    public function deletePlan(int $id): bool;
}
