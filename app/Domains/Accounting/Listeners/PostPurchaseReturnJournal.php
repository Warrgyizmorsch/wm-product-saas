<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Purchase\Events\PurchaseReturnApproved;
use Illuminate\Support\Facades\Log;

class PostPurchaseReturnJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }

    public function handle(PurchaseReturnApproved $event): void
    {
        $return = $event->purchaseReturn;

        if ($this->journals->findByReference('purchase_return', $return->id)->isNotEmpty()) {
            return;
        }

        $tenantId = $return->tenant_id ?: (tenant_id() ?? 1);

        try {
            $totalAmount = (float) ($return->total_refund_amount > 0
                ? $return->total_refund_amount
                : ($return->total_amount > 0
                    ? $return->total_amount
                    : $return->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price)));

            if ($totalAmount <= 0) {
                return;
            }

            $accountsPayable = $this->accounts->findByCode('2010', $tenantId);
            $inventory = $this->accounts->findByCode('1200', $tenantId);

            if (!$accountsPayable || !$inventory) {
                Log::warning('PostPurchaseReturnJournal: missing chart of accounts, skipping auto-post', [
                    'purchase_return_id' => $return->id,
                    'tenant_id' => $tenantId,
                ]);
                return;
            }

            $lines = [
                [
                    'chart_of_account_id' => $accountsPayable->id,
                    'debit' => $totalAmount,
                    'description' => "Purchase Return {$return->return_number}",
                ],
                [
                    'chart_of_account_id' => $inventory->id,
                    'credit' => $totalAmount,
                    'description' => "Purchase Return {$return->return_number}",
                ],
            ];

            $this->journals->post($lines, [
                'tenant_id' => $tenantId,
                'journal_date' => $return->return_date ?: now(),
                'source' => Journal::SOURCE_PURCHASE,
                'reference_type' => 'purchase_return',
                'reference_id' => $return->id,
                'memo' => "Purchase Return / Debit Note {$return->return_number}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('PostPurchaseReturnJournal: failed to auto-post journal', [
                'purchase_return_id' => $return->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
