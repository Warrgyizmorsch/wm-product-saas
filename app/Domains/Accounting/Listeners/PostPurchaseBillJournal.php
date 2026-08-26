<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Domains\Purchase\Events\BillPosted;
use Illuminate\Support\Facades\Log;

class PostPurchaseBillJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
        private readonly PostingFailureRecorder $failures,
    ) {
    }

    public function handle(BillPosted $event): void
    {
        $bill = $event->bill;

        if ($this->journals->findByReference('vendor_bill', $bill->id)->isNotEmpty()) {
            return;
        }

        try {
            $tenantId = $bill->tenant_id;

            $accountsPayable = $this->accounts->findByCode('2010', $tenantId);
            $inputGst = $this->accounts->findByCode('1600', $tenantId);
            $inventory = $this->accounts->findByCode('1200', $tenantId);
            $purchaseExpense = $this->accounts->findByCode('5900', $tenantId);

            if (!$accountsPayable || (!$inventory && !$purchaseExpense) || ((float) $bill->tax_amount > 0 && !$inputGst)) {
                $message = 'Missing chart of accounts (Accounts Payable/Inventory/Expense/Input GST), skipping auto-post';
                Log::warning("PostPurchaseBillJournal: {$message}", [
                    'bill_id' => $bill->id,
                    'tenant_id' => $tenantId,
                ]);
                $this->failures->record($tenantId, BillPosted::class, $bill, $message);
                return;
            }

            $goodsSubtotal = 0.0;
            $serviceSubtotal = 0.0;

            foreach ($bill->items as $item) {
                $lineSubtotal = (float) $item->quantity * (float) $item->unit_rate;
                $isService = $item->product && $item->product->item_type === 'Service';

                if ($isService) {
                    $serviceSubtotal += $lineSubtotal;
                } else {
                    $goodsSubtotal += $lineSubtotal;
                }
            }

            // Fallback: if items/products can't be resolved, treat the whole subtotal as
            // Inventory — safer default for a Purchase module, where most bills are goods receipts.
            if ($goodsSubtotal <= 0 && $serviceSubtotal <= 0) {
                $goodsSubtotal = (float) $bill->subtotal;
            }

            $lines = [];

            if ($goodsSubtotal > 0) {
                if (!$inventory) {
                    $message = 'Missing Inventory (1200) account, skipping auto-post';
                    Log::warning("PostPurchaseBillJournal: {$message}", [
                        'bill_id' => $bill->id,
                        'tenant_id' => $tenantId,
                    ]);
                    $this->failures->record($tenantId, BillPosted::class, $bill, $message);
                    return;
                }
                $lines[] = [
                    'chart_of_account_id' => $inventory->id,
                    'debit' => $goodsSubtotal,
                    'description' => "Bill {$bill->bill_number} - Goods",
                ];
            }

            if ($serviceSubtotal > 0) {
                if (!$purchaseExpense) {
                    $message = 'Missing Expense (5900) account, skipping auto-post';
                    Log::warning("PostPurchaseBillJournal: {$message}", [
                        'bill_id' => $bill->id,
                        'tenant_id' => $tenantId,
                    ]);
                    $this->failures->record($tenantId, BillPosted::class, $bill, $message);
                    return;
                }
                $lines[] = [
                    'chart_of_account_id' => $purchaseExpense->id,
                    'debit' => $serviceSubtotal,
                    'description' => "Bill {$bill->bill_number} - Services",
                ];
            }

            if ((float) $bill->tax_amount > 0) {
                $lines[] = [
                    'chart_of_account_id' => $inputGst->id,
                    'debit' => (float) $bill->tax_amount,
                    'description' => "Input GST on Bill {$bill->bill_number}",
                ];
            }

            $lines[] = [
                'chart_of_account_id' => $accountsPayable->id,
                'credit' => (float) $bill->grand_total,
                'description' => "Bill {$bill->bill_number}",
            ];

            $this->journals->post($lines, [
                'tenant_id' => $tenantId,
                'journal_date' => $bill->bill_date,
                'source' => Journal::SOURCE_PURCHASE,
                'reference_type' => 'vendor_bill',
                'reference_id' => $bill->id,
                'memo' => "Vendor Bill {$bill->bill_number}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('PostPurchaseBillJournal: failed to auto-post journal', [
                'bill_id' => $bill->id,
                'error' => $e->getMessage(),
            ]);
            $this->failures->record($bill->tenant_id, BillPosted::class, $bill, $e->getMessage());
        }
    }
}
