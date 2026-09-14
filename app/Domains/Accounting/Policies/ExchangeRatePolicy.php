<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\ExchangeRate;
use App\Models\User;
use App\Services\Access\AccessService;

class ExchangeRatePolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'accounting.exchange_rates.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, ExchangeRate $exchangeRate): bool
    {
        return $this->access->allows($user, 'accounting.exchange_rates.view', [
            'tenant_id' => $exchangeRate->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'accounting.exchange_rates.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, ExchangeRate $exchangeRate): bool
    {
        return $this->access->allows($user, 'accounting.exchange_rates.update', [
            'tenant_id' => $exchangeRate->tenant_id,
        ]);
    }

    public function delete(User $user, ExchangeRate $exchangeRate): bool
    {
        return $this->access->allows($user, 'accounting.exchange_rates.delete', [
            'tenant_id' => $exchangeRate->tenant_id,
        ]);
    }

    /**
     * Configure auto-sync and trigger a sync on demand.
     */
    public function sync(User $user): bool
    {
        return $this->access->allows($user, 'accounting.exchange_rates.sync', [
            'tenant_id' => $user->tenant_id,
        ]);
    }
}
