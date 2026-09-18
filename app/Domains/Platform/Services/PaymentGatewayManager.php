<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Contracts\PaymentGateway;
use App\Domains\Platform\Models\PlatformSetting;
use RuntimeException;

/**
 * Registry + resolver for PaymentGateway implementations. Every gateway the
 * app knows about is registered once (bound in AppServiceProvider); which
 * one is actually used is a stored setting (PlatformSetting), changeable
 * from the Payment Gateway settings screen — never a code change.
 */
class PaymentGatewayManager
{
    private const ACTIVE_GATEWAY_SETTING_KEY = 'active_payment_gateway';

    /** @var array<string, PaymentGateway> keyed by identifier() */
    private array $gateways = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->identifier()] = $gateway;
    }

    /** @return list<PaymentGateway> */
    public function all(): array
    {
        return array_values($this->gateways);
    }

    public function find(string $identifier): ?PaymentGateway
    {
        return $this->gateways[$identifier] ?? null;
    }

    public function activeIdentifier(): ?string
    {
        return PlatformSetting::get(self::ACTIVE_GATEWAY_SETTING_KEY);
    }

    /**
     * @throws RuntimeException if no gateway is set active, or the active one isn't registered/configured
     */
    public function active(): PaymentGateway
    {
        $identifier = $this->activeIdentifier();

        if ($identifier === null) {
            throw new RuntimeException('No payment gateway is configured as active. Set one under Tenant Console → Payment Gateway.');
        }

        $gateway = $this->find($identifier);

        if ($gateway === null) {
            throw new RuntimeException("The active payment gateway ({$identifier}) is not registered.");
        }

        if (! $gateway->isConfigured()) {
            throw new RuntimeException("The active payment gateway ({$gateway->label()}) is missing required credentials.");
        }

        return $gateway;
    }

    /**
     * @throws RuntimeException if the gateway isn't registered or isn't configured
     */
    public function setActive(string $identifier): void
    {
        $gateway = $this->find($identifier);

        if ($gateway === null) {
            throw new RuntimeException("Unknown payment gateway: {$identifier}");
        }

        if (! $gateway->isConfigured()) {
            throw new RuntimeException("{$gateway->label()} is missing required credentials — add them to .env before activating it.");
        }

        PlatformSetting::set(self::ACTIVE_GATEWAY_SETTING_KEY, $identifier);
    }

    public function resolveByIdentifier(string $identifier): ?PaymentGateway
    {
        return $this->find($identifier);
    }
}
