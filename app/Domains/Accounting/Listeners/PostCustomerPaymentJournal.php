<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\Sales\Events\CustomerPaymentReceived;
use Illuminate\Support\Facades\Log;

class PostCustomerPaymentJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
        private readonly SystemAccountService $systemAccounts,
        private readonly PostingFailureRecorder $failures,
    ) {
    }

    public function handle(CustomerPaymentReceived $event): void
    {
        $payment = $event->payment;

        if ($this->journals->findByReference('customer_payment', $payment->id)->isNotEmpty()) {
            return;
        }

        $allocations = $payment->allocations()->get();
        $isInvoiceAllocation = $allocations->contains(fn ($a) => $a->invoice_id !== null);
        $isAdvanceAllocation = $allocations->contains(fn ($a) => $a->invoice_id === null && $a->sales_order_id !== null);

        if (!$isInvoiceAllocation && !$isAdvanceAllocation) {
            Log::warning('PostCustomerPaymentJournal: payment has no invoice/sales-order allocation, skipping auto-post', [
                'payment_id' => $payment->id,
            ]);

            return;
        }

        try {
            // The bank/cash ledger the money actually went to (e.g. HDFC); older
            // payments without one fall back to 1020 Bank Account as before.
            $bank = $payment->bank_account_id
                ? \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScope('tenant')->where('tenant_id', $payment->tenant_id)->whereKey($payment->bank_account_id)->first()
                : $this->systemAccounts->get(SystemAccount::BANK, $payment->tenant_id);
            $creditKey = $isInvoiceAllocation ? SystemAccount::AR : SystemAccount::ADVANCE_FROM_CUSTOMERS;
            $creditAccount = $this->systemAccounts->get($creditKey, $payment->tenant_id);

            if (!$bank || !$creditAccount) {
                $message = 'Missing chart of accounts (Bank/Accounts Receivable/Customer Advances), skipping auto-post';
                Log::warning("PostCustomerPaymentJournal: {$message}", [
                    'payment_id' => $payment->id,
                    'tenant_id' => $payment->tenant_id,
                ]);
                $this->failures->record($payment->tenant_id, CustomerPaymentReceived::class, $payment, $message);

                return;
            }

            $this->journals->post([
                [
                    'chart_of_account_id' => $bank->id,
                    'debit' => (float) $payment->amount,
                    'description' => "Payment {$payment->payment_number}",
                ],
                [
                    'chart_of_account_id' => $creditAccount->id,
                    'credit' => (float) $payment->amount,
                    'description' => "Payment {$payment->payment_number}",
                    'party_type' => JournalEntry::PARTY_CUSTOMER,
                    'party_id' => $payment->customer_id,
                ],
            ], [
                'tenant_id' => $payment->tenant_id,
                'company_id' => $payment->company_id,
                'branch_id' => $payment->branch_id,
                'journal_date' => $payment->payment_date,
                'source' => Journal::SOURCE_SALES,
                'reference_type' => 'customer_payment',
                'reference_id' => $payment->id,
                'memo' => "Customer payment {$payment->payment_number}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('PostCustomerPaymentJournal: failed to auto-post journal', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            $this->failures->record($payment->tenant_id, CustomerPaymentReceived::class, $payment, $e->getMessage());
        }
    }
}
