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
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorBillService
{
    public function __construct(
        protected VendorBillRepository $billRepo,
        protected JournalService $journalService,
        protected ChartOfAccountRepositoryInterface $accountRepo,
        protected ThreeWayMatchService $matcher,
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
            $rawFreight = $validated['freight_amount'] ?? null;
            $freightAmount = $isFreightBilledOnInvoice ? ($rawFreight !== null ? convert_to_base((float)$rawFreight) : (float)($po?->freight_amount ?? 0)) : 0.0;
            $freightTaxPercent = $isFreightBilledOnInvoice ? (float)($validated['freight_tax_percent'] ?? 18.0) : 0.0;
            $orderTaxPercent = (float)($validated['order_tax_percent'] ?? $validated['tax_rate'] ?? 0);

            $subtotal = 0;
            $sumItemDiscounts = 0;
            $sumItemTaxes = 0;

            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $qty = (float)$item['quantity'];
                $price = convert_to_base((float)($item['unit_price'] ?? $item['unit_rate'] ?? 0));
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
                $discountAmount = convert_to_base((float)($validated['discount_amount'] ?? 0));
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
                    $igstAmount = round($taxAmount, 4);
                } else {
                    $cgstAmount = round($taxAmount / 2, 4);
                    $sgstAmount = round($taxAmount - $cgstAmount, 4);
                }
            }

            $adjustment = convert_to_base((float) ($validated['adjustment'] ?? 0));
            $grandTotal = max(0, $grossBeforeTax + $freightAmount + $taxAmount + $adjustment);

            // Calculate Landed Cost Revaluation Data and update Stock Valuation
            $revaluationData = null;
            $isCapitalizeAllocation = !in_array($freightAllocationMethod, ['none', 'direct_expense']);
            $hasFreightRevaluation = $isFreightBilledOnInvoice && $freightAmount > 0 && $isCapitalizeAllocation;

            $revaluationItems = [];
            $affectedProductWarehouse = [];
            $processedTxnIds = [];

            // Pre-load all products in a single batch query to prevent N+1 queries for large bills (100-300 items)
            $allProductIds = array_filter(array_map(function ($row) use ($po) {
                $item = $row['item'];
                $poItem = $po ? $po->items->firstWhere('id', $item['purchase_order_item_id'] ?? null) : null;
                return $item['product_id'] ?? $poItem?->product_id;
            }, $itemsData));
            $productsById = !empty($allProductIds) ? Product::whereIn('id', $allProductIds)->get()->keyBy('id') : collect();

            // Pre-load all GRN transactions in 1 query if linked to GRN
            $grnTxnsByProduct = collect();
            if ($grn) {
                $grnTxnsByProduct = StockTransaction::where('tenant_id', $tenantId)
                    ->where('warehouse_id', $grn->warehouse_id)
                    ->where('type', 'IN')
                    ->where('reference_type', 'Purchase Receipt')
                    ->where('reference_id', $grn->id)
                    ->get()
                    ->groupBy('product_id');
            }

            foreach ($itemsData as $row) {
                $item = $row['item'];
                $poItem = $po ? $po->items->firstWhere('id', $item['purchase_order_item_id'] ?? null) : null;
                $productId = $item['product_id'] ?? $poItem?->product_id;
                $product = $productId ? ($productsById[$productId] ?? null) : null;

                $itemNetSub = max(0, $row['line_subtotal'] - $row['disc_amt']);
                $freightShare = 0.0;
                if ($hasFreightRevaluation) {
                    if ($freightAllocationMethod === 'by_quantity' && $totalQtySum > 0) {
                        $freightShare = round($freightAmount * ($row['qty'] / $totalQtySum), 4);
                    } else {
                        // by_amount (default)
                        $freightShare = ($grossBeforeTax > 0) ? round($freightAmount * ($itemNetSub / $grossBeforeTax), 4) : 0;
                    }
                }

                $freightPerUnit = ($row['qty'] > 0 && $hasFreightRevaluation) ? round($freightShare / $row['qty'], 4) : 0;
                $baseUnitCost = $row['price'];
                $newLandedCost = round($baseUnitCost + $freightPerUnit, 4);

                if ($hasFreightRevaluation) {
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
                }

                // 1. Update matching StockTransaction for this GRN and product only if cost changed or freight applied
                if ($product && $grn && isset($grnTxnsByProduct[$productId])) {
                    $availableTxns = $grnTxnsByProduct[$productId]->reject(fn($t) => in_array($t->id, $processedTxnIds));
                    
                    $matchedTxn = null;
                    if ($availableTxns->count() === 1) {
                        $matchedTxn = $availableTxns->first();
                    } else {
                        $matchedTxn = $availableTxns->first(fn($t) => abs((float)$t->quantity - (float)$row['qty']) < 0.001) ?? $availableTxns->first();
                    }

                    if ($matchedTxn) {
                        $processedTxnIds[] = $matchedTxn->id;
                        // Only perform update and queue for avg cost recalculation if the cost actually changed!
                        if (abs((float)$matchedTxn->unit_cost - $newLandedCost) > 0.0001) {
                            $matchedTxn->unit_cost = $newLandedCost;
                            $matchedTxn->total_value = round($newLandedCost * (float)$matchedTxn->quantity, 4);
                            $matchedTxn->save();

                            $key = "{$productId}_{$grn->warehouse_id}";
                            $affectedProductWarehouse[$key] = [
                                'product_id'   => $productId,
                                'warehouse_id' => $grn->warehouse_id,
                            ];
                        }
                    }
                }
            }

            // 2. Recalculate warehouse stock unit_cost only for products whose cost actually changed
            if (!empty($affectedProductWarehouse)) {
                $affectedProductIds = array_column($affectedProductWarehouse, 'product_id');
                
                $activeInTxns = StockTransaction::where('tenant_id', $tenantId)
                    ->whereIn('product_id', $affectedProductIds)
                    ->where('warehouse_id', $grn->warehouse_id)
                    ->where('type', 'IN')
                    ->where('balance_qty', '>', 0)
                    ->get()
                    ->groupBy('product_id');

                $warehouseStocks = ProductWarehouseStock::where('tenant_id', $tenantId)
                    ->whereIn('product_id', $affectedProductIds)
                    ->where('warehouse_id', $grn->warehouse_id)
                    ->get()
                    ->keyBy('product_id');

                foreach ($affectedProductWarehouse as $pair) {
                    $pId = $pair['product_id'];
                    $wId = $pair['warehouse_id'];

                    $inTxns = $activeInTxns[$pId] ?? collect();
                    $totalVal = $inTxns->sum(fn($t) => (float)$t->unit_cost * (float)$t->balance_qty);
                    $totalQty = $inTxns->sum(fn($t) => (float)$t->balance_qty);
                    $newAvgCost = ($totalQty > 0) ? round($totalVal / $totalQty, 4) : 0.0;

                    if (isset($warehouseStocks[$pId])) {
                        $whStock = $warehouseStocks[$pId];
                        $whStock->unit_cost = $newAvgCost;
                        $whStock->save();
                    }

                    $prod = $productsById[$pId] ?? null;
                    if ($prod && !$prod->cost_price) {
                        $prod->cost_price = $newAvgCost;
                        $prod->save();
                    }
                }
            }

            if ($hasFreightRevaluation) {
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
                'adjustment'                   => $adjustment,
                'grand_total'                  => $grandTotal,
                'paid_amount'                  => 0,
                'due_amount'                   => round($grandTotal, 4),
                'notes'                        => $validated['notes'] ?? null,
                'created_by'                   => auth()->id() ?: 1,
            ]);

            foreach ($itemsData as $row) {
                $item = $row['item'];
                $poItem = $po ? $po->items->firstWhere('id', $item['purchase_order_item_id'] ?? null) : null;
                $productId = $item['product_id'] ?? $poItem?->product_id;

                $itemNetSub = max(0, $row['line_subtotal'] - $row['disc_amt']);
                $itemTaxRate = ($taxType === 'order_wise_tax') ? $orderTaxPercent : $row['tax_rate'];

                $itemFreightShare = 0.0;
                if ($isFreightBilledOnInvoice && $freightAmount > 0) {
                    if ($freightAllocationMethod === 'by_quantity' && $totalQtySum > 0) {
                        $itemFreightShare = round($freightAmount * ($row['qty'] / $totalQtySum), 4);
                    } else {
                        $itemFreightShare = ($grossBeforeTax > 0) ? round($freightAmount * ($itemNetSub / $grossBeforeTax), 4) : 0;
                    }
                }

                $lineTaxableBase = $itemNetSub + $itemFreightShare;
                $lineTax = $lineTaxableBase * ($itemTaxRate / 100);
                $finalLineTotal = round($lineTaxableBase + $lineTax, 4);

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

            // 3-way match (PO ↔ GRN ↔ bill). In 'hold' mode a bill with
            // exceptions stays On Hold: no GL posting until it is released.
            if ($this->matcher->apply($bill)) {
                return $bill;
            }

            // Dispatch BillPosted event to automatically trigger Accounting GL Journal Posting
            event(new \App\Domains\Purchase\Events\BillPosted($bill));
            app(\App\Domains\Accounting\Listeners\PostPurchaseBillJournal::class)->handle(new \App\Domains\Purchase\Events\BillPosted($bill));

            return $bill;
        });
    }

    public function getAvailableVendorAdvance(int $vendorId, int $tenantId, ?int $purchaseOrderId = null): float
    {
        // Self-heal: ensure any existing PurchaseAdvancePayment records are mirrored in VendorPayment
        $missingPoAdvances = PurchaseAdvancePayment::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendorId)
            ->whereNotIn('payment_number', function ($q) use ($tenantId) {
                $q->select('payment_number')->from('vendor_payments')->where('tenant_id', $tenantId);
            })
            ->get();

        foreach ($missingPoAdvances as $poAdv) {
            VendorPayment::create([
                'tenant_id'         => $tenantId,
                'company_id'        => $poAdv->company_id,
                'branch_id'         => $poAdv->branch_id,
                'payment_number'    => $poAdv->advance_number,
                'vendor_id'         => $poAdv->vendor_id,
                'purchase_order_id' => $poAdv->purchase_order_id,
                'payment_type'      => 'Advance',
                'payment_method'    => $poAdv->payment_method,
                'payment_date'      => $poAdv->payment_date,
                'amount'            => $poAdv->amount,
                'reference_number'  => $poAdv->reference_number,
                'status'            => 'Posted',
                'notes'             => $poAdv->notes,
                'created_by'        => $poAdv->created_by,
            ]);
        }

        $advancesQuery = VendorPayment::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendorId)
            ->where('payment_type', 'Advance')
            ->where('status', '!=', 'cancelled');

        if ($purchaseOrderId) {
            $advancesQuery->where(function ($q) use ($purchaseOrderId) {
                $q->where('purchase_order_id', $purchaseOrderId)
                  ->orWhereNull('purchase_order_id');
            });
        }

        $advances = $advancesQuery->get();

        $totalAvailable = 0.0;
        foreach ($advances as $adv) {
            $allocated = (float) $adv->allocations()->sum('allocated_amount');
            $remaining = max(0.0, (float) $adv->amount - $allocated);
            $totalAvailable += $remaining;
        }

        return round($totalAvailable, 4);
    }

    public function applyAdvanceCredit(VendorBill $bill, int $tenantId): bool
    {
        if ($bill->isOnHold()) {
            throw new \InvalidArgumentException("Bill {$bill->bill_number} is on hold for a PO/GRN mismatch. Release it before applying advance.");
        }

        return DB::transaction(function () use ($bill, $tenantId) {
            $due = (float) $bill->due_amount;
            if ($due <= 0.001) {
                return false;
            }

            $poId = $bill->purchase_order_id ?? $bill->goodsReceiptNote?->purchase_order_id;
            $advAvailable = $this->getAvailableVendorAdvance($bill->vendor_id, $tenantId, $poId);
            if ($advAvailable <= 0.001) {
                return false;
            }

            $amountToApply = min($due, $advAvailable);
            $remainingToAllocate = $amountToApply;

            $advancesQuery = VendorPayment::where('tenant_id', $tenantId)
                ->where('vendor_id', $bill->vendor_id)
                ->where('payment_type', 'Advance')
                ->where('status', '!=', 'cancelled');

            if ($poId) {
                $advances = $advancesQuery->where(function ($q) use ($poId) {
                        $q->where('purchase_order_id', $poId)
                          ->orWhereNull('purchase_order_id');
                    })
                    ->orderByRaw("CASE WHEN purchase_order_id = ? THEN 0 ELSE 1 END", [$poId])
                    ->orderBy('payment_date')
                    ->orderBy('id')
                    ->get();
            } else {
                $advances = $advancesQuery->orderBy('payment_date')->orderBy('id')->get();
            }

            foreach ($advances as $adv) {
                if ($remainingToAllocate <= 0.001) {
                    break;
                }

                $alreadyAllocated = (float) $adv->allocations()->sum('allocated_amount');
                $unallocated = max(0.0, (float) $adv->amount - $alreadyAllocated);

                if ($unallocated <= 0.001) {
                    continue;
                }

                $chunk = min($remainingToAllocate, $unallocated);

                VendorPaymentAllocation::create([
                    'tenant_id'         => $tenantId,
                    'company_id'        => $bill->company_id,
                    'branch_id'         => $bill->branch_id,
                    'vendor_payment_id' => $adv->id,
                    'vendor_bill_id'    => $bill->id,
                    'allocated_amount'  => $chunk,
                ]);

                $remainingToAllocate -= $chunk;
            }

            $newPaid = round((float)($bill->paid_amount ?? 0) + $amountToApply, 4);
            $billTotal = (float)($bill->grand_total ?: $bill->total_amount);
            $newDue = max(0.0, round($billTotal - $newPaid, 4));
            $status = ($newDue <= 0.0001) ? 'Paid' : 'Partially Paid';

            $bill->update([
                'paid_amount' => $newPaid,
                'due_amount'  => $newDue,
                'status'      => $status,
            ]);

            $this->postAdvanceSettlementJournal($bill, $amountToApply, $tenantId);

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
                    'posted_by'      => auth()->id(),
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
