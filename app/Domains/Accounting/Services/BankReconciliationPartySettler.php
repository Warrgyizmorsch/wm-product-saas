<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementMatch;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Services\VendorPaymentService;
use App\Domains\Sales\Services\CustomerPaymentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Settles a bank line against a customer's open invoices or a vendor's open
 * bills. The payment itself is recorded by Sales / Purchase (their own
 * services keep invoices, bills and allocations right); Accounting's existing
 * listeners post it to the bank ledger this reconciliation is for, and the
 * resulting bank entry is matched to the statement line — all in one
 * transaction, so a failure anywhere leaves nothing half-done.
 */
class BankReconciliationPartySettler
{
    public const CUSTOMER = 'customer';
    public const VENDOR = 'vendor';

    /** Bank narration mode → the payment method labels Sales/Purchase use. */
    private const METHOD_LABELS = [
        'bank_transfer' => 'Bank Transfer',
        'upi' => 'UPI / QR',
        'cheque' => 'Cheque',
        'cash' => 'Cash',
        'card' => 'Card',
    ];

    public function __construct(
        private readonly BankReconciliationMatcher $matcher,
        private readonly CustomerPaymentService $customerPayments,
        private readonly VendorPaymentService $vendorPayments,
    ) {
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function parties(string $type): Collection
    {
        $model = $type === self::VENDOR ? Vendor::class : Customer::class;

        return $model::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($party) => ['id' => $party->id, 'name' => (string) $party->name]);
    }

    /**
     * Open invoices (customer) or bills (vendor), oldest first.
     *
     * @return list<array{id: int, number: string, date: ?string, due: ?string, total: float, balance: float}>
     */
    public function openDocuments(string $type, int $partyId): array
    {
        if ($type === self::VENDOR) {
            return $this->vendorPayments->openBills($partyId)->map(fn ($bill) => [
                'id' => $bill->id,
                'number' => $bill->bill_number . ($bill->vendor_invoice_number ? " ({$bill->vendor_invoice_number})" : ''),
                'date' => $bill->bill_date?->format('d M Y'),
                'due' => $bill->due_date?->format('d M Y'),
                'total' => round((float) ($bill->grand_total ?: $bill->total_amount), 2),
                'balance' => round((float) $bill->due_amount, 2),
            ])->values()->all();
        }

        return $this->customerPayments->openInvoices($partyId)->map(fn ($invoice) => [
            'id' => $invoice->id,
            'number' => $invoice->invoice_number,
            'date' => $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->format('d M Y') : null,
            'due' => $invoice->due_date ? Carbon::parse($invoice->due_date)->format('d M Y') : null,
            'total' => round((float) $invoice->total_amount, 2),
            'balance' => round((float) $invoice->balance_due, 2),
        ])->values()->all();
    }

    /**
     * @param array<int, float> $allocations document id => amount applied (the rest stays on account)
     */
    public function settle(BankReconciliation $reconciliation, int $statementLineId, string $partyType, int $partyId, array $allocations, ?int $userId = null): Journal
    {
        if ($reconciliation->isCompleted()) {
            throw new InvalidArgumentException('This reconciliation is already completed and locked.');
        }

        return DB::transaction(function () use ($reconciliation, $statementLineId, $partyType, $partyId, $allocations, $userId) {
            $line = $this->matcher->lockUnmatchedLine($reconciliation, $statementLineId);
            $amount = round(abs((float) $line->amount), 2);
            $method = self::METHOD_LABELS[$this->matcher->paymentMethodFor($line)] ?? 'Bank Transfer';
            $note = 'Bank reconciliation: ' . ($line->description ?: 'statement line');

            $common = [
                'tenant_id' => $reconciliation->tenant_id,
                'company_id' => $reconciliation->company_id,
                'branch_id' => $reconciliation->branch_id,
                'payment_date' => $line->transaction_date->toDateString(),
                'payment_method' => $method,
                'amount' => $amount,
                'notes' => $note,
                'bank_account_id' => $reconciliation->chart_of_account_id,
                'allocations' => $allocations,
            ];

            if ($partyType === self::CUSTOMER) {
                if ((float) $line->amount <= 0) {
                    throw new InvalidArgumentException('Only money received (a deposit) can be applied to customer invoices.');
                }

                $payment = $this->customerPayments->receive($common + ['customer_id' => $partyId, 'reference_no' => $line->reference]);
                $referenceType = 'customer_payment';
            } elseif ($partyType === self::VENDOR) {
                if ((float) $line->amount >= 0) {
                    throw new InvalidArgumentException('Only money paid out (a withdrawal) can be applied to vendor bills.');
                }

                $payment = $this->vendorPayments->payBills($common + ['vendor_id' => $partyId, 'reference_number' => $line->reference]);
                $referenceType = 'vendor_payment';
            } else {
                throw new InvalidArgumentException('Choose a customer or a vendor.');
            }

            // Posted synchronously by PostCustomerPaymentJournal / PostVendorPaymentJournal.
            $journal = Journal::withoutGlobalScope('tenant')
                ->where('tenant_id', $reconciliation->tenant_id)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $payment->id)
                ->first();

            if ($journal === null) {
                $reason = \App\Domains\Accounting\Models\AccountingPostingFailure::withoutGlobalScope('tenant')
                    ->where('tenant_id', $reconciliation->tenant_id)
                    ->latest('id')
                    ->value('message');

                throw new InvalidArgumentException('The payment could not be posted to the books' . ($reason ? ": {$reason}" : '.'));
            }

            $bankEntry = JournalEntry::withoutGlobalScope('tenant')
                ->where('journal_id', $journal->id)
                ->where('chart_of_account_id', $reconciliation->chart_of_account_id)
                ->first();

            if ($bankEntry === null) {
                throw new InvalidArgumentException('The payment was posted to a different bank ledger than this reconciliation.');
            }

            $this->matcher->recordMatch($reconciliation, $line, collect([$bankEntry]), $userId, BankStatementMatch::METHOD_POSTED);

            return $journal;
        });
    }
}
