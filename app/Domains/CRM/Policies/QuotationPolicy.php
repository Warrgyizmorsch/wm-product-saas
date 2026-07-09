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
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $this->access->allows($user, 'crm.quotations.view', [
            'tenant_id' => $quotation->tenant_id,
            'owner_id' => $quotation->sales_person_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'crm.quotations.create', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $this->access->allows($user, 'crm.quotations.update', [
            'tenant_id' => $quotation->tenant_id,
            'owner_id' => $quotation->sales_person_id,
        ]);
    }

    public function approve(User $user, Quotation $quotation): bool
    {
        return $this->access->allows($user, 'crm.quotations.approve', [
            'tenant_id' => $quotation->tenant_id,
        ]);
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $this->access->allows($user, 'crm.quotations.delete', [
            'tenant_id' => $quotation->tenant_id,
            'owner_id' => $quotation->sales_person_id,
        ]);
    }
}
