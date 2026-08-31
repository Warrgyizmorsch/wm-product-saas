<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\VoucherDetail;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Domains\Accounting\Support\VoucherType;
use App\Domains\Sales\Events\SalesReturnApproved;
use Illuminate\Support\Facades\Log;

class PostSalesReturnJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
        private readonly PostingFailureRecorder $failures,
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
            // Sales Returns & Allowances (4030), falling back to Sales Revenue (4010)
            // for tenants provisioned before the dedicated returns ledger existed.
            $salesReturnAccount = $this->accounts->findByCode('4030', $tenantId)
                ?? $this->accounts->findByCode('4010', $tenantId);

            // Output GST Accounts
            $cgstAccount = $this->accounts->findByCode('2110', $tenantId);
            $sgstAccount = $this->accounts->findByCode('2120', $tenantId);
            $igstAccount = $this->accounts->findByCode('2130', $tenantId) ?: $this->accounts->findByCode('2100', $tenantId);

            if (!$accountsReceivable || !$salesReturnAccount) {
                $message = 'Missing chart of accounts (Accounts Receivable/Sales Returns/Sales Revenue), skipping auto-post';
                Log::warning("PostSalesReturnJournal: {$message}", [
                    'sales_return_id' => $salesReturn->id,
                    'tenant_id'       => $tenantId,
                ]);
                $this->failures->record($tenantId, SalesReturnApproved::class, $salesReturn, $message);
                return;
            }

            // Determine Item GST Tax (Freight Charges and Freight GST are strictly excluded)
            $taxRate = 18.0; // Standard GST 18% (9% CGST + 9% SGST)
            $isInterState = false;

            $invoice = null;
            if ($salesReturn->invoice_id) {
                $invoice = \App\Domains\Sales\Models\Invoice::find($salesReturn->invoice_id);
            } elseif ($salesReturn->sales_order_id) {
                $invoice = \App\Domains\Sales\Models\Invoice::where('sales_order_id', $salesReturn->sales_order_id)
                    ->where('status', '!=', 'Cancelled')
                    ->latest()
                    ->first();
            }

            if ($invoice) {
                if ((float)$invoice->subtotal > 0 && (float)$invoice->tax_amount > 0) {
                    $taxRate = round(((float)$invoice->tax_amount / (float)$invoice->subtotal) * 100, 2);
                }
                $isInterState = ((float)$invoice->igst_amount > 0) || ($invoice->gst_type === 'IGST');
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

            $creditNoteJournal = $this->journals->post($lines, [
                'tenant_id'              => $tenantId,
                'journal_date'           => $salesReturn->return_date ?: now(),
                'source'                 => Journal::SOURCE_SALES,
                'reference_type'         => 'sales_return',
                'reference_id'           => $salesReturn->id,
                'memo'                   => "Sales Return / Credit Note {$salesReturn->return_number}",
                'voucher_type'           => VoucherType::CREDIT_NOTE,
                'journal_number_prefix'  => VoucherType::prefix(VoucherType::CREDIT_NOTE),
            ]);

            VoucherDetail::create([
                'tenant_id'    => $tenantId,
                'journal_id'   => $creditNoteJournal->id,
                'voucher_type' => VoucherType::CREDIT_NOTE,
                'party_type'   => 'customer',
                'party_id'     => $salesReturn->customer_id,
                'party_name'   => $salesReturn->customer?->name,
                'reference_no' => $salesReturn->return_number,
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
            $this->failures->record($tenantId, SalesReturnApproved::class, $salesReturn, $e->getMessage());
        }
    }
}
