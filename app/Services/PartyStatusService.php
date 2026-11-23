<?php

namespace App\Services;

use App\Domains\CRM\Models\Customer;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Database\Eloquent\Model;

/**
 * Making a verified customer or vendor inactive, and bringing it back
 * (FRS 2.3, task C-16).
 *
 *  - Anyone who may edit the party can make it inactive, but must give a reason.
 *  - A party stopped for a RISK reason (credit, fraud, compliance) can only be
 *    reactivated by a different user who holds the reactivate permission.
 *  - Any other inactive party is reactivated by anyone who may edit it, as before.
 *  - An unverified party is not handled here: only its verifier can activate it.
 */
class PartyStatusService
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('party_verification.enabled', true);
    }

    /** @return array<string, array{label: string, risk: bool}> */
    public function reasons(): array
    {
        return config('party_verification.inactive_reasons', []);
    }

    public function reasonLabel(?string $reason): string
    {
        return $this->reasons()[$reason]['label'] ?? ($reason ? ucfirst(str_replace('_', ' ', $reason)) : 'Not recorded');
    }

    public function isActive(Model $party): bool
    {
        return strtolower((string) $party->status) === 'active';
    }

    /** Inactive for a risk reason: only a different, authorised user may reactivate it. */
    public function isRiskLocked(Model $party): bool
    {
        return $this->enabled()
            && $party->isVerified()
            && ! $this->isActive($party)
            && $party::isRiskReason($party->inactive_reason);
    }

    public function permissionFor(Model $party): string
    {
        return $party instanceof Customer ? 'crm.customers.reactivate' : 'purchase.vendors.reactivate';
    }

    public function canReactivateRisk(?User $user, Model $party): bool
    {
        if (! $user) {
            return false;
        }

        return $this->access->allows($user, $this->permissionFor($party), [
            'tenant_id' => $party->tenant_id,
            'company_id' => $party->company_id ?? null,
            'branch_id' => $party->branch_id ?? null,
        ]);
    }

    /** Why this user cannot reactivate the party, or null if they can. */
    public function reactivationBlockedReason(?User $user, Model $party): ?string
    {
        if (method_exists($party, 'isMerged') && $party->isMerged()) {
            return "This party was permanently merged into record #{$party->merged_into_id} and cannot be reactivated.";
        }

        if (! $party->isVerified()) {
            return 'This party is waiting for verification. Only its verifier can activate it.';
        }

        if ($this->isActive($party)) {
            return 'This party is already active.';
        }

        if (! $this->isRiskLocked($party)) {
            return null;
        }

        if (! $this->canReactivateRisk($user, $party)) {
            return 'This party was made inactive for a risk reason. Only a user with the reactivate permission can reactivate it.';
        }

        if ($party->inactivated_by !== null && (int) $party->inactivated_by === (int) $user?->id) {
            return 'You made this party inactive, so a different authorised person must reactivate it.';
        }

        return null;
    }

    /** @return list<string> Reason keys that also block vendor payments (e.g. fraud, compliance). */
    public function paymentBlockingReasons(): array
    {
        $blocked = [];
        foreach ($this->reasons() as $key => $meta) {
            if (! empty($meta['blocks_payments'])) {
                $blocked[] = $key;
            }
        }

        return $blocked;
    }

    public function blocksPayments(?string $reason): bool
    {
        return $reason !== null && $reason !== ''
            && (bool) ($this->reasons()[$reason]['blocks_payments'] ?? false);
    }

    /**
     * Inactive but verified vendors that we still owe money (an open bill, or a payable
     * opening balance), excluding vendors stopped for payment-blocking reasons
     * (Suspected fraud, Compliance issue). They stay selectable on the payment screen,
     * with a warning, so an existing debt can still be settled.
     *
     * @return list<int>
     */
    public function inactiveVendorIdsWithDues(?int $tenantId): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $paymentBlocked = $this->paymentBlockingReasons();

        return \App\Domains\Inventory\Models\Vendor::query()
            ->withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereRaw("lower(status) <> 'active'")
            ->where('verification_status', 'verified')
            ->when($paymentBlocked !== [], function ($q) use ($paymentBlocked) {
                $q->where(function ($sub) use ($paymentBlocked) {
                    $sub->whereNull('inactive_reason')
                        ->orWhereNotIn('inactive_reason', $paymentBlocked);
                });
            })
            ->where(function ($q) {
                $q->whereIn('id', \App\Domains\Purchase\Models\VendorBill::query()
                    ->withoutGlobalScopes()
                    ->whereIn('status', ['posted', 'unpaid', 'partially_paid', 'approved', 'Posted', 'Unpaid', 'Partially Paid', 'Approved'])
                    ->where('due_amount', '>', 0.01)
                    ->select('vendor_id'))
                  ->orWhere(function ($q2) {
                      $q2->where('opening_balance', '>', 0)->where('opening_balance_type', 'credit');
                  });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function deactivate(Model $party, User $user, string $reason, ?string $note = null): Model
    {
        if (! $party->isVerified()) {
            throw new PartyStatusException('This party is waiting for verification and is already inactive.');
        }

        if (! $this->isActive($party)) {
            throw new PartyStatusException('This party is already inactive.');
        }

        if (! array_key_exists($reason, $this->reasons())) {
            throw new PartyStatusException('Choose a reason.');
        }

        if ($reason === 'other' && trim((string) $note) === '') {
            throw new PartyStatusException('Describe the reason when you choose "Other".');
        }

        $party::$statusChangeNote = $note;
        try {
            $party->forceFill([
                'status' => 'inactive',
                'inactive_reason' => $reason,
                'inactive_note' => $note,
                'inactivated_by' => $user->id,
                'inactivated_at' => now(),
            ])->save();
        } finally {
            $party::$statusChangeNote = null;
        }

        return $party->refresh();
    }

    public function reactivate(Model $party, User $user, ?string $note = null): Model
    {
        if ($blocked = $this->reactivationBlockedReason($user, $party)) {
            throw new PartyStatusException($blocked);
        }

        $party::$statusChangeNote = $note;
        $party::$riskReactivationApproved = true;
        try {
            $party->forceFill(['status' => 'active'])->save();
        } finally {
            $party::$riskReactivationApproved = false;
            $party::$statusChangeNote = null;
        }

        return $party->refresh();
    }
}
