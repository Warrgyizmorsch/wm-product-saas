<?php

declare(strict_types=1);

namespace App\Domains\CRM\Services;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\OpeningBalanceService;
use App\Domains\CRM\Models\Customer;

/**
 * Keeps a customer's opening balance on the ledger: Accounts Receivable tagged
 * with the customer, debit when they owe you, credit for an advance received,
 * dated at the books' start. See OpeningBalanceService.
 *
 * Throws InvalidArgumentException when it cannot post (no financial year,
 * period closed, missing account) so the screen can tell the user.
 */
class CustomerOpeningBalanceService
{
    public function __construct(
        protected OpeningBalanceService $openings,
    ) {}

    public function syncCustomerOpeningBalance(Customer $customer): ?Journal
    {
        $tenantId = (int) $customer->tenant_id;

        return $this->openings->sync([
            'tenant_id' => $tenantId,
            'company_id' => $customer->company_id,
            'branch_id' => $customer->branch_id,
            'reference_type' => 'customer_opening_balance',
            'reference_id' => (int) $customer->id,
            'chart_of_account_id' => $this->openings->controlAccount(JournalEntry::PARTY_CUSTOMER, $tenantId)->id,
            'party_type' => JournalEntry::PARTY_CUSTOMER,
            'party_id' => (int) $customer->id,
            'label' => "Customer {$customer->name}",
            'source' => Journal::SOURCE_SALES,
        ], (float) ($customer->opening_balance ?? 0), $customer->opening_balance_type ?: OpeningBalanceService::DEBIT);
    }

    /**
     * For screens that save the customer first: post the opening balance and return
     * a message for the user if it could not be posted, instead of throwing.
     */
    public function trySync(Customer $customer): ?string
    {
        try {
            $this->syncCustomerOpeningBalance($customer);

            return null;
        } catch (\InvalidArgumentException $e) {
            return "Saved, but the opening balance was not posted to the ledger: {$e->getMessage()}";
        }
    }
}
