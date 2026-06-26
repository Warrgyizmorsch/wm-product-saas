<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Repositories\TenantRepository;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TenantService
{
    public function __construct(
        private readonly TenantRepository $tenants,
    ) {
    }

    public function all(): Collection
    {
        return $this->tenants->all();
    }

    public function summary(): array
    {
        return [
            'total' => $this->tenants->count(),
            'active' => $this->tenants->activeCount(),
        ];
    }

    public function create(array $data): Tenant
    {
        return $this->tenants->create($this->payload($data));
    }

    public function update(Tenant $tenant, array $data): bool
    {
        return $this->tenants->update($tenant, $this->payload($data));
    }

    public function updateStatus(Tenant $tenant, string $status): bool
    {
        return $this->tenants->update($tenant, ['status' => $status]);
    }

    private function payload(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => $data['slug'] ?: Str::slug($data['name']),
            'domain' => $data['domain'] ?: null,
            'status' => $data['status'],
            'plan' => $data['plan'],
            'timezone' => $data['timezone'],
            'locale' => $data['locale'],
            'settings' => [
                'branch' => $data['branch'] ?: 'Main Office',
                'currency' => $data['currency'] ?: 'INR',
                'financial_year' => $data['financial_year'] ?: 'FY '.now()->format('Y'),
            ],
        ];
    }
}
