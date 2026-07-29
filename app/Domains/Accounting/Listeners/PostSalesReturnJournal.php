<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Sales\Events\SalesReturnApproved;
use Illuminate\Support\Facades\Log;

class PostSalesReturnJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }

    public function handle(SalesReturnApproved $event): void
    {
        $salesReturn = $event->salesReturn;

        if ($this->journals->findByReference('sales_return', $salesReturn->id)->isNotEmpty()) {
            return;
        }

        $tenantId = $salesReturn->tenant_id ?: (tenant_id() ?? 1);

        try {
            $totalAmount = (float) ($salesReturn->total_refund_amount > 0 
                ? $salesReturn->total_refund_amount 
                : ($salesReturn->total_amount > 0 
                    ? $salesReturn->total_amount 
                    : $salesReturn->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price)));

            if ($totalAmount <= 0) {
                return;
            }

            // Accounts Receivable (1100) - Asset Account
            $accountsReceivable = $this->accounts->findByCode('1100', $tenantId);
            // Sales Returns / Allowances (4020) or Sales Revenue (4010) - Income Account
            $salesReturnAccount = $this->accounts->findByCode('4020', $tenantId)
                ?? $this->accounts->findByCode('4010', $tenantId);

            if (!$accountsReceivable || !$salesReturnAccount) {
                Log::warning('PostSalesReturnJournal: missing chart of accounts, skipping auto-post', [
                    'sales_return_id' => $salesReturn->id,
                    'tenant_id'       => $tenantId,
                ]);

                return;
            }

            // Double Entry for Sales Return (Credit Note):
            // Debit: Sales Returns / Revenue (reduces income)
            // Credit: Accounts Receivable (reduces customer balance)
            $lines = [
                [
                    'chart_of_account_id' => $salesReturnAccount->id,
                    'debit'               => $totalAmount,
                    'description'         => "Sales Return {$salesReturn->return_number}",
                ],
                [
                    'chart_of_account_id' => $accountsReceivable->id,
                    'credit'              => $totalAmount,
                    'description'         => "Sales Return {$salesReturn->return_number}",
                ],
            ];

            $this->journals->post($lines, [
                'tenant_id'      => $tenantId,
                'journal_date'   => $salesReturn->return_date ?: now(),
                'source'         => Journal::SOURCE_SALES,
                'reference_type' => 'sales_return',
                'reference_id'   => $salesReturn->id,
                'memo'           => "Sales Return / Credit Note {$salesReturn->return_number}",
            ]);

            Log::info("PostSalesReturnJournal: Journal posted for Sales Return {$salesReturn->return_number}");
        } catch (\Throwable $e) {
            Log::warning('PostSalesReturnJournal: failed to auto-post journal', [
                'sales_return_id' => $salesReturn->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
