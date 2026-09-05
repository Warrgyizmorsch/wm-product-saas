<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorBillItem;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\VendorPayment;
use App\Domains\Purchase\Models\VendorPaymentAllocation;
use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Purchase\Repositories\VendorBillRepository;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Models\Journal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorBillService
{
    public function __construct(
        protected VendorBillRepository $billRepo,
        protected JournalService $journalService,
        protected ChartOfAccountRepositoryInterface $accountRepo
    ) {}

    public function storeBill(array $validated, int $tenantId): VendorBill
    {
        $po = !empty($validated['purchase_order_id']) ? PurchaseOrder::find($validated['purchase_order_id']) : null;
        $grn = !empty($validated['goods_receipt_note_id']) ? GoodsReceiptNote::find($validated['goods_receipt_note_id']) : null;

        $vendorId = $validated['vendor_id'] ?? $po?->vendor_id ?? $grn?->vendor_id;

        return DB::transaction(function () use ($validated, $po, $grn, $vendorId, $tenantId) {
            $billNumber = $this->billRepo->getNextBillNumber($tenantId);

            $discountType = $validated['discount_type'] ?? $po?->discount_type ?? 'without_discount';
            
            $hasItemTax = false;
            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $it) {
                    if ((float)($it['tax_rate'] ?? $it['tax_percentage'] ?? 0) > 0) {
                        $hasItemTax = true;
                        break;
                    }
                }
            }
            $taxType = $validated['tax_type'] ?? $po?->tax_type ?? ($hasItemTax ? 'item_wise_tax' : 'order_wise_tax');
            $gstType = $validated['gst_type'] ?? $po?->gst_type ?? 'cgst_sgst';
            $freightTerms = $validated['freight_terms'] ?? $po?->freight_terms ?? 'to_pay';
            $freightAllocationMethod = $validated['freight_allocation_method'] ?? 'by_amount';

            // Freight Terms Logic: "to_pay" means Freight is paid to 3rd party Transporter via Landed Cost Voucher.
            // Therefore, Freight Amount MUST NOT be billed on Material Vendor Bill!
            $isFreightBilledOnInvoice = in_array($freightTerms, ['to_be_billed', 'prepaid']);
            $freightAmount = $isFreightBilledOnInvoice ? (float)($validated['freight_amount'] ?? $po?->freight_amount ?? 0) : 0.0;
            $freightTaxPercent = $isFreightBilledOnInvoice ? (float)($validated['freight_tax_percent'] ?? 18.0) : 0.0;
            $orderTaxPercent = (float)($validated['order_tax_percent'] ?? $validated['tax_rate'] ?? 0);

            $subtotal = 0;
            $sumItemDiscounts = 0;
            $sumItemTaxes = 0;

            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $qty = (float)$item['quantity'];
                $price = (float)($item['unit_price'] ?? $item['unit_rate'] ?? 0);
                $discPct = (float)($item['discount_percent'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? $item['tax_percentage'] ?? 0);

                $lineSubtotal = $qty * $price;
                $lineDisc = ($discountType === 'item_wise') ? ($lineSubtotal * ($discPct / 100)) : 0;
                $lineNetSubtotal = max(0, $lineSubtotal - $lineDisc);

                $lineTax = ($taxType === 'item_wise_tax') ? ($lineNetSubtotal * ($taxRate / 100)) : 0;

                $subtotal += $lineSubtotal;
                $sumItemDiscounts += $lineDisc;
                $sumItemTaxes += $lineTax;

                $itemsData[] = [
                    'item'            => $item,
                    'qty'             => $qty,
                    'price'           => $price,
                    'disc_pct'        => $discPct,
                    'disc_amt'        => $lineDisc,
                    'tax_rate'        => $taxRate,
                    'line_subtotal'   => $lineSubtotal,
                    'line_tax'        => $lineTax,
                    'line_total'      => $lineNetSubtotal + $lineTax,
                ];
            }

            if ($discountType === 'order_wise') {
                $discountAmount = (float)($validated['discount_amount'] ?? 0);
            } elseif ($discountType === 'item_wise') {
                $discountAmount = $sumItemDiscounts;
            } else {
                $discountAmount = 0;
            }

            $grossBeforeTax = max(0, $subtotal - $discountAmount);
            $totalQtySum = array_sum(array_column($itemsData, 'qty'));

            $freightTaxMethod = $validated['freight_tax_method'] ?? 'highest_rate';

            if ($taxType === 'without_tax' || !$isFreightBilledOnInvoice || $freightAmount <= 0) {
                $itemsTaxAmount = 0;
                $freightTaxAmount = 0;
                if ($taxType === 'order_wise_tax') {
                    $itemsTaxAmount = $grossBeforeTax * ($orderTaxPercent / 100);
                } elseif ($taxType === 'item_wise_tax') {
                    $itemsTaxAmount = $sumItemTaxes;
                }
            } else {
                if ($taxType === 'order_wise_tax') {
                    $itemsTaxAmount = $grossBeforeTax * ($orderTaxPercent / 100);
                } else {
                    $itemsTaxAmount = $sumItemTaxes;
                }

                if ($freightTaxMethod === 'pro_rata' && $grossBeforeTax > 0) {
                    $freightTaxAmount = 0.0;
                    foreach ($itemsData as $itemInfo) {
                        $itemNetSub = max(0, $itemInfo['line_subtotal'] - $itemInfo['disc_amt']);
                        $ratio = $itemNetSub / $grossBeforeTax;
                        $itemFreightShare = $freightAmount * $ratio;
                        $itemTaxRate = ($taxType === 'item_wise_tax') ? $itemInfo['tax_rate'] : $orderTaxPercent;
                        $freightTaxAmount += $itemFreightShare * ($itemTaxRate / 100);
                    }
                } elseif ($freightTaxMethod === 'manual') {
                    $freightTaxAmount = $freightAmount * ($freightTaxPercent / 100);
                } else {
                    // highest_rate (default)
                    $maxTaxRate = 0.0;
                    if ($taxType === 'order_wise_tax') {
                        $maxTaxRate = $orderTaxPercent;
                    } else {
                        foreach ($itemsData as $itemInfo) {
                            if ($itemInfo['tax_rate'] > $maxTaxRate) {
                                $maxTaxRate = $itemInfo['tax_rate'];
                            }
                        }
                    }
                    if ($maxTaxRate <= 0) $maxTaxRate = 18.0;
                    $freightTaxAmount = $freightAmount * ($maxTaxRate / 100);
                }
            }

            $taxAmount = $itemsTaxAmount + $freightTaxAmount;

            $cgstAmount = 0.0;
            $sgstAmount = 0.0;
            $igstAmount = 0.0;

            if ($taxAmount > 0) {
                if ($gstType === 'igst') {
                    $igstAmount = round($taxAmount, 2);
                } else {
                    $cgstAmount = round($taxAmount / 2, 2);
                    $sgstAmount = round($taxAmount - $cgstAmount, 2);
                }
            }

            $grandTotal = max(0, $grossBeforeTax + $freightAmount + $taxAmount);

            // Calculate Landed Cost Revaluation Data for "to_be_billed" mode
            $revaluationData = null;
            $isCapitalizeAllocation = !in_array($freightAllocationMethod, ['none', 'direct_expense']);
            if ($isFreightBilledOnInvoice && $freightAmount > 0 && $isCapitalizeAllocation) {
                $revaluationItems = [];
                foreach ($itemsData as $row) {
                    $item = $row['item'];
                    $poItem = $po ? $po->items->firstWhere('id', $item['purchase_order_item_id'] ?? null) : null;
                    $productId = $item['product_id'] ?? $poItem?->product_id;
                    $product = $productId ? \App\Domains\Inventory\Models\Product::find($productId) : null;

                    $itemNetSub = max(0, $row['line_subtotal'] - $row['disc_amt']);
                    if ($freightAllocationMethod === 'by_quantity' && $totalQtySum > 0) {
                        $freightShare = round($freightAmount * ($row['qty'] / $totalQtySum), 2);
                    } else {
                        // by_amount (default)
                        $freightShare = ($grossBeforeTax > 0) ? round($freightAmount * ($itemNetSub / $grossBeforeTax), 2) : 0;
                    }

                    $freightPerUnit = ($row['qty'] > 0) ? round($freightShare / $row['qty'], 2) : 0;
                    $baseUnitCost = $row['price'];
                    $newLandedCost = round($baseUnitCost + $freightPerUnit, 2);

                    $revaluationItems[] = [
                        'product_id' => $productId,
                        'product_name' => $product?->name ?? 'Item #' . $productId,
                        'sku' => $product?->sku ?? 'SKU-N/A',
                        'quantity' => $row['qty'],
                        'base_unit_cost' => $baseUnitCost,
                        'freight_share' => $freightShare,
                        'freight_per_unit' => $freightPerUnit,
                        'new_landed_cost' => $newLandedCost,
                    ];

                    // Perform Stock Valuation Revaluation in warehouse stock ONLY
                    if ($product && $grn) {
                        $whStock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('product_id', $product->id)
                            ->where('warehouse_id', $grn->warehouse_id)
                            ->first();

                        if ($whStock) {
                            $whStock->unit_cost = $newLandedCost;
                            $whStock->save();
                        }
                    }
                }

                $revaluationData = [
                    'mode' => $freightTerms,
                    'allocation_method' => $freightAllocationMethod,
                    'total_base_freight' => $freightAmount,
                    'total_freight_tax' => $freightTaxAmount,
                    'revaluation_items' => $revaluationItems,
                ];
            }

            $bill = $this->billRepo->create([
                'tenant_id'                    => $tenantId,
                'bill_number'                  => $billNumber,
                'vendor_invoice_number'        => $validated['vendor_invoice_number'] ?? $validated['vendor_bill_number'] ?? null,
                'discount_type'                => $discountType,
                'tax_type'                     => $taxType,
                'discount_amount'              => $discountAmount,
                'gst_type'                     => $gstType,
                'freight_terms'                => $freightTerms,
                'freight_amount'               => $freightAmount,
                'freight_tax_method'           => $freightTaxMethod,
                'freight_allocation_method'    => $freightAllocationMethod,
                'landed_cost_revaluation_data' => $revaluationData,
                'cgst_amount'                  => $cgstAmount,
                'sgst_amount'                  => $sgstAmount,
                'igst_amount'                  => $igstAmount,
                'goods_receipt_note_id'        => $grn?->id,
                'purchase_order_id'            => $po?->id,
                'vendor_id'                    => $vendorId,
                'bill_date'                    => $validated['bill_date'],
                'due_date'                     => $validated['due_date'],
                'status'                       => 'Unpaid',
                'subtotal'                     => $subtotal,
                'tax_amount'                   => $taxAmount,
                'grand_total'                  => $grandTotal,
                'paid_amount'                  => 0,
                'due_amount'                   => round($grandTotal, 2),
                'notes'                        => $validated['notes'] ?? null,
                'created_by'                   => auth()->id() ?: 1,
            ]);

            foreach ($itemsData as $row) {
                $item = $row['item'];
                $poItem = $po ? $po->items->firstWhere('id', $item['purchase_order_item_id'] ?? null) : null;
                $productId = $item['product_id'] ?? $poItem?->product_id;

                $itemNetSub = max(0, $row['line_subtotal'] - $row['disc_amt']);
                $itemTaxRate = ($taxType === 'order_wise_tax') ? $orderTaxPercent : $row['tax_rate'];

                $finalLineTotal = $row['line_total'];
                if ($freightTaxMethod === 'pro_rata' && $isFreightBilledOnInvoice && $freightAmount > 0 && $grossBeforeTax > 0) {
                    $ratio = $itemNetSub / $grossBeforeTax;
                    $itemFreightShare = $freightAmount * $ratio;
                    $lineTaxWithFreight = ($itemNetSub + $itemFreightShare) * ($itemTaxRate / 100);
                    $finalLineTotal = round($itemNetSub + $itemFreightShare + $lineTaxWithFreight, 2);
                }

                VendorBillItem::create([
                    'tenant_id'                  => $tenantId,
                    'vendor_bill_id'             => $bill->id,
                    'product_id'                 => $productId,
                    'goods_receipt_note_item_id' => $item['goods_receipt_note_item_id'] ?? null,
                    'quantity'                   => $row['qty'],
                    'unit_rate'                  => $row['price'],
                    'tax_percentage'             => $itemTaxRate,
                    'total_amount'               => $finalLineTotal,
                ]);
            }

            // Dispatch BillPosted event to automatically trigger Accounting GL Journal Posting
            event(new \App\Domains\Purchase\Events\BillPosted($bill));
            app(\App\Domains\Accounting\Listeners\PostPurchaseBillJournal::class)->handle(new \App\Domains\Purchase\Events\BillPosted($bill));

            return $bill;
        });
    }

    public function getAvailableVendorAdvance(int $vendorId, int $tenantId): float
    {
        $directAdvances = VendorPayment::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendorId)
            ->where('payment_type', 'Advance')
            ->sum('amount');

        $poAdvances = PurchaseAdvancePayment::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendorId)
            ->sum('amount');

        $usedAsAdvance = VendorPaymentAllocation::where('tenant_id', $tenantId)
            ->whereHas('vendorPayment', function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId)
                  ->where('payment_type', 'Advance');
            })
            ->sum('allocated_amount');

        return max(0.0, round(($directAdvances + $poAdvances) - $usedAsAdvance, 2));
    }

    public function applyAdvanceCredit(VendorBill $bill, int $tenantId): bool
    {
        return DB::transaction(function () use ($bill, $tenantId) {
            $due = (float) $bill->due_amount;
            if ($due <= 0) {
                return false;
            }

            $advAvailable = $this->getAvailableVendorAdvance($bill->vendor_id, $tenantId);
            if ($advAvailable <= 0) {
                return false;
            }

            $apply = min($due, $advAvailable);
            $newPaid = (float)($bill->paid_amount ?? 0) + $apply;
            $billTotal = (float)($bill->grand_total ?: $bill->total_amount);
            $newDue = max(0.0, round($billTotal - $newPaid, 2));
            $status = ($newDue <= 0.001) ? 'Paid' : 'Partially Paid';

            $bill->update([
                'paid_amount' => $newPaid,
                'due_amount'  => $newDue,
                'status'      => $status,
            ]);

            $advPayment = VendorPayment::where('tenant_id', $tenantId)
                ->where('vendor_id', $bill->vendor_id)
                ->where('payment_type', 'Advance')
                ->latest()
                ->first();

            if ($advPayment) {
                VendorPaymentAllocation::create([
                    'tenant_id'         => $tenantId,
                    'vendor_payment_id' => $advPayment->id,
                    'vendor_bill_id'    => $bill->id,
                    'allocated_amount'  => $apply,
                ]);
            }

            $this->postAdvanceSettlementJournal($bill, $apply, $tenantId);

            return true;
        });
    }

    protected function postAdvanceSettlementJournal(VendorBill $bill, float $appliedAmount, int $tenantId): void
    {
        try {
            $payableAccount = $this->accountRepo->findByCode('2010', $tenantId)
                ?? $this->accountRepo->getByType('liability')->first();

            $advanceAccount = $this->accountRepo->findByCode('1410', $tenantId)
                ?? $this->accountRepo->findByCode('1400', $tenantId)
                ?? $this->accountRepo->getByType('asset')->last();

            if ($payableAccount && $advanceAccount) {
                $vendorName = $bill->vendor?->name ?? ('Vendor #' . $bill->vendor_id);
                $lines = [
                    [
                        'chart_of_account_id' => $payableAccount->id,
                        'debit'               => (float)$appliedAmount,
                        'description'         => "Bill Settlement via Advance Credit ({$bill->bill_number})",
                    ],
                    [
                        'chart_of_account_id' => $advanceAccount->id,
                        'credit'              => (float)$appliedAmount,
                        'description'         => "Advance Credit Applied for {$vendorName}",
                    ],
                ];

                $this->journalService->post($lines, [
                    'tenant_id'      => $tenantId,
                    'journal_date'   => now(),
                    'source'         => Journal::SOURCE_PURCHASE,
                    'reference_type' => 'vendor_bill_advance_settlement',
                    'reference_id'   => $bill->id,
                    'memo'           => "Advance Credit Adjustment of ₹{$appliedAmount} for Bill {$bill->bill_number}",
                    'posted_by'      => auth()->id() ?: 1,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('VendorBillService: Failed to post advance settlement journal', [
                'bill_id' => $bill->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
