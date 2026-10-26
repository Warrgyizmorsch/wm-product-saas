<?php

namespace App\Console\Commands;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\BillReference;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use App\Domains\Accounting\Services\BillWise\AdvanceAdjustmentService;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Services\OpeningBalanceService;
use App\Domains\Purchase\Models\PurchaseReturn;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\PaymentAllocation;
use App\Domains\Sales\Models\SalesReturn;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Builds (or rebuilds — safe to re-run) the bill-wise register from the
 * journals already in the books: invoices and vendor bills open bills,
 * receipts / payments / returns settle the documents they were applied to,
 * opening dues become "Opening Balance" bills, everything else is On Account.
 * Also posts the Advance-from-Customers adjustment for sales-order advances
 * already applied to invoices. Finishes by checking every party's register
 * against its ledger balance.
 */
class BuildBillRegister extends Command
{
    protected $signature = 'accounting:build-bill-register
                            {--tenant= : Only this tenant id}
                            {--dry-run : Report what would be built without changing anything}';

    protected $description = 'Build the bill-wise (New Ref / Agst Ref / On Account) register from posted journals';

    public function handle(BillAllocationService $bills, AdvanceAdjustmentService $advances, OpeningBalanceService $openings, JournalRepositoryInterface $journalRepository): int
    {
        if (! BillAllocationService::enabled()) {
            $this->error('Run the migrations first (bill_references / bill_allocations tables are missing).');

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey((int) $id))
            ->get();

        $mismatches = 0;

        foreach ($tenants as $tenant) {
            app(TenantContext::class)->set($tenant);
            $this->line("Tenant {$tenant->id} ({$tenant->name})");

            $controls = $bills->controlAccounts($tenant->id);
            if ($controls === []) {
                $this->warn('  no Accounts Receivable / Payable ledger; skipped');
                continue;
            }

            $journals = Journal::query()
                ->whereIn('status', [Journal::STATUS_POSTED, Journal::STATUS_REVERSED])
                ->whereHas('entries', fn ($q) => $q->whereIn('chart_of_account_id', array_keys($controls))->whereNotNull('party_id'))
                ->orderBy('journal_date')->orderBy('id')
                ->get();

            // reversed_journal_id sits on the original: reversal id => original id.
            $reversalOf = Journal::query()->whereNotNull('reversed_journal_id')->pluck('id', 'reversed_journal_id');
            $pendingAdvances = $this->invoicesWithUnadjustedAdvances($tenant->id);

            if ($this->option('dry-run')) {
                $this->line("  would rebuild the register from {$journals->count()} journal(s) with party lines");
                foreach ($pendingAdvances as $invoice) {
                    $this->line("  would post the advance adjustment for invoice {$invoice->invoice_number}");
                }
                continue;
            }

            try {
                DB::transaction(function () use ($tenant, $journals, $reversalOf, $bills, $openings) {
                    BillAllocation::query()->delete();
                    BillReference::query()->delete();

                    foreach ($journals as $journal) {
                        $original = $reversalOf[$journal->id] ?? null;

                        if ($original !== null) {
                            $bills->mirrorReversal(Journal::find($original), $journal);
                        } else {
                            $bills->allocateJournal($journal, $this->hintsFor($journal, $openings));
                        }
                    }

                    $this->line('  register built from ' . $journals->count() . ' journal(s)');
                });

                foreach ($pendingAdvances as $invoice) {
                    foreach ($advances->adjustInvoice($invoice) as $journal) {
                        $this->line("  advance applied to invoice {$invoice->invoice_number}: posted {$journal->journal_number}");
                    }
                }
            } catch (Throwable $e) {
                $this->error("  failed: {$e->getMessage()}");
                $mismatches++;
                continue;
            }

            $mismatches += $this->checkInvariant($tenant->id, $controls, $journalRepository);
        }

        if ($mismatches > 0) {
            $this->warn("{$mismatches} problem(s) found; see above.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * The same hints the live posting paths now pass, rebuilt for an old journal.
     *
     * @return list<array<string, mixed>>
     */
    private function hintsFor(Journal $journal, OpeningBalanceService $openings): array
    {
        $id = (int) $journal->reference_id;

        return match ($journal->reference_type) {
            'Invoice' => ($invoice = Invoice::withoutGlobalScopes()->find($id))
                ? [BillAllocationService::newRef('receivable', (string) $invoice->invoice_number, $invoice->invoice_date ?? $journal->journal_date, $invoice->due_date, 'Invoice', $id)]
                : [],
            'vendor_bill' => ($bill = VendorBill::withoutGlobalScopes()->find($id))
                ? [BillAllocationService::newRef('payable', (string) $bill->bill_number, $bill->bill_date ?? $journal->journal_date, $bill->due_date, 'vendor_bill', $id)]
                : [],
            'customer_payment' => $this->customerPaymentHints($id),
            'vendor_payment' => $this->vendorPaymentHints($id),
            'sales_return' => ($return = SalesReturn::withoutGlobalScopes()->find($id)) && $return->invoice_id
                ? [BillAllocationService::againstRef('receivable', 'Invoice', (int) $return->invoice_id)]
                : [],
            'purchase_return' => ($return = PurchaseReturn::withoutGlobalScopes()->find($id)) && $return->vendor_bill_id
                ? [BillAllocationService::againstRef('payable', 'vendor_bill', (int) $return->vendor_bill_id)]
                : [],
            'customer_opening_balance', 'vendor_opening_balance' => $this->openingHints($journal, $openings),
            // An asset bought on credit owes the vendor a bill.
            'asset_capitalization' => ($asset = \App\Domains\HRMS\Models\Asset::withoutGlobalScopes()->find($id))
                ? [BillAllocationService::newRef('payable', (string) ($asset->invoice_number ?: $asset->asset_code), $asset->capitalization_date ?? $journal->journal_date, $asset->capitalization_date ?? $journal->journal_date, 'asset_capitalization', $id)]
                : [],
            AdvanceAdjustmentService::REFERENCE_TYPE => ($allocation = PaymentAllocation::withoutGlobalScopes()->find($id)) && $allocation->invoice_id
                ? [BillAllocationService::againstRef('receivable', 'Invoice', (int) $allocation->invoice_id)]
                : [],
            default => [],
        };
    }

    private function customerPaymentHints(int $paymentId): array
    {
        $payment = CustomerPayment::withoutGlobalScopes()->find($paymentId);
        if ($payment === null) {
            return [];
        }

        if (str_starts_with((string) $payment->notes, 'Against opening balance')) {
            return [BillAllocationService::againstRef('receivable', 'customer_opening_balance', (int) $payment->customer_id)];
        }

        return $payment->allocations()->whereNotNull('invoice_id')->get()
            ->map(fn ($a) => BillAllocationService::againstRef('receivable', 'Invoice', (int) $a->invoice_id, (float) $a->allocated_amount))
            ->values()->all();
    }

    private function vendorPaymentHints(int $paymentId): array
    {
        $payment = VendorPayment::withoutGlobalScopes()->find($paymentId);
        if ($payment === null) {
            return [];
        }

        if ($payment->payment_type === 'Opening Balance') {
            return [BillAllocationService::againstRef('payable', 'vendor_opening_balance', (int) $payment->vendor_id)];
        }

        return $payment->allocations()->get()
            ->map(fn ($a) => BillAllocationService::againstRef('payable', 'vendor_bill', (int) $a->vendor_bill_id, (float) $a->allocated_amount))
            ->values()->all();
    }

    private function openingHints(Journal $journal, OpeningBalanceService $openings): array
    {
        $line = $journal->entries()->whereNotNull('party_id')->first();
        if ($line === null) {
            return [];
        }

        return $openings->openingBillHints([
            'party_type' => $line->party_type,
            'party_id' => (int) $line->party_id,
            'reference_type' => $journal->reference_type,
            'reference_id' => (int) $journal->reference_id,
        ], (float) $line->debit > 0, abs((float) $line->debit - (float) $line->credit));
    }

    /** Invoices carrying a sales-order advance whose adjustment journal is missing. */
    private function invoicesWithUnadjustedAdvances(int $tenantId)
    {
        $invoiceIds = PaymentAllocation::query()
            ->whereNotNull('invoice_id')->whereNotNull('sales_order_id')->where('allocated_amount', '>', 0)
            ->whereNotIn('id', Journal::query()->where('reference_type', AdvanceAdjustmentService::REFERENCE_TYPE)->where('status', Journal::STATUS_POSTED)->pluck('reference_id'))
            ->pluck('invoice_id')->unique();

        $advanceJournalPayments = Journal::query()
            ->where('reference_type', 'customer_payment')->where('status', Journal::STATUS_POSTED)
            ->whereHas('entries', fn ($q) => $q->whereHas('account', fn ($a) => $a->where('system_key', \App\Domains\Accounting\Support\SystemAccount::ADVANCE_FROM_CUSTOMERS)))
            ->pluck('reference_id');

        return Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $invoiceIds)
            ->whereIn('id', PaymentAllocation::whereIn('customer_payment_id', $advanceJournalPayments)->pluck('invoice_id'))
            ->get();
    }

    /** Every party's register must equal its ledger balance on the control account. */
    private function checkInvariant(int $tenantId, array $controls, JournalRepositoryInterface $journalRepository): int
    {
        $problems = 0;

        foreach (array_keys($controls) as $controlId) {
            $ledger = $journalRepository->partyBalancesUpTo($tenantId, (int) $controlId, now()->endOfDay())
                ->mapWithKeys(fn ($r) => ["{$r->party_type}:{$r->party_id}" => round((float) $r->balance, 2)]);

            $register = BillAllocation::query()->where('control_account_id', $controlId)
                ->groupBy('party_type', 'party_id')->selectRaw('party_type, party_id, SUM(amount) as balance')->get()
                ->mapWithKeys(fn ($r) => ["{$r->party_type}:{$r->party_id}" => round((float) $r->balance, 2)]);

            foreach ($ledger->keys()->merge($register->keys())->unique() as $party) {
                $diff = round(($ledger[$party] ?? 0) - ($register[$party] ?? 0), 2);
                if (abs($diff) >= 0.01) {
                    $problems++;
                    $this->error("  {$party} on account #{$controlId}: ledger " . number_format($ledger[$party] ?? 0, 2) . ' vs register ' . number_format($register[$party] ?? 0, 2));
                }
            }
        }

        if ($problems === 0) {
            $this->line('  every party\'s bills add up to its ledger balance');
        }

        return $problems;
    }
}
