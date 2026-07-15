<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\TaxRate;
use App\Models\User;
use App\Services\Access\AccessService;

class TaxRatePolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'accounting.tax_rates.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, TaxRate $taxRate): bool
    {
        return $this->access->allows($user, 'accounting.tax_rates.view', [
            'tenant_id' => $taxRate->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'accounting.tax_rates.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return $this->access->allows($user, 'accounting.tax_rates.update', [
            'tenant_id' => $taxRate->tenant_id,
        ]);
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $this->access->allows($user, 'accounting.tax_rates.delete', [
            'tenant_id' => $taxRate->tenant_id,
        ]);
    }
}
