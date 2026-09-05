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

            // Generate Unique Voucher Number (Collision-Proof across all companies/branches/trashed rows)
            $year = now()->format('Y');
            $prefix = "LCV-{$year}-";

            $allNumbers = DB::table('landed_cost_vouchers')
                ->where('voucher_number', 'like', "{$prefix}%")
                ->pluck('voucher_number');

            $maxNum = 0;
            foreach ($allNumbers as $vNum) {
                $numStr = str_replace($prefix, '', $vNum);
                $n = (int) $numStr;
                if ($n > $maxNum) {
                    $maxNum = $n;
                }
            }

            $nextNum = $maxNum + 1;
            do {
                $voucherNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
                $exists = DB::table('landed_cost_vouchers')->where('voucher_number', $voucherNumber)->exists();
                if ($exists) {
                    $nextNum++;
                }
            } while ($exists);

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
                if (in_array($gstType, ['rcm', 'rcm_cgst_sgst', 'rcm_igst'])) {
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
                $isRcm      = in_array($gstType, ['rcm', 'rcm_cgst_sgst', 'rcm_igst']);
                $isIgst     = in_array($gstType, ['igst', 'rcm_igst']);

                $cgstAmount = $isIgst ? 0 : round($taxAmount / 2, 2);
                $sgstAmount = $isIgst ? 0 : round($taxAmount - $cgstAmount, 2);
                $igstAmount = $isIgst ? round($taxAmount, 2) : 0;
                $grandTotal = $isRcm ? $subtotal : ($subtotal + $taxAmount);

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

                // Dispatch BillPosted event to auto-post GL Journal Entry for the Transporter Bill
                event(new \App\Domains\Purchase\Events\BillPosted($bill));
                app(\App\Domains\Accounting\Listeners\PostPurchaseBillJournal::class)->handle(new \App\Domains\Purchase\Events\BillPosted($bill));
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

                $rcmCgstPayableAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '2086')->first()
                    ?: \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM CGST Payable%')->first();
                $rcmSgstPayableAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '2087')->first()
                    ?: \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM SGST Payable%')->first();
                $rcmIgstPayableAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '2085')->first()
                    ?: \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM IGST Payable%')->first();

                $rcmInputCgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1631')->first()
                    ?: \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM Input CGST%')->first();
                $rcmInputSgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1632')->first()
                    ?: \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM Input SGST%')->first();
                $rcmInputIgstAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1633')->first()
                    ?: \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM Input IGST%')->first();

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
                    $gstType = $exp->gst_type;
                    $isRcm  = (bool) $exp->is_rcm || in_array($gstType, ['rcm', 'rcm_cgst_sgst', 'rcm_igst']);
                    $isIgst = in_array($gstType, ['igst', 'rcm_igst']);

                    if ($taxAmt > 0) {
                        if ($isRcm) {
                            if ($isIgst) {
                                // Debit: RCM Input IGST (1633)
                                $rcmAsset = $rcmInputIgstAcc ?: ($inputIgstAcc ?: $inventoryAcc);
                                if ($rcmAsset) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $rcmAsset->id,
                                        'debit'               => round($taxAmt, 2),
                                        'credit'              => 0,
                                        'description'         => "RCM Input IGST for {$exp->cost_head} ({$voucher->voucher_number})",
                                    ];
                                }
                                // Credit: RCM IGST Payable (2085)
                                $rcmLiab = $rcmIgstPayableAcc ?: $apAccount;
                                if ($rcmLiab) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $rcmLiab->id,
                                        'debit'               => 0,
                                        'credit'              => round($taxAmt, 2),
                                        'description'         => "RCM IGST Liability for {$exp->cost_head} ({$voucher->voucher_number})",
                                    ];
                                }
                            } else {
                                // Intra-State RCM (CGST + SGST)
                                $halfTax = round($taxAmt / 2, 2);
                                $sgstTax = round($taxAmt - $halfTax, 2);

                                // Debit: RCM Input CGST (1631) & RCM Input SGST (1632)
                                $cgstAsset = $rcmInputCgstAcc ?: ($inputCgstAcc ?: $inventoryAcc);
                                if ($cgstAsset) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $cgstAsset->id,
                                        'debit'               => $halfTax,
                                        'credit'              => 0,
                                        'description'         => "RCM Input CGST for {$exp->cost_head} ({$voucher->voucher_number})",
                                    ];
                                }
                                $sgstAsset = $rcmInputSgstAcc ?: ($inputSgstAcc ?: $inventoryAcc);
                                if ($sgstAsset) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $sgstAsset->id,
                                        'debit'               => $sgstTax,
                                        'credit'              => 0,
                                        'description'         => "RCM Input SGST for {$exp->cost_head} ({$voucher->voucher_number})",
                                    ];
                                }

                                // Credit: RCM CGST Payable (2086) & RCM SGST Payable (2087)
                                $cgstLiab = $rcmCgstPayableAcc ?: $apAccount;
                                if ($cgstLiab) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $cgstLiab->id,
                                        'debit'               => 0,
                                        'credit'              => $halfTax,
                                        'description'         => "RCM CGST Liability for {$exp->cost_head} ({$voucher->voucher_number})",
                                    ];
                                }
                                $sgstLiab = $rcmSgstPayableAcc ?: $apAccount;
                                if ($sgstLiab) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $sgstLiab->id,
                                        'debit'               => 0,
                                        'credit'              => $sgstTax,
                                        'description'         => "RCM SGST Liability for {$exp->cost_head} ({$voucher->voucher_number})",
                                    ];
                                }
                            }
                        } else {
                            // Forward Charge Mechanism (FCM)
                            if ($isIgst) {
                                if ($inputIgstAcc) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $inputIgstAcc->id,
                                        'debit'               => round($taxAmt, 2),
                                        'credit'              => 0,
                                        'description'         => "Input IGST for {$exp->cost_head} ({$voucher->voucher_number})",
                                    ];
                                }
                            } else {
                                $halfTax = round($taxAmt / 2, 2);
                                if ($inputCgstAcc) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $inputCgstAcc->id,
                                        'debit'               => $halfTax,
                                        'credit'              => 0,
                                        'description'         => "Input CGST for {$exp->cost_head} ({$voucher->voucher_number})",
                                    ];
                                }
                                if ($inputSgstAcc) {
                                    $journalLines[] = [
                                        'chart_of_account_id' => $inputSgstAcc->id,
                                        'debit'               => round($taxAmt - $halfTax, 2),
                                        'credit'              => 0,
                                        'description'         => "Input SGST for {$exp->cost_head} ({$voucher->voucher_number})",
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
