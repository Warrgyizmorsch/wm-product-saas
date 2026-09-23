<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\DTO\SubscriptionQuote;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\TenantModule;
use App\Http\Middleware\EnsureTenantModuleAccess;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Backs the Zoho-style Plan → Add-ons → Pay → Confirmation checkout: what a
 * tenant may pick, and the server-side quote for that pick. The subscribe
 * step (Razorpay Subscriptions) reuses quoteFor() so the amount charged is
 * always the one computed here, never the client's.
 */
class BillingCheckoutService
{
    /** 15-character Indian GSTIN: state code, PAN, entity number, 'Z', checksum. */
    public const GSTIN_PATTERN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    public function __construct(
        private readonly SubscriptionPricing $pricing,
        private readonly TenantModuleService $modules,
        private readonly UsageLimitService $usage,
    ) {
    }

    /** @return Collection<int, Plan> plans a tenant can subscribe to per user */
    public function plans(): Collection
    {
        return Plan::query()
            ->where('is_active', true)
            ->where('is_demo', false)
            ->where(fn ($q) => $q->whereNotNull('monthly_price_per_user')->orWhereNotNull('yearly_price_per_user'))
            ->orderBy('sort_order')
            ->get();
    }

    /** Fewest users the tenant can buy: everyone who can already log in. */
    public function minimumSeats(Tenant $tenant): int
    {
        return max(1, $this->usage->currentUserCount($tenant));
    }

    /** @return list<string> add-ons bought with the old one-time fee — never billed */
    public function lifetimeModules(Tenant $tenant): array
    {
        return $tenant->addonModules
            ->where('billing', TenantModule::BILLING_LIFETIME)
            ->pluck('module')
            ->values()
            ->all();
    }

    /** @return list<string> recurring add-ons currently installed — pre-selected in the Add-ons step */
    public function recurringModules(Tenant $tenant): array
    {
        return $tenant->addonModules
            ->where('billing', TenantModule::BILLING_RECURRING)
            ->filter(fn (TenantModule $row) => $row->isActive())
            ->pluck('module')
            ->values()
            ->all();
    }

    /**
     * Validates a checkout selection for this tenant and prices it.
     *
     * @param list<string> $modules add-ons picked in the Add-ons step
     *
     * @throws ValidationException
     */
    public function quoteFor(Tenant $tenant, int $planId, string $cycle, int $seats, array $modules): SubscriptionQuote
    {
        $plan = $this->plans()->firstWhere('id', $planId);

        if ($plan === null) {
            throw ValidationException::withMessages(['plan_id' => 'That plan is not available.']);
        }

        if (! in_array($cycle, $this->pricing->cycles(), true)) {
            throw ValidationException::withMessages(['cycle' => 'Choose monthly or yearly billing.']);
        }

        $minimum = $this->minimumSeats($tenant);
        if ($seats < $minimum) {
            throw ValidationException::withMessages([
                'seats' => "Your workspace already has {$minimum} users — buy at least {$minimum}.",
            ]);
        }

        $unknown = array_diff($modules, EnsureTenantModuleAccess::GATED_MODULES);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['modules' => 'Unknown module: '.implode(', ', $unknown).'.']);
        }

        $lifetime = $this->lifetimeModules($tenant);
        $this->assertDependencies($plan, [...$modules, ...$lifetime]);

        try {
            return $this->pricing->quote($plan, $cycle, $seats, $modules, $lifetime);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['modules' => $e->getMessage()]);
        }
    }

    /** @param list<string> $modules everything the tenant would have besides the plan */
    private function assertDependencies(Plan $plan, array $modules): void
    {
        $present = [...($plan->features ?? EnsureTenantModuleAccess::GATED_MODULES), ...$modules];

        foreach (array_unique($modules) as $module) {
            $missing = array_diff($this->modules->requirements($module), $present);

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'modules' => "{$this->modules->label($module)} needs {$this->modules->labels($missing)} — add it too.",
                ]);
            }
        }
    }

    /**
     * Saves who the tax invoice is made out to.
     *
     * @param array{billing_name: string, billing_email: string, billing_gstin?: ?string, billing_address?: ?string, billing_state?: ?string} $details
     */
    public function saveBillingDetails(Tenant $tenant, array $details): void
    {
        if (! empty($details['billing_gstin'])) {
            $details['billing_gstin'] = strtoupper($details['billing_gstin']);
        }

        $tenant->update([
            'billing_name' => $details['billing_name'],
            'billing_email' => $details['billing_email'],
            'billing_gstin' => $details['billing_gstin'] ?? null,
            'billing_address' => $details['billing_address'] ?? null,
            'billing_state' => $details['billing_state'] ?? null,
        ]);
    }

    /** Quote as JSON for the checkout page, with the cycle's display label. */
    public function describe(SubscriptionQuote $quote): array
    {
        return $quote->toArray() + [
            'cycle_label' => config("billing.cycles.{$quote->cycle}.label"),
        ];
    }
}
