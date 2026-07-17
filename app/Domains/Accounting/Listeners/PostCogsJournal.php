<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Inventory\Events\StockOutflowRecorded;
use Illuminate\Support\Facades\Log;

class PostCogsJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }

    public function handle(StockOutflowRecorded $event): void
    {
        $transaction = $event->transaction;

        // Scope filter: only Sales deliveries drive COGS in this phase — Production
        // material issues/scrap and manual Inventory adjustments are out of scope.
        if ($transaction->reference_type !== 'DeliveryOrder' || $transaction->reference_id === null) {
            return;
        }

        if ((float) $transaction->total_value <= 0) {
            return;
        }

        if ($this->journals->findByReference('delivery_order', $transaction->reference_id)->isNotEmpty()) {
            return;
        }

        try {
            $cogs = $this->accounts->findByCode('5010', $transaction->tenant_id);
            $inventory = $this->accounts->findByCode('1200', $transaction->tenant_id);

            if (!$cogs || !$inventory) {
                Log::warning('PostCogsJournal: missing chart of accounts, skipping auto-post', [
                    'stock_transaction_id' => $transaction->id,
                    'tenant_id' => $transaction->tenant_id,
                ]);

                return;
            }

            $this->journals->post([
                [
                    'chart_of_account_id' => $cogs->id,
                    'debit' => (float) $transaction->total_value,
                    'description' => 'Cost of goods sold for delivery order #' . $transaction->reference_id,
                ],
                [
                    'chart_of_account_id' => $inventory->id,
                    'credit' => (float) $transaction->total_value,
                    'description' => 'Cost of goods sold for delivery order #' . $transaction->reference_id,
                ],
            ], [
                'tenant_id' => $transaction->tenant_id,
                'journal_date' => now(),
                'source' => Journal::SOURCE_INVENTORY,
                'reference_type' => 'delivery_order',
                'reference_id' => $transaction->reference_id,
                'memo' => 'COGS for delivery order #' . $transaction->reference_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('PostCogsJournal: failed to auto-post journal', [
                'stock_transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
