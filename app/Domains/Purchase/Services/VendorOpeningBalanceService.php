<?php

declare(strict_types=1);

namespace App\Domains\Purchase\Services;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\OpeningBalanceService;
use App\Domains\Inventory\Models\Vendor;

/**
 * Keeps a vendor's opening balance on the ledger: Accounts Payable tagged with
 * the vendor, credit when you owe them, debit for an advance paid, dated at
 * the books' start. See OpeningBalanceService.
 *
 * Throws InvalidArgumentException when it cannot post (no financial year,
 * period closed, missing account) so the screen can tell the user.
 */
class VendorOpeningBalanceService
{
    public function __construct(
        protected OpeningBalanceService $openings,
    ) {}

    public function syncVendorOpeningBalance(Vendor $vendor): ?Journal
    {
        $tenantId = (int) $vendor->tenant_id;

        return $this->openings->sync([
            'tenant_id' => $tenantId,
            'company_id' => $vendor->company_id,
            'branch_id' => $vendor->branch_id,
            'reference_type' => 'vendor_opening_balance',
            'reference_id' => (int) $vendor->id,
            'chart_of_account_id' => $this->openings->controlAccount(JournalEntry::PARTY_VENDOR, $tenantId)->id,
            'party_type' => JournalEntry::PARTY_VENDOR,
            'party_id' => (int) $vendor->id,
            'label' => trim("Vendor {$vendor->name} " . ($vendor->code ? "({$vendor->code})" : '')),
            'source' => Journal::SOURCE_PURCHASE,
        ], (float) ($vendor->opening_balance ?? 0), $vendor->opening_balance_type ?: OpeningBalanceService::CREDIT);
    }

    /**
     * For screens that save the vendor first: post the opening balance and return
     * a message for the user if it could not be posted, instead of throwing.
     */
    public function trySync(Vendor $vendor): ?string
    {
        try {
            $this->syncVendorOpeningBalance($vendor);

            return null;
        } catch (\InvalidArgumentException $e) {
            return "Saved, but the opening balance was not posted to the ledger: {$e->getMessage()}";
        }
    }
}
