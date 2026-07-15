<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Models\User;
use App\Services\Access\AccessService;

class ChartOfAccountPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'accounting.chart_of_accounts.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, ChartOfAccount $account): bool
    {
        return $this->access->allows($user, 'accounting.chart_of_accounts.view', [
            'tenant_id' => $account->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'accounting.chart_of_accounts.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, ChartOfAccount $account): bool
    {
        return $this->access->allows($user, 'accounting.chart_of_accounts.update', [
            'tenant_id' => $account->tenant_id,
        ]);
    }

    public function delete(User $user, ChartOfAccount $account): bool
    {
        return $this->access->allows($user, 'accounting.chart_of_accounts.delete', [
            'tenant_id' => $account->tenant_id,
        ]);
    }
}
