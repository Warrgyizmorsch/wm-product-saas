<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Sales\Events\InvoicePosted;
use Illuminate\Support\Facades\Log;

class PostInvoiceJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }

    public function handle(InvoicePosted $event): void
    {
        $invoice = $event->invoice;

        if ($this->journals->findByReference('invoice', $invoice->id)->isNotEmpty()) {
            return;
        }

        try {
            $accountsReceivable = $this->accounts->findByCode('1100', $invoice->tenant_id);
            $salesRevenue = $this->accounts->findByCode('4010', $invoice->tenant_id);
            $taxesPayable = $this->accounts->findByCode('2020', $invoice->tenant_id);

            if (!$accountsReceivable || !$salesRevenue || ($invoice->tax_total > 0 && !$taxesPayable)) {
                Log::warning('PostInvoiceJournal: missing chart of accounts, skipping auto-post', [
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $invoice->tenant_id,
                ]);

                return;
            }

            $lines = [
                [
                    'chart_of_account_id' => $accountsReceivable->id,
                    'debit' => (float) $invoice->grand_total,
                    'description' => "Invoice {$invoice->invoice_number}",
                ],
                [
                    'chart_of_account_id' => $salesRevenue->id,
                    'credit' => (float) $invoice->subtotal - (float) $invoice->discount,
                    'description' => "Invoice {$invoice->invoice_number}",
                ],
            ];

            if ((float) $invoice->tax_total > 0) {
                $lines[] = [
                    'chart_of_account_id' => $taxesPayable->id,
                    'credit' => (float) $invoice->tax_total,
                    'description' => "Tax on invoice {$invoice->invoice_number}",
                ];
            }

            $this->journals->post($lines, [
                'tenant_id' => $invoice->tenant_id,
                'journal_date' => $invoice->invoice_date,
                'source' => Journal::SOURCE_SALES,
                'reference_type' => 'invoice',
                'reference_id' => $invoice->id,
                'memo' => "Invoice {$invoice->invoice_number}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('PostInvoiceJournal: failed to auto-post journal', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
