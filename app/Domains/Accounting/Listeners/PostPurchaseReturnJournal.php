<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Domains\Purchase\Events\PurchaseReturnApproved;
use App\Domains\Purchase\Models\LandedCostItem;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorBillItem;
use Illuminate\Support\Facades\Log;

class PostPurchaseReturnJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
        private readonly PostingFailureRecorder $failures,
    ) {
    }

    public function handle(PurchaseReturnApproved $event): void
    {
        $return = $event->purchaseReturn;

        if ($this->journals->findByReference('purchase_return', $return->id)->isNotEmpty()) {
            return;
        }

        $tenantId = $return->tenant_id ?: (tenant_id() ?? 1);

        try {
            // Chart of Account lookups
            $accountsPayable = $this->accounts->findByCode('2010', $tenantId);
            $inventory       = $this->accounts->findByCode('1200', $tenantId);
            $inputGst        = $this->accounts->findByCode('1600', $tenantId);
            $inputCgst       = $this->accounts->findByCode('1610', $tenantId)
                ?? $this->accounts->findByCode('1601', $tenantId)
                ?? (ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%Input CGST%')->first() ?: $inputGst);
            $inputSgst       = $this->accounts->findByCode('1620', $tenantId)
                ?? $this->accounts->findByCode('1602', $tenantId)
                ?? (ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%Input SGST%')->first() ?: $inputGst);
            $inputIgst       = $this->accounts->findByCode('1630', $tenantId)
                ?? $this->accounts->findByCode('1603', $tenantId)
                ?? (ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%Input IGST%')->first() ?: $inputGst);
            $freightExpense  = $this->accounts->findByCode('5030', $tenantId) ?: $this->accounts->findByCode('5900', $tenantId);

            if (!$accountsPayable || !$inventory) {
                Log::warning('PostPurchaseReturnJournal: missing Chart of Accounts (2010/1200), skipping auto-post', [
                    'purchase_return_id' => $return->id,
                    'tenant_id' => $tenantId,
                ]);
                $this->failures->record($tenantId, PurchaseReturnApproved::class, $return, $message);
                return;
            }

            $totalBaseAmount     = 0.0;
            $totalTaxAmount      = 0.0;
            $totalCgstAmount     = 0.0;
            $totalSgstAmount     = 0.0;
            $totalIgstAmount     = 0.0;
            $totalExtraFreight   = 0.0;

            // Related Vendor Bill lookup for exact line item GST tax rates
            $bill = null;
            if ($return->vendor_bill_id) {
                $bill = VendorBill::with('items')->find($return->vendor_bill_id);
            } elseif ($return->goods_receipt_note_id) {
                $bill = VendorBill::with('items')->where('goods_receipt_note_id', $return->goods_receipt_note_id)->first();
            }

            foreach ($return->items as $item) {
                $itemQty   = (float) $item->quantity;
                $unitPrice = (float) $item->unit_price;
                $lineBase  = round($itemQty * $unitPrice, 2);
                $totalBaseAmount += $lineBase;

                // 1. Check if Landed Cost Voucher was posted for this GRN Item
                if ($return->goods_receipt_note_id) {
                    $landedItem = LandedCostItem::where('goods_receipt_note_id', $return->goods_receipt_note_id)
                        ->where('product_id', $item->product_id)
                        ->whereHas('landedCostVoucher', fn($q) => $q->where('status', 'Posted'))
                        ->first();

                    if ($landedItem && $landedItem->quantity > 0) {
                        $perUnitFreight = (float) $landedItem->allocated_cost / (float) $landedItem->quantity;
                        $lineFreight    = round($perUnitFreight * $itemQty, 2);
                        $totalExtraFreight += $lineFreight;
                    }
                }

                // 2. Check line item GST Tax rates from Vendor Bill
                if ($bill) {
                    $billItem = $bill->items->where('product_id', $item->product_id)->first();
                    if ($billItem && (float) $billItem->tax_rate > 0) {
                        $taxRate   = (float) $billItem->tax_rate;
                        $lineTax   = round(($lineBase * $taxRate) / 100, 2);
                        $totalTaxAmount += $lineTax;

                        if ($bill->igst_amount > 0) {
                            $totalIgstAmount += $lineTax;
                        } else {
                            $halfTax = round($lineTax / 2, 2);
                            $totalCgstAmount += $halfTax;
                            $totalSgstAmount += ($lineTax - $halfTax);
                        }
                    }
                }
            }

            if ($totalBaseAmount <= 0) {
                return;
            }

            $vendorRefundAmount   = round($totalBaseAmount + $totalTaxAmount, 2);
            $totalInventoryCredit = round($totalBaseAmount + $totalExtraFreight, 2);

            $lines = [];

            // 1. Accounts Payable Vendor (Debit Note) - DEBIT
            $lines[] = [
                'chart_of_account_id' => $accountsPayable->id,
                'debit'               => $vendorRefundAmount,
                'credit'              => 0,
                'description'         => "Purchase Return Debit Note {$return->return_number} - Vendor Refund",
            ];

            // 2. Freight Expense / Return Loss - DEBIT (If Landed Cost was previously added)
            if ($totalExtraFreight > 0 && $freightExpense) {
                $lines[] = [
                    'chart_of_account_id' => $freightExpense->id,
                    'debit'               => $totalExtraFreight,
                    'credit'              => 0,
                    'description'         => "Unrecovered Freight Expense on Purchase Return {$return->return_number}",
                ];
            }

            // 3. Inventory Asset Account - CREDIT (Full Landed Stock Value Cleared)
            $lines[] = [
                'chart_of_account_id' => $inventory->id,
                'debit'               => 0,
                'credit'              => $totalInventoryCredit,
                'description'         => "Purchase Return {$return->return_number} - Stock Asset Reduction",
            ];

            // 4. Input GST Tax Credit Reversals - CREDIT
            if ($totalIgstAmount > 0 && $inputIgst) {
                $lines[] = [
                    'chart_of_account_id' => $inputIgst->id,
                    'debit'               => 0,
                    'credit'              => $totalIgstAmount,
                    'description'         => "Input IGST Reversal on Purchase Return {$return->return_number}",
                ];
            } else {
                if ($totalCgstAmount > 0 && $inputCgst) {
                    $lines[] = [
                        'chart_of_account_id' => $inputCgst->id,
                        'debit'               => 0,
                        'credit'              => $totalCgstAmount,
                        'description'         => "Input CGST Reversal on Purchase Return {$return->return_number}",
                    ];
                }
                if ($totalSgstAmount > 0 && $inputSgst) {
                    $lines[] = [
                        'chart_of_account_id' => $inputSgst->id,
                        'debit'               => 0,
                        'credit'              => $totalSgstAmount,
                        'description'         => "Input SGST Reversal on Purchase Return {$return->return_number}",
                    ];
                }
            }

            // Fallback for tax credit reversal if specific CGST/SGST accounts not mapped
            if ($totalTaxAmount > 0 && $totalCgstAmount == 0 && $totalIgstAmount == 0 && $inputGst) {
                $lines[] = [
                    'chart_of_account_id' => $inputGst->id,
                    'debit'               => 0,
                    'credit'              => $totalTaxAmount,
                    'description'         => "Input GST Tax Credit Reversal on Purchase Return {$return->return_number}",
                ];
            }

            // Post General Ledger Journal Entry
            $this->journals->post($lines, [
                'tenant_id'      => $tenantId,
                'journal_date'   => $return->return_date ?: now(),
                'source'         => Journal::SOURCE_PURCHASE,
                'reference_type' => 'purchase_return',
                'reference_id'   => $return->id,
                'memo'           => "Purchase Return / Debit Note {$return->return_number} ({$return->vendor?->name})",
            ]);

            Log::info("PostPurchaseReturnJournal: Successfully posted GL entry for Purchase Return {$return->return_number}", [
                'purchase_return_id' => $return->id,
                'vendor_refund'      => $vendorRefundAmount,
                'inventory_credit'   => $totalInventoryCredit,
                'extra_freight'      => $totalExtraFreight,
            ]);
        } catch (\Throwable $e) {
            Log::error('PostPurchaseReturnJournal: failed to auto-post journal', [
                'purchase_return_id' => $return->id,
                'error'              => $e->getMessage(),
            ]);
            $this->failures->record($tenantId, PurchaseReturnApproved::class, $return, $e->getMessage());
        }
    }
}
