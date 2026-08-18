<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductionPlanRepository implements ProductionPlanRepositoryInterface
{
    public function paginatePlans(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionPlan::with(['product', 'bom', 'routing']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where('plan_number', 'like', $search);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function find(int $id): ?ProductionPlan
    {
        return ProductionPlan::with(['product', 'bom', 'routing'])->find($id);
    }

    public function findPlanWithRequirements(int $id): ?ProductionPlan
    {
        return ProductionPlan::with([
            'product',
            'bom',
            'routing',
            'requirements.material',
            'requirements.uom',
            'operations.workCenter',
            'operations.machine',
        ])->find($id);
    }

    public function createPlan(array $data): ProductionPlan
    {
        return ProductionPlan::create($data);
    }

    public function updatePlan(int $id, array $data): ProductionPlan
    {
        $plan = ProductionPlan::findOrFail($id);
        $plan->update($data);
        return $plan->fresh();
    }

    public function deletePlan(int $id): bool
    {
        $plan = ProductionPlan::findOrFail($id);
        return (bool) $plan->delete();
    }
}
