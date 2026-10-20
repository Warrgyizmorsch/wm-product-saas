<?php

declare(strict_types=1);

namespace App\Domains\CRM\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\AccountResolverService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\CRM\Models\Customer;
use Illuminate\Support\Facades\Log;

class CustomerOpeningBalanceService
{
    public function __construct(
        protected JournalService $journals,
        protected SystemAccountService $systemAccounts,
        protected AccountResolverService $accountResolver,
    ) {}

    /**
     * Synchronizes a customer's opening_balance with the General Ledger.
     * Reverses previous opening balance posting if amount changed, and posts a fresh double-entry journal:
     * - Debit: Accounts Receivable (Code 1020 - Asset increased) tagged with party_type='customer'
     * - Credit: Opening Balance Suspense / Equity Offset (Code 3020 / Suspense)
     */
    public function syncCustomerOpeningBalance(Customer $customer): void
    {
        try {
            $existing = $this->journals->activePosting(
                (int) $customer->tenant_id,
                'customer_opening_balance',
                $customer->id
            );

            $balance = round((float) ($customer->opening_balance ?? 0), 2);

            // If an existing posting exists and amount has changed, reverse it
            if ($existing && round((float) $existing->total_debit, 2) !== $balance) {
                $this->journals->reverse($existing->id, 'Customer opening balance changed or removed');
                $existing = null;
            }

            // If balance is 0 or already posted with same amount, nothing more to do
            if ($balance <= 0 || $existing) {
                return;
            }

            // Resolve Accounts Receivable (1020 Asset)
            $arAccount = $this->systemAccounts->get(SystemAccount::AR, $customer->tenant_id);
            if (!$arAccount) {
                $arAccount = $this->accountResolver->resolveAccount(
                    identifier: null,
                    tenantId: $customer->tenant_id,
                    fallbackCode: '1020',
                    fallbackType: ChartOfAccount::TYPE_ASSET,
                );
            }

            // Resolve Opening Balance Suspense / Equity Offset (3020 Equity / Suspense)
            $suspense = $this->systemAccounts->get(SystemAccount::SUSPENSE, $customer->tenant_id);
            if (!$suspense) {
                $suspense = $this->accountResolver->resolveAccount(
                    identifier: null,
                    tenantId: $customer->tenant_id,
                    fallbackCode: '3020',
                    fallbackType: ChartOfAccount::TYPE_EQUITY,
                );
            }

            if (!$arAccount || !$suspense) {
                Log::warning('CustomerOpeningBalanceService: AR or Suspense account not found for tenant', [
                    'customer_id' => $customer->id,
                    'tenant_id' => $customer->tenant_id,
                ]);
                return;
            }

            $this->journals->postOnce([
                [
                    'chart_of_account_id' => $arAccount->id,
                    'debit' => $balance,
                    'credit' => 0,
                    'description' => "Opening balance for Customer {$customer->name}",
                    'party_type' => JournalEntry::PARTY_CUSTOMER,
                    'party_id' => $customer->id,
                ],
                [
                    'chart_of_account_id' => $suspense->id,
                    'debit' => 0,
                    'credit' => $balance,
                    'description' => "Opening balance offset for Customer {$customer->name}",
                ],
            ], [
                'tenant_id' => $customer->tenant_id,
                'company_id' => $customer->company_id,
                'branch_id' => $customer->branch_id,
                'journal_date' => now(),
                'source' => Journal::SOURCE_SALES,
                'reference_type' => 'customer_opening_balance',
                'reference_id' => $customer->id,
                'memo' => "Opening Balance — {$customer->name}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('CustomerOpeningBalanceService: failed to sync opening balance', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
