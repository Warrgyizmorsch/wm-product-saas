<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Purchase\Events\BillPosted;
use Illuminate\Support\Facades\Log;

class PostPurchaseBillJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
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

            // Fetch Chart of Accounts by standard codes
            $accountsPayable = $this->accounts->findByCode('2010', $tenantId);
            $inputGst        = $this->accounts->findByCode('1600', $tenantId);
            $inputCgst       = $this->accounts->findByCode('1601', $tenantId) ?: $inputGst;
            $inputSgst       = $this->accounts->findByCode('1602', $tenantId) ?: $inputGst;
            $inputIgst       = $this->accounts->findByCode('1603', $tenantId) ?: $inputGst;
            $inventory       = $this->accounts->findByCode('1200', $tenantId);
            $purchaseExpense = $this->accounts->findByCode('5900', $tenantId);
            $freightExpense  = $this->accounts->findByCode('5030', $tenantId) ?: $purchaseExpense;

            if (!$accountsPayable) {
                Log::warning('PostPurchaseBillJournal: missing Accounts Payable (2010) account, skipping auto-post', [
                    'bill_id' => $bill->id,
                    'tenant_id' => $tenantId,
                ]);
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

            // Calculate item base value (subtotal minus discount)
            $netItemsValue = max(0.01, (float)$bill->subtotal - (float)$bill->discount_amount);
            if ($goodsSubtotal <= 0 && $serviceSubtotal <= 0) {
                $goodsSubtotal = $netItemsValue;
            } else if ($bill->discount_amount > 0) {
                $goodsSubtotal = max(0.01, $goodsSubtotal - (float)$bill->discount_amount);
            }

            $lines = [];

            // 1. Inventory / Stock Asset (Debit) - Item Base Amount Only
            if ($goodsSubtotal > 0 && $inventory) {
                $lines[] = [
                    'chart_of_account_id' => $inventory->id,
                    'debit'               => round($goodsSubtotal, 2),
                    'credit'              => 0,
                    'description'         => "Bill {$bill->bill_number} - Stock Purchase (Base Item Value)",
                ];
            }

            // 2. Service Purchase Expense (Debit)
            if ($serviceSubtotal > 0 && $purchaseExpense) {
                $lines[] = [
                    'chart_of_account_id' => $purchaseExpense->id,
                    'debit'               => round($serviceSubtotal, 2),
                    'credit'              => 0,
                    'description'         => "Bill {$bill->bill_number} - Service Charges",
                ];
            }

            // 3. Freight Charges Expense (Debit) - Dedicated Separate Freight Account (5400)
            if ((float)$bill->freight_amount > 0 && $freightExpense) {
                $lines[] = [
                    'chart_of_account_id' => $freightExpense->id,
                    'debit'               => round((float)$bill->freight_amount, 2),
                    'credit'              => 0,
                    'description'         => "Bill {$bill->bill_number} - Freight & Transport Charges",
                ];
            }

            // 4. Input GST Tax Credits (Debit)
            if ((float)$bill->igst_amount > 0 && $inputIgst) {
                $lines[] = [
                    'chart_of_account_id' => $inputIgst->id,
                    'debit'               => round((float)$bill->igst_amount, 2),
                    'credit'              => 0,
                    'description'         => "Input IGST on Bill {$bill->bill_number}",
                ];
            } else {
                if ((float)$bill->cgst_amount > 0 && $inputCgst) {
                    $lines[] = [
                        'chart_of_account_id' => $inputCgst->id,
                        'debit'               => round((float)$bill->cgst_amount, 2),
                        'credit'              => 0,
                        'description'         => "Input CGST on Bill {$bill->bill_number}",
                    ];
                }
                if ((float)$bill->sgst_amount > 0 && $inputSgst) {
                    $lines[] = [
                        'chart_of_account_id' => $inputSgst->id,
                        'debit'               => round((float)$bill->sgst_amount, 2),
                        'credit'              => 0,
                        'description'         => "Input SGST on Bill {$bill->bill_number}",
                    ];
                }
            }

            // Fallback for tax amount if specific CGST/SGST accounts not mapped
            if (empty($lines) || ((float)$bill->tax_amount > 0 && (float)$bill->cgst_amount == 0 && (float)$bill->igst_amount == 0 && $inputGst)) {
                $lines[] = [
                    'chart_of_account_id' => $inputGst->id,
                    'debit'               => round((float)$bill->tax_amount, 2),
                    'credit'              => 0,
                    'description'         => "Input GST Tax Credit on Bill {$bill->bill_number}",
                ];
            }

            // 5. Accounts Payable / Vendor Account (Credit)
            $lines[] = [
                'chart_of_account_id' => $accountsPayable->id,
                'debit'               => 0,
                'credit'              => round((float)$bill->grand_total, 2),
                'description'         => "Vendor Payable - Bill {$bill->bill_number}",
            ];

            // Post General Ledger Journal Entry
            $this->journals->post($lines, [
                'tenant_id'      => $tenantId,
                'journal_date'   => $bill->bill_date,
                'source'         => Journal::SOURCE_PURCHASE,
                'reference_type' => 'vendor_bill',
                'reference_id'   => $bill->id,
                'memo'           => "Vendor Bill {$bill->bill_number} ({$bill->vendor?->name})",
            ]);

            Log::info("PostPurchaseBillJournal: Successfully posted GL entry for Bill {$bill->bill_number}", ['bill_id' => $bill->id]);
        } catch (\Throwable $e) {
            Log::error('PostPurchaseBillJournal: failed to auto-post journal', [
                'bill_id' => $bill->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
