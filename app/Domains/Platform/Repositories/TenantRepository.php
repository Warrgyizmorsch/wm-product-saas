<?php

namespace App\Domains\Platform\Repositories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

class TenantRepository
{
    public function all(): Collection
    {
        return Tenant::query()
            ->latest()
            ->get();
    }

    public function count(): int
    {
        return Tenant::query()->count();
    }

    public function activeCount(): int
    {
        return Tenant::query()
            ->where('status', 'active')
            ->count();
    }

    public function create(array $data): Tenant
    {
        return Tenant::query()->create($data);
    }

    public function update(Tenant $tenant, array $data): bool
    {
        return $tenant->update($data);
    }
}
