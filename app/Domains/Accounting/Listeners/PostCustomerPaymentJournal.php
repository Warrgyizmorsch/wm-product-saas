<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Sales\Events\CustomerPaymentReceived;
use Illuminate\Support\Facades\Log;

class PostCustomerPaymentJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
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
            $bank = $this->accounts->findByCode('1020', $payment->tenant_id);
            $creditCode = $isInvoiceAllocation ? '1100' : '2200';
            $creditAccount = $this->accounts->findByCode($creditCode, $payment->tenant_id);

            if (!$bank || !$creditAccount) {
                Log::warning('PostCustomerPaymentJournal: missing chart of accounts, skipping auto-post', [
                    'payment_id' => $payment->id,
                    'tenant_id' => $payment->tenant_id,
                ]);

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
                ],
            ], [
                'tenant_id' => $payment->tenant_id,
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
        }
    }
}
