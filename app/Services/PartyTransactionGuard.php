<?php

namespace App\Services;

use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Models\PartyBranch;
use Illuminate\Validation\ValidationException;

/**
 * Refuses NEW documents for a customer or vendor that is inactive or not yet
 * verified (FRS 2.3, task C-16: "holds block transactions").
 *
 * Only documents that start new business are guarded (see GuardsPartyTransactions
 * and the models that use it). Documents that settle existing business, such as
 * invoices, bills, receipts, returns and payments, are deliberately not blocked.
 */
class PartyTransactionGuard
{
    public function enabled(): bool
    {
        return (bool) config('party_verification.enabled', true)
            && (bool) config('party_verification.transaction_block_enabled', true);
    }

    /**
     * @param  'customer'|'vendor'  $type
     * @return string|null  the reason the party cannot be used, or null if it can
     */
    public function blockedReason(string $type, int|string|null $partyId, ?int $tenantId, string $documentLabel): ?string
    {
        if (! $this->enabled() || ! $partyId) {
            return null;
        }

        $model = $type === 'customer' ? Customer::class : Vendor::class;

        // Bypass the global scopes (tenant and ownership): the guard must not
        // fail open just because the current user cannot "see" the party.
        $party = $model::query()
            ->withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->find($partyId);

        if (! $party) {
            return null; // let the existing foreign-key / exists validation report it
        }

        $name = $party->name ?: ucfirst($type) . ' #' . $party->getKey();
        $label = ucfirst($type);

        if ($party->isMerged()) {
            $targetSuffix = $party->merged_into_id ? " (#{$party->merged_into_id})" : '';
            return "{$label} '{$name}' has been merged into another record{$targetSuffix}, so it cannot be used for a new {$documentLabel}.";
        }

        if (! $party->isVerified()) {
            return "{$label} '{$name}' is waiting for verification, so it cannot be used for a new {$documentLabel} yet.";
        }

        if (strtolower((string) $party->status) !== 'active') {
            $reason = app(PartyStatusService::class)->reasonLabel($party->inactive_reason);
            $suffix = $party->inactive_reason ? " ({$reason})" : '';

            return "{$label} '{$name}' is inactive{$suffix}, so it cannot be used for a new {$documentLabel}.";
        }

        return null;
    }

    public function canTransact(string $type, int|string|null $partyId, ?int $tenantId, string $documentLabel): bool
    {
        return $this->blockedReason($type, $partyId, $tenantId, $documentLabel) === null;
    }

    public function assertCanTransact(string $type, int|string|null $partyId, ?int $tenantId, string $documentLabel, string $field): void
    {
        $message = $this->blockedReason($type, $partyId, $tenantId, $documentLabel);

        if ($message !== null) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /**
     * Vendor payment blocking:
     *  - Unverified vendors cannot be paid.
     *  - Vendors made inactive for payment-blocking reasons (Suspected fraud, Compliance issue)
     *    cannot be paid until reactivated.
     *  - Vendors inactive for normal reasons (Dormant, Business closed, Duplicate, Credit risk, Other)
     *    may still be paid to settle existing dues (with a warning on the payment screen).
     */
    public function vendorPaymentBlockedReason(int|string|null $vendorId, ?int $tenantId): ?string
    {
        if (! $this->enabled() || ! $vendorId) {
            return null;
        }

        $vendor = Vendor::query()
            ->withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->find($vendorId);

        if (! $vendor) {
            return null;
        }

        $name = $vendor->name ?: 'Vendor #' . $vendor->getKey();

        if (! $vendor->isVerified()) {
            return "Vendor '{$name}' is waiting for verification, so a payment cannot be created yet.";
        }

        if (strtolower((string) $vendor->status) !== 'active') {
            $statusService = app(PartyStatusService::class);
            if ($statusService->blocksPayments($vendor->inactive_reason)) {
                $reason = $statusService->reasonLabel($vendor->inactive_reason);

                return "Vendor '{$name}' is inactive ({$reason}), so payments are blocked until it is reactivated.";
            }
        }

        return null;
    }

    public function assertCanPayVendor(int|string|null $vendorId, ?int $tenantId, string $field = 'vendor_id'): void
    {
        $message = $this->vendorPaymentBlockedReason($vendorId, $tenantId);

        if ($message !== null) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /**
     * Refuse using a PartyBranch that does not belong to the expected party,
     * is not verified yet, or is inactive (FRS 2.3 / C-17).
     *
     * @param  'customer'|'vendor'  $expectedPartyType
     */
    public function branchBlockedReason(
        string $expectedPartyType,
        int|string|null $expectedPartyId,
        int|string|null $branchId,
        ?int $tenantId,
        string $documentLabel
    ): ?string {
        if (! $branchId) {
            return null;
        }

        $branch = PartyBranch::query()
            ->withoutGlobalScopes()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->find($branchId);

        if (! $branch) {
            return "The selected {$expectedPartyType} branch does not exist for this tenant.";
        }

        if ($branch->party_type !== $expectedPartyType) {
            return "Branch '{$branch->name}' does not belong to a {$expectedPartyType}.";
        }

        if ($expectedPartyId && (int) $branch->party_id !== (int) $expectedPartyId) {
            return "Branch '{$branch->name}' does not belong to the selected {$expectedPartyType}.";
        }

        if ($this->enabled()) {
            if (! $branch->isVerified()) {
                return "Branch '{$branch->name}' is waiting for verification, so it cannot be used for a new {$documentLabel} yet.";
            }

            if (strtolower((string) $branch->status) !== 'active') {
                return "Branch '{$branch->name}' is inactive, so it cannot be used for a new {$documentLabel}.";
            }
        }

        return null;
    }

    public function assertCanUseBranch(
        string $expectedPartyType,
        int|string|null $expectedPartyId,
        int|string|null $branchId,
        ?int $tenantId,
        string $documentLabel,
        string $field
    ): void {
        $message = $this->branchBlockedReason($expectedPartyType, $expectedPartyId, $branchId, $tenantId, $documentLabel);

        if ($message !== null) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
