<?php

namespace App\Domains\Accounting\Policies;

use App\Domains\Accounting\Models\Journal;
use App\Models\User;
use App\Services\Access\AccessService;

/**
 * Deliberately NOT registered via Gate::policy() — Journal::class already has
 * JournalPolicy registered there, and Laravel only allows one policy class per
 * model. VoucherController injects this directly and calls its methods rather
 * than going through $this->authorize()/@can.
 */
class VoucherPolicy
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function viewAny(User $user, string $type): bool
    {
        return $this->access->allows($user, "accounting.vouchers.{$type}.view", [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function view(User $user, Journal $journal, string $type): bool
    {
        return $this->access->allows($user, "accounting.vouchers.{$type}.view", [
            'tenant_id' => $journal->tenant_id,
        ]);
    }

    public function post(User $user, string $type): bool
    {
        return $this->access->allows($user, "accounting.vouchers.{$type}.post", [
            'tenant_id' => $user->tenant_id,
        ]);
    }

    public function reverse(User $user, Journal $journal): bool
    {
        return $this->access->allows($user, "accounting.vouchers.{$journal->voucher_type}.reverse", [
            'tenant_id' => $journal->tenant_id,
        ]);
    }
}
