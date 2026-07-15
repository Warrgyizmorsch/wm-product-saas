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

    public function reverse(User $user, Journal $journal): bool
    {
        return $this->access->allows($user, 'accounting.journals.reverse', [
            'tenant_id' => $journal->tenant_id,
        ]);
    }
}
