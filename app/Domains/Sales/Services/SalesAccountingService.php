<?php

namespace App\Domains\Sales\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\AccountResolverService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Events\InvoicePosted;
use Illuminate\Support\Facades\Log;

class SalesAccountingService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly PostingFailureRecorder $failures,
        private readonly AccountResolverService $accountResolver,
    ) {}

    /**
     * Automatically post balanced double-entry accounting journal for a Sales Invoice.
     *
     * Debit:  Accounts Receivable (Customer A/c) - Total Invoice Amount
     * Credit: Sales Revenue A/c (Product specific or standard 4010) - Net Taxable Sales
     * Credit: Output CGST A/c - Output CGST Tax Amount
     * Credit: Output SGST A/c - Output SGST Tax Amount
     * Credit: Output IGST A/c - Output IGST Tax Amount
     * Credit: Freight & Logistics Income A/c - Freight Amount (when Freight Terms = 'To Be Billed')
     */
    public function postInvoiceJournal(Invoice $invoice): ?Journal
    {
        $tenantId = $invoice->tenant_id;

        $existing = Journal::where('tenant_id', $tenantId)
            ->where('reference_type', 'Invoice')
            ->where('reference_id', $invoice->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $invoice->loadMissing('items.product');

        // 1. Debtors / Accounts Receivable A/c (1100)
        $accountsReceivable = $this->accountResolver->resolveAccount(
            identifier: null,
            tenantId: $tenantId,
            fallbackCode: '1100',
            fallbackType: ChartOfAccount::TYPE_ASSET
        );

        // 2. Default Sales Revenue A/c (4010)
        $defaultSalesRevenue = $this->accountResolver->resolveSalesAccount(null, $tenantId);

        // 3. Output CGST A/c
        $outputCgst = ChartOfAccount::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('code', '2110')->orWhere('name', 'like', '%Output CGST%');
            })->first();

        // 4. Output SGST A/c
        $outputSgst = ChartOfAccount::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('code', '2120')->orWhere('name', 'like', '%Output SGST%');
            })->first();

        // 5. Output IGST A/c
        $outputIgst = ChartOfAccount::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('code', '2130')->orWhere('name', 'like', '%Output IGST%');
            })->first();

        // 6. Freight & Shipping Account (5610 Outward Freight)
        $freightIncome = ChartOfAccount::where('tenant_id', $tenantId)->where('code', '5610')->first()
            ?? (ChartOfAccount::where('tenant_id', $tenantId)
                ->where(function ($q) {
                    $q->where('name', 'like', '%Freight%')->orWhere('name', 'like', '%Shipping%');
                })->first() ?? ChartOfAccount::where('tenant_id', $tenantId)->where('code', '4900')->first());

        // 7. Round Off
        $roundOff = ChartOfAccount::where('tenant_id', $tenantId)->where('code', '5730')->first();

        if (!$accountsReceivable || !$defaultSalesRevenue) {
            Log::warning("SalesAccountingService: Missing core Chart of Accounts for tenant {$tenantId}");
            $this->failures->record($tenantId, InvoicePosted::class, $invoice, 'Missing Accounts Receivable or Sales Revenue account.');
            return null;
        }

        $lines = [];

        // Debit Customer (Accounts Receivable) -> Total Invoice Amount
        $lines[] = [
            'chart_of_account_id' => $accountsReceivable->id,
            'debit'               => round((float) $invoice->total_amount, 2),
            'credit'              => 0,
            'description'         => "Debtors - Invoice {$invoice->invoice_number}",
        ];

        // Credit Sales Revenue -> Net Taxable Amount partitioned by each product's sales account
        $netTaxableSales = round((float) ($invoice->subtotal - $invoice->discount_amount), 2);
        if ($netTaxableSales > 0) {
            $salesBuckets = []; // account_id => ['account' => ChartOfAccount, 'amount' => float]
            $itemsSubtotalSum = 0.0;

            foreach ($invoice->items as $item) {
                $lineTaxable = max(0, ((float)$item->quantity * (float)$item->unit_price) - (float)($item->discount ?? 0));
                $itemsSubtotalSum += $lineTaxable;
                $resolvedSalesAcc = $this->accountResolver->resolveSalesAccount($item->product, $tenantId) ?: $defaultSalesRevenue;
                $accId = $resolvedSalesAcc->id;

                if (!isset($salesBuckets[$accId])) {
                    $salesBuckets[$accId] = [
                        'account' => $resolvedSalesAcc,
                        'amount' => 0.0,
                    ];
                }
                $salesBuckets[$accId]['amount'] += $lineTaxable;
            }

            // If header discount was applied or no item records exist, scale buckets to match netTaxableSales
            if ($itemsSubtotalSum > 0 && abs($itemsSubtotalSum - $netTaxableSales) > 0.005) {
                $scale = $netTaxableSales / $itemsSubtotalSum;
                foreach ($salesBuckets as $accId => $bucket) {
                    $salesBuckets[$accId]['amount'] = round($bucket['amount'] * $scale, 2);
                }
            } elseif ($itemsSubtotalSum <= 0 || empty($salesBuckets)) {
                $salesBuckets[$defaultSalesRevenue->id] = [
                    'account' => $defaultSalesRevenue,
                    'amount'  => $netTaxableSales,
                ];
            }

            foreach ($salesBuckets as $accId => $bucket) {
                if ($bucket['amount'] > 0) {
                    $lines[] = [
                        'chart_of_account_id' => $accId,
                        'debit'               => 0,
                        'credit'              => round($bucket['amount'], 2),
                        'description'         => "Sales Revenue ({$bucket['account']->name}) - Invoice {$invoice->invoice_number}",
                    ];
                }
            }
        }

        // Credit Output CGST
        if ((float) $invoice->cgst_amount > 0 && $outputCgst) {
            $lines[] = [
                'chart_of_account_id' => $outputCgst->id,
                'debit'               => 0,
                'credit'              => round((float) $invoice->cgst_amount, 2),
                'description'         => "Output CGST Payable - Invoice {$invoice->invoice_number}",
            ];
        }

        // Credit Output SGST
        if ((float) $invoice->sgst_amount > 0 && $outputSgst) {
            $lines[] = [
                'chart_of_account_id' => $outputSgst->id,
                'debit'               => 0,
                'credit'              => round((float) $invoice->sgst_amount, 2),
                'description'         => "Output SGST Payable - Invoice {$invoice->invoice_number}",
            ];
        }

        // Credit Output IGST
        if ((float) $invoice->igst_amount > 0 && $outputIgst) {
            $lines[] = [
                'chart_of_account_id' => $outputIgst->id,
                'debit'               => 0,
                'credit'              => round((float) $invoice->igst_amount, 2),
                'description'         => "Output IGST Payable - Invoice {$invoice->invoice_number}",
            ];
        }

        // Credit Freight Income (only when freight_terms === 'To Be Billed')
        $effectiveFreight = ($invoice->freight_terms === 'To Be Billed') ? (float) $invoice->freight_amount : 0;
        if ($effectiveFreight > 0 && $freightIncome) {
            $lines[] = [
                'chart_of_account_id' => $freightIncome->id,
                'debit'               => 0,
                'credit'              => round($effectiveFreight, 2),
                'description'         => "Freight Charges Income Billed - Invoice {$invoice->invoice_number}",
            ];
        }

        // Round Off / manual adjustment
        $adjustment = round((float) $invoice->adjustment, 2);
        if ($adjustment != 0) {
            if (!$roundOff) {
                Log::warning("SalesAccountingService: Missing Round Off (5730) account, cannot balance adjustment for invoice {$invoice->invoice_number}");
                $this->failures->record($tenantId, InvoicePosted::class, $invoice, 'Missing Round Off (5730) account to post the invoice adjustment.');
                return null;
            }
            $lines[] = [
                'chart_of_account_id' => $roundOff->id,
                'debit'               => $adjustment < 0 ? abs($adjustment) : 0,
                'credit'              => $adjustment > 0 ? $adjustment : 0,
                'description'         => "Round Off - Invoice {$invoice->invoice_number}",
            ];
        }

        $companyId = $invoice->company_id ?? (company_id() ?? \App\Domains\HRMS\Models\Company::where('tenant_id', $tenantId)->value('id'));
        $branchId  = $invoice->branch_id ?? (branch_id() ?? \App\Domains\HRMS\Models\Branch::where('tenant_id', $tenantId)->value('id'));

        try {
            $meta = [
                'tenant_id'             => $tenantId,
                'company_id'            => $companyId,
                'branch_id'             => $branchId,
                'journal_date'          => $invoice->invoice_date ?? now(),
                'source'                => Journal::SOURCE_SALES,
                'reference_type'        => 'Invoice',
                'reference_id'          => $invoice->id,
                'memo'                  => "Sales Invoice Posting #{$invoice->invoice_number}",
                'journal_number_prefix' => 'INV-JNL',
            ];

            return $this->journalService->post($lines, $meta);
        } catch (\Exception $e) {
            Log::error("SalesAccountingService Exception posting invoice journal: " . $e->getMessage());
            $this->failures->record($tenantId, InvoicePosted::class, $invoice, $e->getMessage());
            return null;
        }
    }

    /**
     * Automatically post balanced COGS Inventory Accounting Journal when a Dispatch Order is shipped/outwarded.
     *
     * Debit:  Cost of Goods Sold (COGS A/c) - Product COGS / 5010
     * Credit: Inventory Asset A/c           - Product Inventory / 1200
     */
    public function postDispatchOrderCogsJournal(\App\Domains\Sales\Models\DispatchOrder $dispatch): ?Journal
    {
        $tenantId = $dispatch->tenant_id ?: (tenant_id() ?? 1);

        $dispatch->loadMissing('items.product');

        // Check if journal already posted for this Dispatch Order
        $existing = Journal::where('tenant_id', $tenantId)
            ->where('reference_type', 'DispatchOrder')
            ->where('reference_id', $dispatch->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $cogsBuckets = [];      // cogs_account_id => float
        $inventoryBuckets = []; // inventory_account_id => float

        foreach ($dispatch->items as $item) {
            $qty = (float) ($item->quantity_dispatched > 0 ? $item->quantity_dispatched : $item->quantity_ordered);
            
            // Valuation Cost Price:
            // 1. First look up the exact StockTransaction recorded for this DispatchOrder & product.
            //    StockService::recordOutflow() calculates FIFO lot depletion or Weighted Average unit cost.
            $stockTx = \App\Domains\Inventory\Models\StockTransaction::where('tenant_id', $tenantId)
                ->where('reference_type', 'DispatchOrder')
                ->where('reference_id', $dispatch->id)
                ->where('product_id', $item->product_id)
                ->where('type', 'OUT')
                ->first();

            $lineCogs = 0.0;
            if ($stockTx && (float)$stockTx->total_value > 0) {
                $lineCogs = round((float)$stockTx->total_value, 2);
            } elseif ($stockTx && (float)$stockTx->unit_cost > 0) {
                $lineCogs = round($qty * (float)$stockTx->unit_cost, 2);
            }

            // 2. Fallbacks if StockTransaction was not present or zero
            if ($lineCogs <= 0) {
                $whCost = (float) (\App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->warehouse_id)
                    ->value('unit_cost') ?? 0);

                $productCost = $whCost > 0 ? $whCost : (float) ($item->product?->cost_price ?? $item->product?->purchase_price ?? $item->product?->unit_cost ?? 0);

                if ($productCost <= 0) {
                    $latestInCost = (float) (\App\Domains\Inventory\Models\StockTransaction::where('tenant_id', $tenantId)
                        ->where('product_id', $item->product_id)
                        ->where('type', 'IN')
                        ->latest('id')
                        ->value('unit_cost') ?? 0);
                    if ($latestInCost > 0) {
                        $productCost = $latestInCost;
                    }
                }

                if ($productCost <= 0) {
                    $productCost = round((float) ($item->product?->selling_price ?? 0) * 0.6, 2);
                }

                $lineCogs = round($qty * $productCost, 2);
            }

            if ($lineCogs <= 0) continue;

            $cogsAcc = $this->accountResolver->resolveCogsAccount($item->product, $tenantId);
            $invAcc  = $this->accountResolver->resolveInventoryAccount($item->product, $tenantId);

            if ($cogsAcc && $invAcc) {
                $cogsBuckets[$cogsAcc->id] = ($cogsBuckets[$cogsAcc->id] ?? 0.0) + $lineCogs;
                $inventoryBuckets[$invAcc->id] = ($inventoryBuckets[$invAcc->id] ?? 0.0) + $lineCogs;
            }
        }

        if (empty($cogsBuckets) || empty($inventoryBuckets)) {
            return null;
        }

        $lines = [];

        foreach ($cogsBuckets as $cogsAccId => $amount) {
            if ($amount > 0) {
                $lines[] = [
                    'chart_of_account_id' => $cogsAccId,
                    'debit'               => round($amount, 2),
                    'credit'              => 0,
                    'description'         => "COGS Expense - Goods Outward for Dispatch #{$dispatch->dispatch_number}",
                ];
            }
        }

        foreach ($inventoryBuckets as $invAccId => $amount) {
            if ($amount > 0) {
                $lines[] = [
                    'chart_of_account_id' => $invAccId,
                    'debit'               => 0,
                    'credit'              => round($amount, 2),
                    'description'         => "Inventory Outward - Stock Shipped for Dispatch #{$dispatch->dispatch_number}",
                ];
            }
        }

        $companyId = $dispatch->company_id ?? (company_id() ?? \App\Domains\HRMS\Models\Company::where('tenant_id', $tenantId)->value('id'));
        $branchId  = $dispatch->branch_id ?? (branch_id() ?? \App\Domains\HRMS\Models\Branch::where('tenant_id', $tenantId)->value('id'));

        try {
            $meta = [
                'tenant_id'             => $tenantId,
                'company_id'            => $companyId,
                'branch_id'             => $branchId,
                'journal_date'          => $dispatch->dispatch_date ?? now(),
                'source'                => Journal::SOURCE_INVENTORY,
                'reference_type'        => 'DispatchOrder',
                'reference_id'          => $dispatch->id,
                'memo'                  => "Inventory COGS Outward Journal for Dispatch #{$dispatch->dispatch_number}",
                'journal_number_prefix' => 'COGS-JNL',
            ];

            return $this->journalService->post($lines, $meta);
        } catch (\Exception $e) {
            Log::error("SalesAccountingService Exception posting COGS journal: " . $e->getMessage());
            return null;
        }
    }
}

