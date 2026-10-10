<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\AccountResolverService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Domains\Inventory\Events\StockOutflowRecorded;
use App\Domains\Inventory\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Links Production material consumption to the General Ledger: debits
 * Work-in-Progress and credits Inventory for the value of raw material
 * issued to a Production Order. Registered on the same StockOutflowRecorded
 * event PostCogsJournal listens to — that listener explicitly excludes
 * Production issues (reference_type 'Production Material Issue'), so this
 * one picks up exactly the transactions it skips; Laravel dispatches an
 * event to every registered listener, so the two never race or double-post.
 */
class PostProductionConsumptionJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly PostingFailureRecorder $failures,
        private readonly AccountResolverService $accountResolver,
    ) {
    }

    public function handle(StockOutflowRecorded $event): void
    {
        $transaction = $event->transaction;

        if ($transaction->reference_type !== 'Production Material Issue' || $transaction->reference_id === null) {
            return;
        }

        if ((float) $transaction->total_value <= 0) {
            return;
        }

        // Keyed by the stock transaction's own id, not the production order id —
        // a single production order accumulates many material-issue transactions
        // over its lifetime, each needing its own journal, so the production
        // order id alone can't be the idempotency key (it would block every
        // issue after the first).
        if ($this->journals->activePosting((int) $transaction->tenant_id, 'stock_transaction', $transaction->id) !== null) {
            return;
        }

        try {
            $product = $transaction->product ?? Product::find($transaction->product_id);
            $wip = $this->accountResolver->resolveAccount(
                identifier: null,
                tenantId: $transaction->tenant_id,
                fallbackCode: '1204',
                fallbackType: \App\Domains\Accounting\Models\ChartOfAccount::TYPE_ASSET,
            );
            $inventory = $this->accountResolver->resolveInventoryAccount($product, $transaction->tenant_id);

            if (!$wip || !$inventory) {
                $message = 'Missing chart of accounts (WIP/Inventory), skipping auto-post';
                Log::warning("PostProductionConsumptionJournal: {$message}", [
                    'stock_transaction_id' => $transaction->id,
                    'tenant_id' => $transaction->tenant_id,
                ]);
                $this->failures->record($transaction->tenant_id, StockOutflowRecorded::class, $transaction, $message);

                return;
            }

            $this->journals->postOnce([
                [
                    'chart_of_account_id' => $wip->id,
                    'debit' => (float) $transaction->total_value,
                    'description' => 'Material consumption for production order #' . $transaction->reference_id,
                ],
                [
                    'chart_of_account_id' => $inventory->id,
                    'credit' => (float) $transaction->total_value,
                    'description' => 'Material consumption for production order #' . $transaction->reference_id,
                ],
            ], [
                'tenant_id' => $transaction->tenant_id,
                'journal_date' => now(),
                'source' => Journal::SOURCE_PRODUCTION,
                'reference_type' => 'stock_transaction',
                'reference_id' => $transaction->id,
                'memo' => 'Material consumption for production order #' . $transaction->reference_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('PostProductionConsumptionJournal: failed to auto-post journal', [
                'stock_transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            $this->failures->record($transaction->tenant_id, StockOutflowRecorded::class, $transaction, $e->getMessage());
        }
    }
}
