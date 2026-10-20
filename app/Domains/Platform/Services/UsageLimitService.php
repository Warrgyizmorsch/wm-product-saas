<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Exceptions\UsageLimitExceededException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class UsageLimitService
{
    /**
     * The tenant's own max_users column always wins when set — it's the
     * per-tenant override an operator uses to grant/restrict a specific
     * tenant regardless of its plan. Only when that's blank do we fall
     * back to the linked plan's catalog limit. Null means unlimited.
     */
    public function maxUsers(Tenant $tenant): ?int
    {
        return $tenant->max_users ?? $tenant->planCatalog?->max_users;
    }

    public function currentUserCount(Tenant $tenant): int
    {
        return User::query()->where('tenant_id', $tenant->id)->count();
    }

    public function remainingUserSlots(Tenant $tenant): ?int
    {
        $max = $this->maxUsers($tenant);

        if ($max === null) {
            return null;
        }

        return max(0, $max - $this->currentUserCount($tenant));
    }

    /**
     * @throws UsageLimitExceededException
     */
    public function assertCanAddUser(Tenant $tenant): void
    {
        $max = $this->maxUsers($tenant);

        if ($max === null) {
            return;
        }

        if ($this->currentUserCount($tenant) >= $max) {
            throw UsageLimitExceededException::forUsers($max);
        }
    }

    /** The plan's cap on a config/plan_limits.php resource, or null when unlimited. */
    public function limit(Tenant $tenant, string $resource): ?int
    {
        $limit = $tenant->planCatalog?->limits[$resource] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    /** Rows of $resource the tenant has now (this month for monthly caps). */
    public function used(Tenant $tenant, string $resource): int
    {
        $config = config("plan_limits.resources.$resource");
        $model = new $config['model'];

        return $config['model']::query()
            ->withoutGlobalScope('tenant')
            ->where($model->getTable().'.tenant_id', $tenant->id)
            ->when(($config['period'] ?? null) === 'month', fn ($q) => $q->whereBetween(
                $model->getTable().'.'.($config['date_column'] ?? 'created_at'),
                [now()->startOfMonth(), now()->endOfMonth()],
            ))
            ->count();
    }

    /**
     * @throws UsageLimitExceededException
     */
    public function assertCanCreate(Tenant $tenant, string $resource): void
    {
        $limit = $this->limit($tenant, $resource);

        if ($limit === null || $this->used($tenant, $resource) < $limit) {
            return;
        }

        $config = config("plan_limits.resources.$resource");

        throw UsageLimitExceededException::forResource(
            mb_strtolower(preg_replace('/ per month$/i', '', $config['label'])),
            $limit,
            ($config['period'] ?? null) === 'month',
        );
    }

    /**
     * Enforce every plan_limits resource on its model's creating event, so
     * screens, imports, API and listeners all hit the same cap.
     */
    public static function guardModels(): void
    {
        foreach (config('plan_limits.resources', []) as $resource => $config) {
            $config['model']::creating(function ($model) use ($resource): void {
                if (self::$suspended > 0) {
                    return;
                }

                $tenantId = $model->tenant_id ?? tenant_id();
                $tenant = $tenantId === null ? null : app(self::class)->tenant((int) $tenantId);

                if ($tenant !== null) {
                    app(self::class)->assertCanCreate($tenant, $resource);
                }
            });
        }
    }

    private static int $suspended = 0;

    /** Run $callback with resource caps off — tenant provisioning, seeders, platform tools. */
    public static function withoutLimits(callable $callback): mixed
    {
        self::$suspended++;

        try {
            return $callback();
        } finally {
            self::$suspended--;
        }
    }

    /** @var array<int, Tenant|null> */
    private array $tenants = [];

    private function tenant(int $tenantId): ?Tenant
    {
        return $this->tenants[$tenantId] ??= Tenant::query()->with('planCatalog')->find($tenantId);
    }

    /**
     * The tenant's usage against every capped resource, for the Subscription page.
     *
     * @return list<array{key: string, label: string, used: int, limit: ?int, percent: ?float}>
     */
    public function resourceUsage(Tenant $tenant): array
    {
        $rows = [];

        foreach (config('plan_limits.resources', []) as $resource => $config) {
            $limit = $this->limit($tenant, $resource);
            $used = $this->used($tenant, $resource);

            $rows[] = [
                'key' => $resource,
                'label' => $config['label'],
                'used' => $used,
                'limit' => $limit,
                'percent' => $limit === null || $limit === 0 ? null : min(100, round(($used / $limit) * 100)),
            ];
        }

        return $rows;
    }

    /**
     * One row per tenant for the platform-wide usage console — the single
     * place an admin checks consumption against plan limits without opening
     * each tenant individually.
     *
     * @return Collection<int, array{tenant: Tenant, plan_name: string, max_users: ?int, used_users: int, remaining_users: ?int, user_percent: ?float, source: string, resources: list<array>, worst_percent: ?float}>
     */
    public function overview(): Collection
    {
        return Tenant::query()
            ->with('planCatalog')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant) {
                $max = $this->maxUsers($tenant);
                $used = $tenant->users_count;
                // One count per capped resource per tenant — fine for an admin console.
                $resources = $this->resourceUsage($tenant);
                $userPercent = $max === null || $max === 0 ? null : min(100, round(($used / $max) * 100));
                $percents = array_filter([$userPercent, ...array_column($resources, 'percent')], fn ($p) => $p !== null);

                return [
                    'resources' => $resources,
                    // Highest share of any cap (users or resources) — drives the row status.
                    'worst_percent' => $percents === [] ? null : max($percents),
                    'tenant' => $tenant,
                    'plan_name' => $tenant->planCatalog?->name ?? ucfirst((string) $tenant->plan),
                    'max_users' => $max,
                    'used_users' => $used,
                    'remaining_users' => $max === null ? null : max(0, $max - $used),
                    'user_percent' => $max === null || $max === 0 ? null : min(100, round(($used / $max) * 100)),
                    'source' => $tenant->max_users !== null ? 'tenant override' : ($tenant->planCatalog !== null ? 'plan' : 'unlimited'),
                ];
            });
    }
}
