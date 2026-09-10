<?php

namespace App\Domains\HRMS\Policies;

use App\Domains\HRMS\Models\Company;
use App\Models\User;
use App\Services\Access\AccessService;

class OrgPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'hrms.org.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'hrms.org.manage', ['tenant_id' => $user->tenant_id])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function update(User $user, mixed $company = null): bool
    {
        $tenantId = $company instanceof Company ? $company->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.org.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]);
    }

    public function delete(User $user, mixed $company = null): bool
    {
        $tenantId = $company instanceof Company ? $company->tenant_id : $user->tenant_id;

        return $this->access->allows($user, 'hrms.org.manage', ['tenant_id' => $tenantId])
            || $this->access->allows($user, 'hr.settings.manage', ['tenant_id' => $tenantId]);
    }
}
