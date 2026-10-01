<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\Journal;
use App\Models\User;
use App\Services\Access\AccessService;

class JournalPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return $this->access->allows($user, 'accounting.journals.view', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, Journal $journal): bool
    {
        return $this->access->allows($user, 'accounting.journals.view', [
            'tenant_id' => $journal->tenant_id,
        ]);
    }

    /**
     * There's no draft/create-then-post split in the ledger yet (JournalService::post()
     * both builds and posts in one step), so "post" is the create ability.
     */
    public function post(User $user): bool
    {
        return $this->access->allows($user, 'accounting.journals.post', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    /**
     * Maker-checker: approve or reject someone else's pending manual journal
     * or voucher. The "not your own entry" rule is enforced in
     * JournalService::approve(), since it holds whatever the permission says.
     */
    public function approve(User $user, ?Journal $journal = null): bool
    {
        return $this->access->allows($user, 'accounting.journals.approve', [
            'tenant_id' => $journal?->tenant_id ?? $user->tenant_id,
        ]);
    }

    /** Turn maker-checker on/off and set its threshold for the tenant. */
    public function configureApprovals(User $user): bool
    {
        return $this->access->allows($user, 'accounting.approvals.configure', [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function reverse(User $user, Journal $journal): bool
    {
        return $this->access->allows($user, 'accounting.journals.reverse', [
            'tenant_id' => $journal->tenant_id,
        ]);
    }
}
