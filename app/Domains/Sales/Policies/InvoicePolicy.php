<?php

namespace App\Domains\Sales\Policies;

use App\Domains\Sales\Models\Invoice;
use App\Models\User;
use App\Services\Access\AccessService;

class InvoicePolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'sales.invoices.view', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        $ownerId = $invoice->salesOrder?->sales_person_id
            ?? $invoice->customer?->crmAccount?->owner_id;

        return $this->access->allows($user, 'sales.invoices.view', [
            'tenant_id'     => $invoice->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $invoice->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $invoice->company_id,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->access->allows($user, 'sales.invoices.create', [
            'tenant_id'     => $user->tenant_id,
            'owner_id'      => $user->id,
            'branch_id'     => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $user->company_id,
        ]);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        $ownerId = $invoice->salesOrder?->sales_person_id
            ?? $invoice->customer?->crmAccount?->owner_id;

        return $this->access->allows($user, 'sales.invoices.create', [
            'tenant_id'     => $invoice->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $invoice->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $invoice->company_id,
        ]);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        $ownerId = $invoice->salesOrder?->sales_person_id
            ?? $invoice->customer?->crmAccount?->owner_id;

        return $this->access->allows($user, 'sales.invoices.send', [
            'tenant_id'     => $invoice->tenant_id,
            'owner_id'      => $ownerId,
            'branch_id'     => $invoice->branch_id,
            'department_id' => $user->department_id,
            'company_id'    => $invoice->company_id,
        ]);
    }
}
