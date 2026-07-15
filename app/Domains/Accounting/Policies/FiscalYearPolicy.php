<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\FiscalYear;
use App\Models\User;
use App\Services\Access\AccessService;

class FiscalYearPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'accounting.fiscal_years.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->access->allows($user, 'accounting.fiscal_years.view', [
            'tenant_id' => $fiscalYear->tenant_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'accounting.fiscal_years.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    /**
     * Closing a fiscal year locks every period in it against new postings —
     * kept separate from create() since it's a much higher-stakes action.
     */
    public function close(User $user, FiscalYear $fiscalYear): bool
    {
        return $this->access->allows($user, 'accounting.fiscal_years.close', [
            'tenant_id' => $fiscalYear->tenant_id,
        ]);
    }
}
