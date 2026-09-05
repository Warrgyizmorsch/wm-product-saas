<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use App\Domains\Purchase\Models\LandedCostVoucher;
use App\Domains\Purchase\Models\LandedCostReceipt;
use App\Domains\Purchase\Models\LandedCostExpense;
use App\Domains\Purchase\Models\LandedCostItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LandedCostService
{
    public function createVoucher(int $tenantId, array $data): LandedCostVoucher
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $grnIds = $data['grn_ids'] ?? [];
            if (empty($grnIds)) {
                throw new InvalidArgumentException('Please select at least one Goods Receipt Note (GRN).');
            }

            $expensesData = $data['expenses'] ?? [];
            if (empty($expensesData)) {
                throw new InvalidArgumentException('Please add at least one expense line.');
            }

            // Generate Voucher Number
            $year = now()->format('Y');
            $prefix = "LCV-{$year}-";
            $lastVoucher = LandedCostVoucher::where('tenant_id', $tenantId)
                ->where('voucher_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();

            $nextNum = 1;
            if ($lastVoucher) {
                $lastNumStr = str_replace($prefix, '', $lastVoucher->voucher_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $voucherNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $voucher = LandedCostVoucher::create([
                'tenant_id'       => $tenantId,
                'voucher_number'  => $voucherNumber,
                'voucher_date'    => $data['voucher_date'] ?? now()->toDateString(),
                'status'          => 'Draft',
                'total_expenses'  => 0.0000,
                'notes'           => $data['notes'] ?? null,
                'created_by'      => auth()->id() ?: 1,
            ]);

            // Save Receipt links
            foreach ($grnIds as $grnId) {
                LandedCostReceipt::create([
                    'tenant_id'              => $tenantId,
                    'landed_cost_voucher_id' => $voucher->id,
                    'goods_receipt_note_id'  => (int) $grnId,
                ]);
            }

            // Save Expense lines & sum total
            $totalExpenses = 0.0;
            foreach ($expensesData as $exp) {
                $amt = (float) ($exp['amount'] ?? 0);
                if ($amt <= 0) continue;

                $taxRate = (float) ($exp['tax_rate'] ?? 0);
                $gstType = $exp['gst_type'] ?? 'cgst_sgst';
                $isRcm   = !empty($exp['is_rcm']) && ($exp['is_rcm'] === '1' || $exp['is_rcm'] === true || $exp['is_rcm'] === 'true');
                if ($gstType === 'rcm') {
                    $isRcm = true;
                }

                $taxAmount = 0.0;
                if ($taxRate > 0) {
                    $taxAmount = round($amt * ($taxRate / 100), 4);
                }

                // If FCM: total payable = base + tax amount. If RCM: vendor payable = base amount (tax paid to govt).
                $totalWithTax = $isRcm ? $amt : ($amt + $taxAmount);

                $totalExpenses += $amt;
                LandedCostExpense::create([
                    'tenant_id'              => $tenantId,
                    'company_id'             => $voucher->company_id,
                    'branch_id'              => $voucher->branch_id,
                    'landed_cost_voucher_id' => $voucher->id,
                    'cost_head'              => $exp['cost_head'] ?? 'Freight',
                    'vendor_id'              => !empty($exp['vendor_id']) ? (int) $exp['vendor_id'] : null,
                    'amount'                 => $amt,
                    'tax_rate'               => $taxRate,
                    'gst_type'               => $gstType,
                    'is_rcm'                 => $isRcm,
                    'tax_amount'             => $taxAmount,
                    'total_with_tax'         => $totalWithTax,
                    'allocation_basis'       => $exp['allocation_basis'] ?? 'by_qty',
                    'description'            => $exp['description'] ?? null,
                ]);
            }

            $voucher->total_expenses = $totalExpenses;
            $voucher->save();

            // Calculate and create allocated items
            $this->calculateAndCreateItems($voucher, $tenantId, $grnIds);

            return $voucher;
        });
    }

    public function postVoucher(int $tenantId, int $voucherId): LandedCostVoucher
    {
        return DB::transaction(function () use ($tenantId, $voucherId) {
            $voucher = LandedCostVoucher::where('tenant_id', $tenantId)
                ->with(['items.goodsReceiptNote', 'items.product', 'expenses'])
                ->findOrFail($voucherId);

            if ($voucher->status !== 'Draft') {
                throw new InvalidArgumentException("Voucher is already {$voucher->status}.");
            }

            // Track which product+warehouse pairs need warehouse summary recalculation
            $affectedProductWarehouse = [];

            foreach ($voucher->items as $item) {
                $grn = $item->goodsReceiptNote;
                if (!$grn) continue;

                $warehouseId        = $grn->warehouse_id;
                $productId          = $item->product_id;
                $grnId              = $item->goods_receipt_note_id;
                $allocatedCostTotal = (float) $item->allocated_cost; // total extra cost for this GRN item

                if ($allocatedCostTotal <= 0 || !$warehouseId) continue;

                // ── Step 1: Update ONLY the specific GRN's stock_transaction entry ──
                $stockTxn = StockTransaction::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('type', 'IN')
                    ->where('reference_type', 'Purchase Receipt')
                    ->where('reference_id', $grnId)
                    ->first();

                if ($stockTxn) {
                    $batchQty      = (float) $stockTxn->quantity;
                    $perUnitExtra  = $batchQty > 0 ? ($allocatedCostTotal / $batchQty) : 0.0;
                    $newUnitCost   = (float) $stockTxn->unit_cost + $perUnitExtra;
                    $newTotalValue = $newUnitCost * $batchQty;

                    $stockTxn->unit_cost   = $newUnitCost;
                    $stockTxn->total_value = $newTotalValue;
                    $stockTxn->save();
                }

                // Track unique product+warehouse combinations for step 2
                $key = "{$productId}_{$warehouseId}";
                $affectedProductWarehouse[$key] = [
                    'product_id'   => $productId,
                    'warehouse_id' => $warehouseId,
                ];
            }

            // ── Step 2: Recalculate warehouse unit_cost as FIFO weighted average ──
            foreach ($affectedProductWarehouse as $pair) {
                $productId   = $pair['product_id'];
                $warehouseId = $pair['warehouse_id'];

                $inTxns = StockTransaction::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('type', 'IN')
                    ->where('balance_qty', '>', 0)
                    ->get();

                $totalValue = $inTxns->sum(fn($t) => (float) $t->unit_cost * (float) $t->balance_qty);
                $totalQty   = $inTxns->sum(fn($t) => (float) $t->balance_qty);
                $newAvgCost = ($totalQty > 0) ? ($totalValue / $totalQty) : 0.0;

                // Update warehouse stock summary unit_cost
                $stock = ProductWarehouseStock::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if ($stock) {
                    $stock->unit_cost = $newAvgCost;
                    $stock->save();
                }

                // Preserve Product Master Base Purchase Cost benchmark
                $product = Product::find($productId);
                if ($product && !$product->cost_price) {
                    $product->cost_price = $newAvgCost;
                    $product->save();
                }
            }

            // ── Step 3: Auto-generate Vendor Bills for Transporters ──
            $billRepo = app(\App\Domains\Purchase\Repositories\VendorBillRepository::class);
            $vendorExpensesGrouped = $voucher->expenses->whereNotNull('vendor_id')->groupBy('vendor_id');

            foreach ($vendorExpensesGrouped as $vendorId => $expLines) {
                $billNumber = $billRepo->getNextBillNumber($tenantId);
                $subtotal   = (float) $expLines->sum('amount');
                $taxAmount  = (float) $expLines->sum('tax_amount');
                $grandTotal = (float) $expLines->sum('total_with_tax');

                $firstExp   = $expLines->first();
                $gstType    = $firstExp?->gst_type ?? 'cgst_sgst';
                $isIgst     = $gstType === 'igst';

                $cgstAmount = $isIgst ? 0 : round($taxAmount / 2, 2);
                $sgstAmount = $isIgst ? 0 : round($taxAmount / 2, 2);
                $igstAmount = $isIgst ? round($taxAmount, 2) : 0;

                $bill = \App\Domains\Purchase\Models\VendorBill::create([
                    'tenant_id'             => $tenantId,
                    'company_id'            => $voucher->company_id,
                    'branch_id'             => $voucher->branch_id,
                    'bill_number'           => $billNumber,
                    'vendor_invoice_number' => "LCV-INV-{$voucher->voucher_number}",
                    'vendor_id'             => (int) $vendorId,
                    'bill_date'             => $voucher->voucher_date,
                    'due_date'              => $voucher->voucher_date,
                    'status'                => 'Unpaid',
                    'gst_type'              => $gstType,
                    'subtotal'              => $subtotal,
                    'tax_amount'            => $taxAmount,
                    'cgst_amount'           => $cgstAmount,
                    'sgst_amount'           => $sgstAmount,
                    'igst_amount'           => $igstAmount,
                    'grand_total'           => $grandTotal,
                    'paid_amount'           => 0,
                    'due_amount'            => round($grandTotal, 2),
                    'notes'                 => "Auto-generated Service Bill for Landed Cost Voucher {$voucher->voucher_number}",
                    'created_by'            => auth()->id() ?: 1,
                ]);

                foreach ($expLines as $expLine) {
                    $expLine->vendor_bill_id = $bill->id;
                    $expLine->save();

                    \App\Domains\Purchase\Models\VendorBillItem::create([
                        'tenant_id'      => $tenantId,
                        'vendor_bill_id' => $bill->id,
                        'quantity'       => 1,
                        'unit_rate'      => (float) $expLine->amount,
                        'tax_percentage' => (float) $expLine->tax_rate,
                        'total_amount'   => (float) $expLine->total_with_tax,
                    ]);
                }
            }

            $voucher->status       = 'Posted';
            $voucher->posting_date = now()->toDateString();
            $voucher->posted_by    = auth()->id() ?: 1;
            $voucher->posted_at    = now();
            $voucher->save();

            // ── Step 4: Post GL Journal Entry for Landed Cost Revaluation & Transporter AP/GST ──
            try {
                $inventoryAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1200')->first();
                $apAccount    = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '2010')->first();
                $freightAcc   = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '5030')->first()
                    ?: (\App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '5900')->first());

                $inputCgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1610')->first()
                    ?: \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1600')->first();
                $inputSgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1620')->first()
                    ?: \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1600')->first();
                $inputIgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1630')->first()
                    ?: \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1600')->first();

                // RCM input tax credit accounts are distinct from regular Input
                // GST — a reverse-charge purchase self-assesses tax it never paid
                // the vendor, so it can't share the same ledger accounts as
                // ordinary purchase ITC. Fall back to the regular Input accounts
                // only if the tenant hasn't provisioned dedicated RCM accounts.
                $rcmInputCgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1631')->first() ?: $inputCgstAcc;
                $rcmInputSgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1632')->first() ?: $inputSgstAcc;
                $rcmInputIgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1633')->first() ?: $inputIgstAcc;

                // RCM payable accounts (2085/2086/2087) — the self-assessed
                // liability paid in cash, NOT the same as Statutory Dues Payable
                // (2080) or the generic Output Duties header (2100), which the
                // previous lookup incorrectly fell back to.
                $rcmPayableCgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '2086')->first();
                $rcmPayableSgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '2087')->first();
                $rcmPayableIgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '2085')->first();

                $journalLines = [];

                // 1. Debit Inventory for base freight amount allocated to stock
                if ($inventoryAcc && (float)$voucher->total_expenses > 0) {
                    $journalLines[] = [
                        'chart_of_account_id' => $inventoryAcc->id,
                        'debit'               => round((float)$voucher->total_expenses, 2),
                        'credit'              => 0,
                        'description'         => "Landed Cost Revaluation (Inventory) - Voucher {$voucher->voucher_number}",
                    ];
                }

                // 2. Process GST Debits & AP Credits per expense line
                $totalVendorPayable = 0.0;
                $nonVendorExpenseTotal = 0.0;

                foreach ($voucher->expenses as $exp) {
                    $taxAmt = (float) $exp->tax_amount;
                    $isRcm  = (bool) $exp->is_rcm || str_starts_with((string) $exp->gst_type, 'rcm');
                    $gstType = $exp->gst_type;
                    $isIgstType = str_contains((string) $gstType, 'igst');

                    $debitCgstAcc = $isRcm ? $rcmInputCgstAcc : $inputCgstAcc;
                    $debitSgstAcc = $isRcm ? $rcmInputSgstAcc : $inputSgstAcc;
                    $debitIgstAcc = $isRcm ? $rcmInputIgstAcc : $inputIgstAcc;

                    if ($taxAmt > 0) {
                        if ($isIgstType) {
                            if ($debitIgstAcc) {
                                $journalLines[] = [
                                    'chart_of_account_id' => $debitIgstAcc->id,
                                    'debit'               => round($taxAmt, 2),
                                    'credit'              => 0,
                                    'description'         => ($isRcm ? "RCM Input IGST" : "Input IGST") . " for {$exp->cost_head} ({$voucher->voucher_number})",
                                ];
                            }
                        } else {
                            // cgst_sgst (regular or RCM) split
                            $halfTax = round($taxAmt / 2, 2);
                            if ($debitCgstAcc) {
                                $journalLines[] = [
                                    'chart_of_account_id' => $debitCgstAcc->id,
                                    'debit'               => $halfTax,
                                    'credit'              => 0,
                                    'description'         => ($isRcm ? "RCM Input CGST" : "Input CGST") . " for {$exp->cost_head} ({$voucher->voucher_number})",
                                ];
                            }
                            if ($debitSgstAcc) {
                                $journalLines[] = [
                                    'chart_of_account_id' => $debitSgstAcc->id,
                                    'debit'               => round($taxAmt - $halfTax, 2),
                                    'credit'              => 0,
                                    'description'         => ($isRcm ? "RCM Input SGST" : "Input SGST") . " for {$exp->cost_head} ({$voucher->voucher_number})",
                                ];
                            }
                        }

                        // RCM: the buyer self-assesses this tax as a liability
                        // (paid in cash, not to the vendor) rather than crediting
                        // it toward Accounts Payable like normal purchase tax.
                        if ($isRcm) {
                            if ($isIgstType && $rcmPayableIgstAcc) {
                                $journalLines[] = [
                                    'chart_of_account_id' => $rcmPayableIgstAcc->id,
                                    'debit'               => 0,
                                    'credit'              => round($taxAmt, 2),
                                    'description'         => "RCM IGST Payable for {$exp->cost_head} ({$voucher->voucher_number})",
                                ];
                            } elseif (!$isIgstType) {
                                $halfTax = round($taxAmt / 2, 2);
                                if ($rcmPayableCgstAcc) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $rcmPayableCgstAcc->id,
                                        'debit'               => 0,
                                        'credit'              => $halfTax,
                                        'description'         => "RCM CGST Payable for {$exp->cost_head} ({$voucher->voucher_number})",
                                    ];
                                }
                                if ($rcmPayableSgstAcc) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $rcmPayableSgstAcc->id,
                                        'debit'               => 0,
                                        'credit'              => round($taxAmt - $halfTax, 2),
                                        'description'         => "RCM SGST Payable for {$exp->cost_head} ({$voucher->voucher_number})",
                                    ];
                                }
                            }
                        }
                    }

                    if (!empty($exp->vendor_id)) {
                        $totalVendorPayable += (float) $exp->total_with_tax;
                    } else {
                        $nonVendorExpenseTotal += (float) $exp->amount;
                    }
                }

                // 3. Credit Transporter Accounts Payable (2010)
                if ($apAccount && $totalVendorPayable > 0) {
                    $journalLines[] = [
                        'chart_of_account_id' => $apAccount->id,
                        'debit'               => 0,
                        'credit'              => round($totalVendorPayable, 2),
                        'description'         => "Transporter Accounts Payable - Voucher {$voucher->voucher_number}",
                    ];
                }

                // 4. Credit Freight Expense Clearing for non-vendor expenses
                if ($freightAcc && $nonVendorExpenseTotal > 0) {
                    $journalLines[] = [
                        'chart_of_account_id' => $freightAcc->id,
                        'debit'               => 0,
                        'credit'              => round($nonVendorExpenseTotal, 2),
                        'description'         => "Freight Expense Allocation - Voucher {$voucher->voucher_number}",
                    ];
                }

                if (!empty($journalLines)) {
                    $journalService = app(\App\Domains\Accounting\Services\JournalService::class);
                    $journalService->post($journalLines, [
                        'tenant_id'      => $tenantId,
                        'journal_date'   => $voucher->voucher_date,
                        'source'         => \App\Domains\Accounting\Models\Journal::SOURCE_PURCHASE,
                        'reference_type' => 'landed_cost_voucher',
                        'reference_id'   => $voucher->id,
                        'memo'           => "Landed Cost Allocation & Transporter AP Voucher {$voucher->voucher_number}",
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("LandedCostService: GL posting failed for voucher {$voucher->voucher_number}: " . $e->getMessage());
            }

            return $voucher;
        });
    }

    public function cancelVoucher(int $tenantId, int $voucherId): LandedCostVoucher
    {
        return DB::transaction(function () use ($tenantId, $voucherId) {
            $voucher = LandedCostVoucher::where('tenant_id', $tenantId)
                ->with(['items.goodsReceiptNote', 'items.product'])
                ->findOrFail($voucherId);

            if ($voucher->status === 'Cancelled') {
                throw new InvalidArgumentException("Voucher is already Cancelled.");
            }

            if ($voucher->status === 'Posted') {
                // Track affected pairs
                $affectedProductWarehouse = [];

                foreach ($voucher->items as $item) {
                    $grn = $item->goodsReceiptNote;
                    if (!$grn) continue;

                    $warehouseId        = $grn->warehouse_id;
                    $productId          = $item->product_id;
                    $grnId              = $item->goods_receipt_note_id;
                    $allocatedCostTotal = (float) $item->allocated_cost;

                    if ($allocatedCostTotal <= 0 || !$warehouseId) continue;

                    // ── Revert: Remove landed cost from the specific GRN stock_transaction entry ──
                    $stockTxn = StockTransaction::where('tenant_id', $tenantId)
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->where('type', 'IN')
                        ->where('reference_type', 'Purchase Receipt')
                        ->where('reference_id', $grnId)
                        ->first();

                    if ($stockTxn) {
                        $batchQty     = (float) $stockTxn->quantity;
                        $perUnitExtra = $batchQty > 0 ? ($allocatedCostTotal / $batchQty) : 0.0;
                        $revertedCost = max(0.0, (float) $stockTxn->unit_cost - $perUnitExtra);

                        $stockTxn->unit_cost   = $revertedCost;
                        $stockTxn->total_value = $revertedCost * $batchQty;
                        $stockTxn->save();
                    }

                    $key = "{$productId}_{$warehouseId}";
                    $affectedProductWarehouse[$key] = [
                        'product_id'   => $productId,
                        'warehouse_id' => $warehouseId,
                    ];
                }

                // Recalculate warehouse weighted average after revert
                foreach ($affectedProductWarehouse as $pair) {
                    $productId   = $pair['product_id'];
                    $warehouseId = $pair['warehouse_id'];

                    $inTxns = StockTransaction::where('tenant_id', $tenantId)
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->where('type', 'IN')
                        ->where('balance_qty', '>', 0)
                        ->get();

                    $totalValue = $inTxns->sum(fn($t) => (float) $t->unit_cost * (float) $t->balance_qty);
                    $totalQty   = $inTxns->sum(fn($t) => (float) $t->balance_qty);
                    $newAvgCost = ($totalQty > 0) ? ($totalValue / $totalQty) : 0.0;

                    $stock = ProductWarehouseStock::where('tenant_id', $tenantId)
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->first();

                    if ($stock) {
                        $stock->unit_cost = $newAvgCost;
                        $stock->save();
                    }

                    $product = Product::find($productId);
                    if ($product) {
                        $product->unit_cost  = $newAvgCost;
                        $product->cost_price = $newAvgCost;
                        $product->save();
                    }
                }
            }

            $voucher->status = 'Cancelled';
            $voucher->save();

            return $voucher;
        });
    }

    private function calculateAndCreateItems(LandedCostVoucher $voucher, int $tenantId, array $grnIds): void
    {
        $grnItems = GoodsReceiptNoteItem::whereIn('goods_receipt_note_id', $grnIds)
            ->with(['goodsReceiptNote', 'product'])
            ->get();

        if ($grnItems->isEmpty()) {
            return;
        }

        $totalQty    = (float) $grnItems->sum('received_qty');
        $totalAmount = (float) $grnItems->sum('total_amount');

        $expenses = $voucher->expenses;

        foreach ($grnItems as $item) {
            $qty       = (float) $item->received_qty;
            $unitRate  = (float) $item->unit_rate;
            $baseTotal = (float) $item->total_amount;

            $allocatedCost = 0.0;

            foreach ($expenses as $exp) {
                $expAmount = (float) $exp->amount;
                $basis     = $exp->allocation_basis;

                if ($basis === 'by_amount' && $totalAmount > 0) {
                    $allocatedCost += ($baseTotal / $totalAmount) * $expAmount;
                } elseif ($basis === 'equal' && count($grnItems) > 0) {
                    $allocatedCost += $expAmount / count($grnItems);
                } else {
                    // Default: by_qty
                    if ($totalQty > 0) {
                        $allocatedCost += ($qty / $totalQty) * $expAmount;
                    }
                }
            }

            $newTotalAmount    = $baseTotal + $allocatedCost;
            $newLandedUnitCost = $qty > 0 ? ($newTotalAmount / $qty) : $unitRate;

            LandedCostItem::create([
                'tenant_id'                   => $tenantId,
                'landed_cost_voucher_id'      => $voucher->id,
                'goods_receipt_note_id'       => $item->goods_receipt_note_id,
                'goods_receipt_note_item_id'  => $item->id,
                'product_id'                  => $item->product_id,
                'quantity'                    => $qty,
                'base_unit_rate'              => $unitRate,
                'base_total_amount'           => $baseTotal,
                'allocated_cost'              => $allocatedCost,
                'new_landed_unit_cost'        => $newLandedUnitCost,
                'new_total_amount'            => $newTotalAmount,
            ]);
        }
    }

    public function previewGrnItems(int $tenantId, array $grnIds): array
    {
        $grnItems = GoodsReceiptNoteItem::whereIn('goods_receipt_note_id', $grnIds)
            ->with(['goodsReceiptNote', 'product.uom'])
            ->get();

        $items = [];
        foreach ($grnItems as $item) {
            $items[] = [
                'grn_id'       => $item->goods_receipt_note_id,
                'grn_number'   => $item->goodsReceiptNote->grn_number,
                'product_id'   => $item->product_id,
                'product_name' => $item->product->name,
                'sku'          => $item->product->sku ?: 'No SKU',
                'uom'          => $item->product->uom->code ?? 'PCS',
                'received_qty' => (float) $item->received_qty,
                'unit_rate'    => (float) $item->unit_rate,
                'total_amount' => (float) $item->total_amount,
            ];
        }

        return $items;
    }
}
