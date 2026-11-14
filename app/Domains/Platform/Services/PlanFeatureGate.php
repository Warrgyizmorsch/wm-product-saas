<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\Plan;
use App\Models\Tenant;

/**
 * Zoho-style feature-level plan gating. A plan lists the features it leaves
 * out (plans.excluded_features, keys from config/plan_features.php); for its
 * tenants those features turn view-only — AccessService::allows() asks this
 * gate first, so every policy, controller check and sidebar entry follows it.
 *
 * Bound scoped (one instance per request / queued job) so a plan change is
 * picked up on the next request without any cache to clear.
 */
class PlanFeatureGate
{
    /** @var array<string, string>|null permission prefix => feature key */
    private ?array $prefixes = null;

    /** @var array<int, list<string>> tenant id => excluded feature keys */
    private array $excluded = [];

    /** The feature behind the most recent denial, while that denial was the plan's doing. */
    private ?string $lastBlocked = null;

    /**
     * Whether $tenantId's plan lets this permission through. Permissions the
     * catalog doesn't cover, and view actions, always pass.
     */
    public function allows(string $permissionName, ?int $tenantId): bool
    {
        if ($tenantId === null) {
            return true;
        }

        $excluded = $this->excludedFor($tenantId);
        if ($excluded === []) {
            return true;
        }

        $feature = $this->featureFor($permissionName);
        if ($feature === null || ! in_array($feature, $excluded, true)) {
            return true;
        }

        $action = substr($permissionName, strrpos($permissionName, '.') + 1);

        return in_array($action, config('plan_features.view_only_actions', ['view']), true);
    }

    /** The catalog feature a permission belongs to (longest matching prefix), or null. */
    public function featureFor(string $permissionName): ?string
    {
        $prefixes = $this->prefixes();
        $candidate = $permissionName;

        while (($cut = strrpos($candidate, '.')) !== false) {
            $candidate = substr($candidate, 0, $cut);
            if (isset($prefixes[$candidate])) {
                return $prefixes[$candidate];
            }
        }

        return null;
    }

    /** @return list<string> */
    public function excludedFor(int $tenantId): array
    {
        if (! array_key_exists($tenantId, $this->excluded)) {
            $plan = Tenant::query()->with('planCatalog')->find($tenantId)?->planCatalog;
            $this->excluded[$tenantId] = $plan === null ? [] : $this->excludedForPlan($plan);
        }

        return $this->excluded[$tenantId];
    }

    /** @return list<string> catalog keys only — a key since removed from the catalog no longer blocks anything */
    public function excludedForPlan(Plan $plan): array
    {
        return array_values(array_intersect($plan->excluded_features ?? [], $this->keys()));
    }

    public function includes(Plan $plan, string $feature): bool
    {
        return ! in_array($feature, $plan->excluded_features ?? [], true);
    }

    /** @return array<string, array{label: string, features: array<string, array{label: string, covers?: list<string>}>}> */
    public function catalog(): array
    {
        return config('plan_features.groups', []);
    }

    /** @return list<string> every feature key */
    public function keys(): array
    {
        return collect($this->catalog())->flatMap(fn (array $group) => array_keys($group['features']))->values()->all();
    }

    public function label(string $feature): ?string
    {
        foreach ($this->catalog() as $group) {
            if (isset($group['features'][$feature])) {
                return $group['features'][$feature]['label'];
            }
        }

        return null;
    }

    /** Called by AccessService after every denial: the feature if the plan blocked it, else null. */
    public function noteDenial(?string $feature): void
    {
        $this->lastBlocked = $feature;
    }

    /** For the 403 page: the feature to upsell when the request was refused by the plan. */
    public function lastBlocked(): ?string
    {
        return $this->lastBlocked;
    }

    /** @return array<string, string> */
    private function prefixes(): array
    {
        if ($this->prefixes === null) {
            $this->prefixes = [];
            foreach ($this->catalog() as $group) {
                foreach ($group['features'] as $key => $feature) {
                    $this->prefixes[$key] = $key;
                    foreach ($feature['covers'] ?? [] as $prefix) {
                        $this->prefixes[$prefix] = $key;
                    }
                }
            }
        }

        return $this->prefixes;
    }
}
