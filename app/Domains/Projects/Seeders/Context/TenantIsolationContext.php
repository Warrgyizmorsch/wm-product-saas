<?php

namespace App\Domains\Projects\Seeders\Context;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantIsolationContext
{
    public readonly int $tenantId;
    public readonly string $tenantSlug;
    public readonly string $tenantName;

    public function __construct(Tenant $tenant)
    {
        $this->tenantId = $tenant->id;
        $this->tenantSlug = $tenant->slug ?? 'default';
        $this->tenantName = $tenant->name ?? 'Demo Tenant';
    }

    public static function resolve(?string $tenantParam = null): self
    {
        if ($tenantParam) {
            $tenant = Tenant::query()
                ->where('id', $tenantParam)
                ->orWhere('slug', $tenantParam)
                ->first();

            if (!$tenant) {
                throw new \InvalidArgumentException("Tenant with ID or slug '{$tenantParam}' not found.");
            }

            return new self($tenant);
        }

        $fallbackSlug = config('tenancy.local_fallback_slug') ?: 'warrgyizmorsch';
        $tenant = Tenant::query()->where('slug', $fallbackSlug)->first()
            ?? Tenant::query()->first();

        if (!$tenant) {
            $tenant = Tenant::query()->create([
                'slug' => $fallbackSlug,
                'name' => 'Demo Tenant',
                'status' => Tenant::STATUS_ACTIVE ?? 'Active',
                'plan' => Tenant::PLAN_ENTERPRISE ?? 'Enterprise',
                'subscription_status' => Tenant::SUBSCRIPTION_ACTIVE ?? 'Active',
                'max_users' => 100,
                'max_storage_mb' => 10240,
                'plan_started_at' => now(),
                'timezone' => 'Asia/Kolkata',
                'locale' => 'en',
                'settings' => [],
            ]);
        }

        return new self($tenant);
    }
}
