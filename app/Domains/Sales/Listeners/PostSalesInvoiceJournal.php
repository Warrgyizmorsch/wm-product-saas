<?php

namespace App\Domains\Sales\Listeners;

use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Domains\Sales\Events\InvoicePosted;
use App\Domains\Sales\Services\SalesAccountingService;
use Illuminate\Support\Facades\Log;

class PostSalesInvoiceJournal
{
    public function __construct(
        private readonly SalesAccountingService $salesAccountingService,
        private readonly PostingFailureRecorder $failures,
    ) {}

    public function handle(InvoicePosted $event): void
    {
        $invoice = $event->invoice;

        if (!$invoice) {
            return;
        }

        try {
            $journal = $this->salesAccountingService->postInvoiceJournal($invoice);
            if ($journal) {
                Log::info("PostSalesInvoiceJournal: Successfully posted accounting journal #{$journal->journal_number} for Invoice #{$invoice->invoice_number}");

                // Keep the invoice's own status in sync with the GL — a Draft invoice
                // that has already been journaled is invisible to reports (e.g. AR
                // Aging) that key off status rather than the ledger.
                if ($invoice->status === 'Draft') {
                    $invoice->status = 'Posted';
                    $invoice->save();
                }
            } else {
                // SalesAccountingService already logged the specific reason (missing
                // chart of accounts, closed accounting period, etc.) — record it as a
                // resolvable failure so it doesn't only live in the application log.
                $this->failures->record(
                    $invoice->tenant_id,
                    InvoicePosted::class,
                    $invoice,
                    "No journal was posted for Invoice #{$invoice->invoice_number} — see application log for the underlying reason."
                );
            }
        } catch (\Throwable $e) {
            Log::warning("PostSalesInvoiceJournal Error: " . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
            $this->failures->record($invoice->tenant_id, InvoicePosted::class, $invoice, $e->getMessage());
        }
    }
}
