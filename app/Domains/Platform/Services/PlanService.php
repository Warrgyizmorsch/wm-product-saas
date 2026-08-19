<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Repositories\PlanRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class PlanService
{
    public function __construct(
        private readonly PlanRepository $plans,
    ) {
    }

    public function all(): Collection
    {
        return $this->plans->all();
    }

    public function summary(): array
    {
        return [
            'total' => $this->plans->all()->count(),
            'active' => $this->plans->activeCount(),
        ];
    }

    public function create(array $data): Plan
    {
        return $this->plans->create($this->payload($data));
    }

    public function update(Plan $plan, array $data): bool
    {
        return $this->plans->update($plan, $this->payload($data));
    }

    private function payload(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => ($data['slug'] ?? null) ?: Str::slug($data['name']),
            'description' => ($data['description'] ?? null) ?: null,
            'price' => ($data['price'] ?? null) ?: 0,
            'currency' => ($data['currency'] ?? null) ?: 'INR',
            'billing_cycle' => ($data['billing_cycle'] ?? null) ?: 'monthly',
            'max_users' => ($data['max_users'] ?? null) ?: null,
            'max_storage_mb' => ($data['max_storage_mb'] ?? null) ?: null,
            'trial_days' => ($data['trial_days'] ?? null) ?: null,
            'features' => $data['features'] ?? [],
            'is_demo' => (bool) ($data['is_demo'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => ($data['sort_order'] ?? null) ?: 0,
        ];
    }
}
