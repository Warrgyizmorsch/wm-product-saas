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
            $itemTaxableSubtotal = (float) $salesReturn->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price);
            if ($itemTaxableSubtotal <= 0) {
                $itemTaxableSubtotal = (float) ($salesReturn->total_refund_amount > 0 ? $salesReturn->total_refund_amount : $salesReturn->total_amount);
            }

            if ($itemTaxableSubtotal <= 0) {
                return;
            }

            // Accounts Receivable (1100) - Asset Account
            $accountsReceivable = $this->accounts->findByCode('1100', $tenantId);
            // Sales Returns / Allowances (4020) or Sales Revenue (4010) - Income Account
            $salesReturnAccount = $this->accounts->findByCode('4020', $tenantId)
                ?? $this->accounts->findByCode('4010', $tenantId);

            // Output GST Accounts
            $cgstAccount = $this->accounts->findByCode('2110', $tenantId);
            $sgstAccount = $this->accounts->findByCode('2120', $tenantId);
            $igstAccount = $this->accounts->findByCode('2100', $tenantId);

            if (!$accountsReceivable || !$salesReturnAccount) {
                Log::warning('PostSalesReturnJournal: missing chart of accounts, skipping auto-post', [
                    'sales_return_id' => $salesReturn->id,
                    'tenant_id'       => $tenantId,
                ]);
                return;
            }

            // Determine Item GST Tax (Freight Charges and Freight GST are strictly excluded)
            $taxRate = 18.0; // Standard GST 18% (9% CGST + 9% SGST)
            $isInterState = false;

            if ($salesReturn->invoice_id) {
                $invoice = \App\Domains\Sales\Models\Invoice::find($salesReturn->invoice_id);
                if ($invoice && (float)$invoice->subtotal > 0 && (float)$invoice->tax_total > 0) {
                    $taxRate = round(((float)$invoice->tax_total / (float)$invoice->subtotal) * 100, 2);
                    $isInterState = (float)$invoice->igst_amount > 0;
                }
            }

            $cgstAmount = 0.0;
            $sgstAmount = 0.0;
            $igstAmount = 0.0;

            if ($taxRate > 0) {
                $totalTax = round($itemTaxableSubtotal * ($taxRate / 100), 2);
                if ($isInterState) {
                    $igstAmount = $totalTax;
                } else {
                    $cgstAmount = round($totalTax / 2, 2);
                    $sgstAmount = round($totalTax / 2, 2);
                }
            }

            $totalCreditNoteRefund = $itemTaxableSubtotal + $cgstAmount + $sgstAmount + $igstAmount;

            // 1. Credit Note Financial Journal (Income & Tax Reversal vs Customer Receivable)
            $lines = [
                [
                    'chart_of_account_id' => $salesReturnAccount->id,
                    'debit'               => $itemTaxableSubtotal,
                    'description'         => "Sales Return Taxable Value {$salesReturn->return_number}",
                ],
            ];

            if ($cgstAmount > 0 && $cgstAccount) {
                $lines[] = [
                    'chart_of_account_id' => $cgstAccount->id,
                    'debit'               => $cgstAmount,
                    'description'         => "CGST Reversal {$salesReturn->return_number}",
                ];
            }

            if ($sgstAmount > 0 && $sgstAccount) {
                $lines[] = [
                    'chart_of_account_id' => $sgstAccount->id,
                    'debit'               => $sgstAmount,
                    'description'         => "SGST Reversal {$salesReturn->return_number}",
                ];
            }

            if ($igstAmount > 0 && $igstAccount) {
                $lines[] = [
                    'chart_of_account_id' => $igstAccount->id,
                    'debit'               => $igstAmount,
                    'description'         => "IGST Reversal {$salesReturn->return_number}",
                ];
            }

            $lines[] = [
                'chart_of_account_id' => $accountsReceivable->id,
                'credit'              => $totalCreditNoteRefund,
                'description'         => "Credit Note Total Refund {$salesReturn->return_number}",
            ];

            $this->journals->post($lines, [
                'tenant_id'      => $tenantId,
                'journal_date'   => $salesReturn->return_date ?: now(),
                'source'         => Journal::SOURCE_SALES,
                'reference_type' => 'sales_return',
                'reference_id'   => $salesReturn->id,
                'memo'           => "Sales Return / Credit Note {$salesReturn->return_number}",
            ]);

            // 2. COGS & Inventory Asset Restocking Journal
            $inventoryAcc = $this->accounts->findByCode('1200', $tenantId);
            $cogsAcc = $this->accounts->findByCode('5010', $tenantId);

            if ($inventoryAcc && $cogsAcc) {
                $totalCostValue = 0.0;
                foreach ($salesReturn->items as $item) {
                    $unitCost = 0.0;
                    if ($salesReturn->sales_order_id) {
                        $soOutTx = \App\Domains\Inventory\Models\StockTransaction::where('tenant_id', $tenantId)
                            ->where('product_id', $item->product_id)
                            ->where('reference_type', 'SalesOrder')
                            ->where('reference_id', $salesReturn->sales_order_id)
                            ->first();

                        if ($soOutTx && (float)$soOutTx->unit_cost > 0) {
                            $unitCost = (float)$soOutTx->unit_cost;
                        }
                    }

                    if ($unitCost <= 0) {
                        $product = \App\Domains\Inventory\Models\Product::find($item->product_id);
                        if ($product) {
                            $unitCost = (float)($product->opening_stock_rate ?: ($product->cost_price ?: $product->unit_cost));
                        }
                    }

                    if ($unitCost <= 0) {
                        $unitCost = (float)$item->unit_price;
                    }

                    $totalCostValue += (float)$item->quantity * $unitCost;
                }

                if ($totalCostValue > 0) {
                    $cogsLines = [
                        [
                            'chart_of_account_id' => $inventoryAcc->id,
                            'debit'               => round($totalCostValue, 2),
                            'description'         => "Restock Inventory Asset {$salesReturn->return_number}",
                        ],
                        [
                            'chart_of_account_id' => $cogsAcc->id,
                            'credit'              => round($totalCostValue, 2),
                            'description'         => "COGS Expense Reversal {$salesReturn->return_number}",
                        ],
                    ];

                    $this->journals->post($cogsLines, [
                        'tenant_id'      => $tenantId,
                        'journal_date'   => $salesReturn->return_date ?: now(),
                        'source'         => Journal::SOURCE_INVENTORY,
                        'reference_type' => 'sales_return_cogs',
                        'reference_id'   => $salesReturn->id,
                        'memo'           => "Sales Return Inventory Restock COGS Reversal {$salesReturn->return_number}",
                    ]);
                }
            }

            Log::info("PostSalesReturnJournal: Credit Note & COGS Journals posted for Sales Return {$salesReturn->return_number}");
        } catch (\Throwable $e) {
            Log::warning('PostSalesReturnJournal: failed to auto-post journal', [
                'sales_return_id' => $salesReturn->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
