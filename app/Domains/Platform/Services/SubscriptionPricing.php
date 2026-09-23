<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\DTO\SubscriptionQuote;
use App\Domains\Platform\Models\ModulePrice;
use App\Domains\Platform\Models\Plan;
use App\Http\Middleware\EnsureTenantModuleAccess;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Zoho-style per-user pricing: (plan + each recurring add-on) per user per
 * month × seats × months in the cycle, GST added on top. The one place a
 * subscription amount is computed — never trusted from the client.
 */
class SubscriptionPricing
{
    /** @var Collection<string, ModulePrice>|null */
    private ?Collection $modulePrices = null;

    /** @return list<string> */
    public function cycles(): array
    {
        return array_keys(config('billing.cycles'));
    }

    public function months(string $cycle): int
    {
        $months = config("billing.cycles.$cycle.months");

        if ($months === null) {
            throw new InvalidArgumentException("Unknown billing cycle: {$cycle}");
        }

        return (int) $months;
    }

    /**
     * Whole rupees per user per month for $plan on $cycle, or null when not sold
     * on it. A plan is a bundle of modules: its price is the admin's override
     * when set, else the sum of its modules' prices on that cycle.
     */
    public function planPricePerUser(Plan $plan, string $cycle): ?int
    {
        $override = $cycle === 'yearly' ? $plan->yearly_price_per_user : $plan->monthly_price_per_user;

        return $override ?? $this->bundlePricePerUser($plan, $cycle);
    }

    /**
     * Sum of the plan's modules' prices on $cycle, or null when any of them has
     * no price on it (the bundle can't be priced). Uses the price even when the
     * module isn't for sale as an add-on — that flag only governs add-ons.
     */
    public function bundlePricePerUser(Plan $plan, string $cycle): ?int
    {
        $modules = $plan->features ?? EnsureTenantModuleAccess::GATED_MODULES;

        if ($modules === []) {
            return null;
        }

        $total = 0;
        foreach ($modules as $module) {
            $price = $this->modulePrices()->get($module)?->pricePerUser($cycle);

            if ($price === null) {
                return null;
            }

            $total += $price;
        }

        return $total;
    }

    /** True when the plan can be bought per user on at least one cycle. */
    public function sellsPerUser(Plan $plan): bool
    {
        foreach ($this->cycles() as $cycle) {
            if ($this->planPricePerUser($plan, $cycle) !== null) {
                return true;
            }
        }

        return false;
    }

    /** Whole rupees per user per month for an add-on module on $cycle, or null when not for sale. */
    public function modulePricePerUser(string $module, string $cycle): ?int
    {
        $price = $this->modulePrices()->get($module);

        return $price !== null && $price->is_active ? $price->pricePerUser($cycle) : null;
    }

    /**
     * @param list<string> $addonModules recurring add-ons to bill; modules the plan
     *     already includes are skipped, so are $freeModules (lifetime add-ons)
     * @param list<string> $freeModules
     *
     * @throws InvalidArgumentException when the plan or an add-on isn't sold on this cycle
     */
    public function quote(Plan $plan, string $cycle, int $seats, array $addonModules = [], array $freeModules = []): SubscriptionQuote
    {
        $months = $this->months($cycle);

        if ($seats < 1) {
            throw new InvalidArgumentException('At least one user is required.');
        }

        $planPrice = $this->planPricePerUser($plan, $cycle);

        if ($planPrice === null) {
            throw new InvalidArgumentException("{$plan->name} isn't available on {$cycle} billing.");
        }

        $lines = [$this->line('plan', $plan->slug, $plan->name, $planPrice, $seats, $months)];

        $included = $plan->features;
        foreach (array_values(array_unique($addonModules)) as $module) {
            if ($included === null || in_array($module, $included, true) || in_array($module, $freeModules, true)) {
                continue;
            }

            $price = $this->modulePricePerUser($module, $cycle);

            if ($price === null) {
                throw new InvalidArgumentException(config("navigation.apps.$module.label", ucfirst($module))." isn't sold as an add-on on {$cycle} billing.");
            }

            $lines[] = $this->line('addon', $module, config("navigation.apps.$module.label", ucfirst($module)), $price, $seats, $months);
        }

        $subtotal = array_sum(array_column($lines, 'amount'));

        return $this->withGst($plan->id, $cycle, $seats, $lines, $subtotal);
    }

    /**
     * What an upgrade costs right now for the rest of the current period
     * (Zoho-style proration): the cycle-price difference × remaining share of
     * the period, GST on top. Downgrades cost nothing now — they take effect
     * at renewal — so this never goes below zero.
     */
    public function prorate(SubscriptionQuote $current, SubscriptionQuote $new, CarbonInterface $periodStart, CarbonInterface $periodEnd, CarbonInterface $now): int
    {
        $delta = $new->subtotal - $current->subtotal;
        $periodSeconds = $periodEnd->getTimestamp() - $periodStart->getTimestamp();
        $remainingSeconds = $periodEnd->getTimestamp() - $now->getTimestamp();

        if ($delta <= 0 || $periodSeconds <= 0 || $remainingSeconds <= 0) {
            return 0;
        }

        $share = min(1, $remainingSeconds / $periodSeconds);
        $net = (int) round($delta * $share);

        return $net + $this->gstOn($net);
    }

    public function gstOn(int $amount): int
    {
        return (int) round($amount * $this->gstRate() / 100);
    }

    public function gstRate(): float
    {
        return (float) config('billing.gst_rate');
    }

    private function line(string $type, string $key, string $label, int $pricePerUser, int $seats, int $months): array
    {
        return [
            'key' => $key,
            'type' => $type,
            'label' => $label,
            'price_per_user' => $pricePerUser,
            'seats' => $seats,
            'months' => $months,
            'amount' => $pricePerUser * 100 * $seats * $months,
        ];
    }

    private function withGst(int $planId, string $cycle, int $seats, array $lines, int $subtotal): SubscriptionQuote
    {
        $gst = $this->gstOn($subtotal);

        return new SubscriptionQuote(
            planId: $planId,
            cycle: $cycle,
            seats: $seats,
            lines: $lines,
            subtotal: $subtotal,
            gstRate: $this->gstRate(),
            gst: $gst,
            total: $subtotal + $gst,
            currency: config('billing.currency'),
        );
    }

    /** @return Collection<string, ModulePrice> */
    private function modulePrices(): Collection
    {
        return $this->modulePrices ??= ModulePrice::query()->get()->keyBy('module');
    }
}
