<?php

namespace App\Policies;

use App\Models\PartyBranch;
use App\Models\User;
use App\Services\Access\AccessService;

class PartyBranchPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user, string $partyType = PartyBranch::PARTY_CUSTOMER): bool
    {
        return $this->allows($user, $partyType, 'view');
    }

    public function view(User $user, PartyBranch $branch): bool
    {
        return $this->allows($user, $branch->party_type, 'view', $branch);
    }

    public function create(User $user, string $partyType = PartyBranch::PARTY_CUSTOMER): bool
    {
        return $this->allows($user, $partyType, 'create');
    }

    public function update(User $user, PartyBranch $branch): bool
    {
        return $this->allows($user, $branch->party_type, 'edit', $branch);
    }

    public function delete(User $user, PartyBranch $branch): bool
    {
        return $this->allows($user, $branch->party_type, 'delete', $branch);
    }

    private function allows(User $user, string $partyType, string $action, ?PartyBranch $branch = null): bool
    {
        $permission = $partyType === PartyBranch::PARTY_VENDOR
            ? "purchase.vendor_branches.{$action}"
            : "crm.customer_branches.{$action}";

        $context = [
            'tenant_id' => $branch?->tenant_id ?? $user->tenant_id,
            'company_id' => $branch?->company_id ?? $user->company_id,
            'branch_id' => $branch?->branch_id ?? $user->branch_id,
        ];

        return $this->access->allows($user, $permission, $context);
    }
}
