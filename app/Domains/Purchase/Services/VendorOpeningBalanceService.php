<?php

declare(strict_types=1);

namespace App\Domains\Purchase\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\AccountResolverService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Support\Facades\Log;

class VendorOpeningBalanceService
{
    public function __construct(
        protected JournalService $journals,
        protected SystemAccountService $systemAccounts,
        protected AccountResolverService $accountResolver,
    ) {}

    /**
     * Synchronizes a vendor's opening_balance with the General Ledger.
     * Reverses previous opening balance posting if amount changed, and posts a fresh double-entry journal:
     * - Credit: Accounts Payable (Code 2010 - Liability increased) tagged with party_type='vendor'
     * - Debit: Opening Balance Suspense / Equity Offset (Code 3020 / Suspense)
     */
    public function syncVendorOpeningBalance(Vendor $vendor): void
    {
        try {
            $existing = $this->journals->activePosting(
                (int) $vendor->tenant_id,
                'vendor_opening_balance',
                $vendor->id
            );

            $balance = round((float) ($vendor->opening_balance ?? 0), 2);

            // If an existing posting exists and amount has changed, reverse it
            if ($existing && round((float) $existing->total_credit, 2) !== $balance) {
                $this->journals->reverse($existing->id, 'Vendor opening balance changed or removed');
                $existing = null;
            }

            // If balance is 0 or already posted with same amount, nothing more to do
            if ($balance <= 0 || $existing) {
                return;
            }

            // Resolve Accounts Payable (2010 Liability)
            $apAccount = $this->systemAccounts->get(SystemAccount::AP, $vendor->tenant_id);
            if (!$apAccount) {
                $apAccount = $this->accountResolver->resolveAccount(
                    identifier: null,
                    tenantId: $vendor->tenant_id,
                    fallbackCode: '2010',
                    fallbackType: ChartOfAccount::TYPE_LIABILITY,
                );
            }

            // Resolve Opening Balance Suspense / Equity Offset (3020 Equity / Suspense)
            $suspense = $this->systemAccounts->get(SystemAccount::SUSPENSE, $vendor->tenant_id);
            if (!$suspense) {
                $suspense = $this->accountResolver->resolveAccount(
                    identifier: null,
                    tenantId: $vendor->tenant_id,
                    fallbackCode: '3020',
                    fallbackType: ChartOfAccount::TYPE_EQUITY,
                );
            }

            if (!$apAccount || !$suspense) {
                Log::warning('VendorOpeningBalanceService: AP or Suspense account not found for tenant', [
                    'vendor_id' => $vendor->id,
                    'tenant_id' => $vendor->tenant_id,
                ]);
                return;
            }

            $this->journals->postOnce([
                [
                    'chart_of_account_id' => $apAccount->id,
                    'debit' => 0,
                    'credit' => $balance,
                    'description' => "Opening balance for Vendor {$vendor->name} ({$vendor->code})",
                    'party_type' => JournalEntry::PARTY_VENDOR,
                    'party_id' => $vendor->id,
                ],
                [
                    'chart_of_account_id' => $suspense->id,
                    'debit' => $balance,
                    'credit' => 0,
                    'description' => "Opening balance offset for Vendor {$vendor->name} ({$vendor->code})",
                ],
            ], [
                'tenant_id' => $vendor->tenant_id,
                'company_id' => $vendor->company_id,
                'branch_id' => $vendor->branch_id,
                'journal_date' => now(),
                'source' => Journal::SOURCE_PURCHASE,
                'reference_type' => 'vendor_opening_balance',
                'reference_id' => $vendor->id,
                'memo' => "Opening Balance — {$vendor->name} ({$vendor->code})",
            ]);
        } catch (\Throwable $e) {
            Log::warning('VendorOpeningBalanceService: failed to sync opening balance', [
                'vendor_id' => $vendor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
