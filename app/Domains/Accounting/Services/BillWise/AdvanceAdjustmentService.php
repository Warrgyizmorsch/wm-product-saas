<?php

namespace App\Domains\Accounting\Services\BillWise;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\PaymentAllocation;

/**
 * A receipt taken against a sales order is booked as Advance from Customers
 * (a liability). When that order is invoiced and the advance is applied to
 * the invoice, the ledger has to follow the document:
 *     Dr Advance from Customers   /   Cr Accounts Receivable (Agst Ref invoice)
 * Without this the invoice reads "paid" while the books still show the full
 * receivable and the advance owed back.
 */
class AdvanceAdjustmentService
{
    public const REFERENCE_TYPE = 'advance_adjustment';

    public function __construct(
        private readonly JournalService $journals,
        private readonly SystemAccountService $systemAccounts,
    ) {
    }

    /**
     * Post the adjustment for every sales-order advance now applied to this
     * invoice. Safe to call again: one journal per allocation (postOnce).
     *
     * @return list<Journal>
     */
    public function adjustInvoice(Invoice $invoice): array
    {
        $tenantId = (int) $invoice->tenant_id;
        $advance = $this->systemAccounts->get(SystemAccount::ADVANCE_FROM_CUSTOMERS, $tenantId);
        $receivable = $this->systemAccounts->get(SystemAccount::AR, $tenantId);

        if ($advance === null || $receivable === null) {
            return [];
        }

        $posted = [];

        $allocations = PaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->whereNotNull('sales_order_id')
            ->where('allocated_amount', '>', 0)
            ->get();

        foreach ($allocations as $allocation) {
            if (! $this->wasBookedAsAdvance((int) $allocation->customer_payment_id, $tenantId, $advance->id)) {
                continue;
            }

            $amount = round((float) $allocation->allocated_amount, 2);
            $customerId = (int) ($invoice->customer_id ?? $invoice->salesOrder?->customer_id);

            $posted[] = $this->journals->postOnce([
                [
                    'chart_of_account_id' => $advance->id,
                    'debit' => $amount,
                    'description' => "Advance applied to invoice {$invoice->invoice_number}",
                    'party_type' => JournalEntry::PARTY_CUSTOMER,
                    'party_id' => $customerId,
                ],
                [
                    'chart_of_account_id' => $receivable->id,
                    'credit' => $amount,
                    'description' => "Advance applied to invoice {$invoice->invoice_number}",
                    'party_type' => JournalEntry::PARTY_CUSTOMER,
                    'party_id' => $customerId,
                ],
            ], [
                'tenant_id' => $tenantId,
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'journal_date' => $invoice->invoice_date ?? now(),
                'source' => Journal::SOURCE_SALES,
                'reference_type' => self::REFERENCE_TYPE,
                'reference_id' => $allocation->id,
                'memo' => "Sales-order advance applied to invoice {$invoice->invoice_number}",
                'bill_allocations' => [BillAllocationService::againstRef('receivable', 'Invoice', (int) $invoice->id, $amount)],
            ]);
        }

        return $posted;
    }

    /** The receipt's journal credited Advance from Customers (not Receivable). */
    private function wasBookedAsAdvance(int $paymentId, int $tenantId, int $advanceAccountId): bool
    {
        $journal = $this->journals->activePosting($tenantId, 'customer_payment', $paymentId);

        return $journal !== null
            && $journal->entries()->where('chart_of_account_id', $advanceAccountId)->where('credit', '>', 0)->exists();
    }
}
