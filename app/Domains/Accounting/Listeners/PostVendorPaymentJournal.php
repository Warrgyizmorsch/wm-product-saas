<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\Purchase\Events\VendorPaymentRecorded;
use Illuminate\Support\Facades\Log;

class PostVendorPaymentJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
        private readonly SystemAccountService $systemAccounts,
        private readonly PostingFailureRecorder $failures,
    ) {
    }

    public function handle(VendorPaymentRecorded $event): void
    {
        $payment = $event->payment;

        if ($this->journals->findByReference('vendor_payment', $payment->id)->isNotEmpty()) {
            return;
        }

        try {
            // The bank/cash ledger the money actually left from; older payments
            // without one fall back to 1010 Cash / 1020 Bank Account as before.
            if ($payment->bank_account_id) {
                $paymentAccount = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScope('tenant')->where('tenant_id', $payment->tenant_id)->whereKey($payment->bank_account_id)->first();
            } else {
                $bankKey = $payment->payment_method === 'Cash' ? SystemAccount::CASH : SystemAccount::BANK;
                $paymentAccount = $this->systemAccounts->get($bankKey, $payment->tenant_id);
            }

            $isAdvance = $payment->payment_type === 'Advance';
            $debitKey = $isAdvance ? SystemAccount::ADVANCE_TO_SUPPLIERS : SystemAccount::AP;
            $debitAccount = $this->systemAccounts->get($debitKey, $payment->tenant_id);

            if (!$paymentAccount || !$debitAccount) {
                $message = 'Missing chart of accounts (Bank/Cash/Accounts Payable/Advance to Suppliers), skipping auto-post';
                Log::warning("PostVendorPaymentJournal: {$message}", [
                    'payment_id' => $payment->id,
                    'tenant_id' => $payment->tenant_id,
                ]);
                $this->failures->record($payment->tenant_id, VendorPaymentRecorded::class, $payment, $message);
                return;
            }

            $vendorName = $payment->vendor?->name ?? ('Vendor #' . $payment->vendor_id);
            $memo = $isAdvance
                ? "Advance Payment to Vendor {$vendorName} ({$payment->payment_number})"
                : "Vendor Payment {$payment->payment_number} for Bill";

            $this->journals->post([
                [
                    'chart_of_account_id' => $debitAccount->id,
                    'debit' => (float) $payment->amount,
                    'description' => $isAdvance
                        ? "Advance to Vendor ({$vendorName})"
                        : "Accounts Payable settled ({$vendorName})",
                    'party_type' => JournalEntry::PARTY_VENDOR,
                    'party_id' => $payment->vendor_id,
                ],
                [
                    'chart_of_account_id' => $paymentAccount->id,
                    'credit' => (float) $payment->amount,
                    'description' => "Paid via {$payment->payment_method}" . ($payment->reference_number ? " Ref: {$payment->reference_number}" : ''),
                ],
            ], [
                'tenant_id' => $payment->tenant_id,
                'company_id' => $payment->company_id,
                'branch_id' => $payment->branch_id,
                'journal_date' => $payment->payment_date,
                'source' => Journal::SOURCE_PURCHASE,
                'reference_type' => 'vendor_payment',
                'reference_id' => $payment->id,
                'memo' => $memo,
            ]);
        } catch (\Throwable $e) {
            Log::warning('PostVendorPaymentJournal: failed to auto-post journal', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            $this->failures->record($payment->tenant_id, VendorPaymentRecorded::class, $payment, $e->getMessage());
        }
    }
}
