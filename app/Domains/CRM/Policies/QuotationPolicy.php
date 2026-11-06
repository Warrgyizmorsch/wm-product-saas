<?php

namespace App\Domains\CRM\Policies;

use App\Domains\CRM\Models\Quotation;
use App\Models\User;
use App\Services\Access\AccessService;

class QuotationPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'crm.quotations.view', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        $ownerId = $quotation->sales_person_id
            ?? $quotation->crmDeal?->owner_id
            ?? $quotation->crmDeal?->account?->owner_id
            ?? $quotation->crmAccount?->owner_id
            ?? $quotation->lead?->lead_owner_id;

        return $this->access->allows($user, 'crm.quotations.view', [
            'tenant_id'     => $quotation->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $quotation->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $quotation->company_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'crm.quotations.create', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        $ownerId = $quotation->sales_person_id
            ?? $quotation->crmDeal?->owner_id
            ?? $quotation->crmDeal?->account?->owner_id
            ?? $quotation->crmAccount?->owner_id
            ?? $quotation->lead?->lead_owner_id;

        return $this->access->allows($user, 'crm.quotations.update', [
            'tenant_id'     => $quotation->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $quotation->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $quotation->company_id,
        ]);
    }

    public function approve(User $user, Quotation $quotation): bool
    {
        return $this->access->allows($user, 'crm.quotations.approve', [
            'tenant_id'     => $quotation->tenant_id,
            'branch_id'     => $quotation->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $quotation->company_id,
        ]);
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        $ownerId = $quotation->sales_person_id
            ?? $quotation->crmDeal?->owner_id
            ?? $quotation->crmDeal?->account?->owner_id
            ?? $quotation->crmAccount?->owner_id
            ?? $quotation->lead?->lead_owner_id;

        return $this->access->allows($user, 'crm.quotations.delete', [
            'tenant_id'     => $quotation->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $quotation->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $quotation->company_id,
        ]);
    }
}
