<?php

namespace App\Domains\Sales\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\CustomerPayment;
use Illuminate\Support\Facades\Log;

class SalesAccountingService
{
    public function __construct(
        private readonly JournalService $journalService
    ) {}

    /**
     * Automatically post balanced double-entry accounting journal for a Sales Invoice.
     *
     * Debit:  Accounts Receivable (Customer A/c) - Total Invoice Amount
     * Credit: Sales Revenue A/c - Net Taxable Sales (Subtotal - Discount)
     * Credit: Output CGST A/c - Output CGST Tax Amount
     * Credit: Output SGST A/c - Output SGST Tax Amount
     * Credit: Output IGST A/c - Output IGST Tax Amount
     * Credit: Freight & Logistics Income A/c - Freight Amount (when Freight Terms = 'To Be Billed')
     */
    public function postInvoiceJournal(Invoice $invoice): ?Journal
    {
        $tenantId = $invoice->tenant_id;

        // 1. Debtors / Accounts Receivable A/c
        $accountsReceivable = ChartOfAccount::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('code', '1100')->orWhere('name', 'Accounts Receivable');
            })->first() ?? ChartOfAccount::where('tenant_id', $tenantId)->where('subtype', ChartOfAccount::SUBTYPE_CURRENT_ASSET)->first();

        // 2. Sales Revenue A/c
        $salesRevenue = ChartOfAccount::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('code', '4010')->orWhere('name', 'Sales Revenue');
            })->first() ?? ChartOfAccount::where('tenant_id', $tenantId)->where('type', ChartOfAccount::TYPE_INCOME)->first();

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

        // 6. Freight & Shipping Income A/c
        $freightIncome = ChartOfAccount::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('name', 'like', '%Freight%')->orWhere('name', 'like', '%Shipping%');
            })->first() ?? ChartOfAccount::where('tenant_id', $tenantId)->where('code', '4900')->first();

        if (!$accountsReceivable || !$salesRevenue) {
            Log::warning("SalesAccountingService: Missing core Chart of Accounts for tenant {$tenantId}");
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

        // Credit Sales Revenue -> Net Taxable Amount
        $netTaxableSales = round((float) ($invoice->subtotal - $invoice->discount_amount), 2);
        if ($netTaxableSales > 0) {
            $lines[] = [
                'chart_of_account_id' => $salesRevenue->id,
                'debit'               => 0,
                'credit'              => $netTaxableSales,
                'description'         => "Sales Revenue - Taxable Goods for Invoice {$invoice->invoice_number}",
            ];
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

        try {
            $meta = [
                'tenant_id'             => $tenantId,
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
            return null;
        }
    }

    /**
     * Automatically post balanced COGS Inventory Accounting Journal when a Dispatch Order is shipped/outwarded.
     *
     * Debit:  Cost of Goods Sold (COGS A/c) - Code 5010
     * Credit: Inventory Asset A/c           - Code 1200
     */
    public function postDispatchOrderCogsJournal(\App\Domains\Sales\Models\DispatchOrder $dispatch): ?Journal
    {
        $tenantId = $dispatch->tenant_id ?: (tenant_id() ?? 1);

        // 1. COGS Expense A/c (Code 5010 or subtype cogs)
        $cogsAccount = ChartOfAccount::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('code', '5010')->orWhere('name', 'Cost of Goods Sold');
            })->first() ?? ChartOfAccount::where('tenant_id', $tenantId)->where('subtype', ChartOfAccount::SUBTYPE_COGS)->first();

        // 2. Inventory Asset A/c (Code 1200 or Inventory)
        $inventoryAccount = ChartOfAccount::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('code', '1200')->orWhere('name', 'Inventory');
            })->first() ?? ChartOfAccount::where('tenant_id', $tenantId)->where('type', ChartOfAccount::TYPE_ASSET)->first();

        if (!$cogsAccount || !$inventoryAccount) {
            Log::warning("SalesAccountingService: Missing COGS or Inventory Chart of Accounts for tenant {$tenantId}");
            return null;
        }

        // Check if journal already posted for this Dispatch Order
        $existing = Journal::where('tenant_id', $tenantId)
            ->where('reference_type', 'DispatchOrder')
            ->where('reference_id', $dispatch->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Calculate total cost value of dispatched items
        $totalCogs = 0;
        foreach ($dispatch->items as $item) {
            $qty = (float) ($item->quantity_dispatched > 0 ? $item->quantity_dispatched : $item->quantity_ordered);
            
            // Valuation Cost Price: use product cost_price, purchase_price, or selling_price * 0.6
            $productCost = (float) ($item->product?->cost_price ?? $item->product?->purchase_price ?? 0);
            if ($productCost <= 0) {
                $productCost = round((float) ($item->product?->selling_price ?? 0) * 0.6, 2);
            }

            $lineCogs = round($qty * $productCost, 2);
            $totalCogs += $lineCogs;
        }

        if ($totalCogs <= 0) {
            return null;
        }

        $lines = [
            [
                'chart_of_account_id' => $cogsAccount->id,
                'debit'               => round($totalCogs, 2),
                'credit'              => 0,
                'description'         => "COGS Expense - Goods Outward for Dispatch #{$dispatch->dispatch_number}",
            ],
            [
                'chart_of_account_id' => $inventoryAccount->id,
                'debit'               => 0,
                'credit'              => round($totalCogs, 2),
                'description'         => "Inventory Outward - Stock Shipped for Dispatch #{$dispatch->dispatch_number}",
            ]
        ];

        try {
            $meta = [
                'tenant_id'             => $tenantId,
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
