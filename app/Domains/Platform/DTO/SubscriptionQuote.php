<?php

namespace App\Domains\Platform\DTO;

/**
 * A server-computed price for one billing cycle (see SubscriptionPricing).
 * Every amount is in paise; listed prices exclude GST, which is added on top.
 */
final class SubscriptionQuote
{
    /**
     * @param list<array{key: string, type: string, label: string, price_per_user: int, seats: int, months: int, amount: int}> $lines
     *     price_per_user is whole rupees per user per month; amount is paise for the whole cycle
     */
    public function __construct(
        public readonly int $planId,
        public readonly string $cycle,
        public readonly int $seats,
        public readonly array $lines,
        public readonly int $subtotal,
        public readonly float $gstRate,
        public readonly int $gst,
        public readonly int $total,
        public readonly string $currency,
    ) {
    }

    /** @return list<string> recurring add-on modules on this quote */
    public function addonModules(): array
    {
        return array_values(array_map(
            fn (array $line) => $line['key'],
            array_filter($this->lines, fn (array $line) => $line['type'] === 'addon'),
        ));
    }

    /** Price per seat for the cycle, excluding GST, in paise (the Razorpay plan amount). */
    public function perSeatAmount(): int
    {
        return $this->seats > 0 ? intdiv($this->subtotal, $this->seats) : 0;
    }

    public function toArray(): array
    {
        return [
            'plan_id' => $this->planId,
            'cycle' => $this->cycle,
            'seats' => $this->seats,
            'lines' => $this->lines,
            'subtotal' => $this->subtotal,
            'gst_rate' => $this->gstRate,
            'gst' => $this->gst,
            'total' => $this->total,
            'currency' => $this->currency,
        ];
    }
}
