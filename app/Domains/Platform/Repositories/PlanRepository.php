<?php

namespace App\Domains\Platform\Repositories;

use App\Domains\Platform\Models\Plan;
use Illuminate\Database\Eloquent\Collection;

class PlanRepository
{
    public function all(): Collection
    {
        return Plan::query()
            ->withCount('tenants')
            ->orderBy('sort_order')
            ->get();
    }

    public function activeCount(): int
    {
        return Plan::query()->where('is_active', true)->count();
    }

    public function create(array $data): Plan
    {
        return Plan::query()->create($data);
    }

    public function update(Plan $plan, array $data): bool
    {
        return $plan->update($data);
    }
}
