<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\PostingFailureRecorder;
use App\Domains\Purchase\Events\BillPosted;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;

class PostPurchaseBillJournal
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
        private readonly PostingFailureRecorder $failures,
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

            // Fetch Chart of Accounts by standard codes & names
            $accountsPayable = $this->accounts->findByCode('2010', $tenantId);
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
            
            // RCM Specific Accounts
            $rcmCgstPayable  = $this->accounts->findByCode('2086', $tenantId)
                ?? ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM CGST Payable%')->first();
            $rcmSgstPayable  = $this->accounts->findByCode('2087', $tenantId)
                ?? ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM SGST Payable%')->first();
            $rcmIgstPayable  = $this->accounts->findByCode('2085', $tenantId)
                ?? ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM IGST Payable%')->first();

            $rcmInputCgst    = $this->accounts->findByCode('1631', $tenantId)
                ?? ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM Input CGST%')->first();
            $rcmInputSgst    = $this->accounts->findByCode('1632', $tenantId)
                ?? ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM Input SGST%')->first();
            $rcmInputIgst    = $this->accounts->findByCode('1633', $tenantId)
                ?? ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%RCM Input IGST%')->first();

            $dutiesOutput    = $this->accounts->findByCode('2100', $tenantId);
            $outputCgst      = $this->accounts->findByCode('2110', $tenantId)
                ?? ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%Output CGST%')->first();
            $outputSgst      = $this->accounts->findByCode('2120', $tenantId)
                ?? ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%Output SGST%')->first();
            $outputIgst      = $this->accounts->findByCode('2130', $tenantId)
                ?? ChartOfAccount::where('tenant_id', $tenantId)->where('name', 'like', '%Output IGST%')->first();

            $inventory       = $this->accounts->findByCode('1200', $tenantId);
            $purchaseExpense = $this->accounts->findByCode('5900', $tenantId);
            $freightExpense  = $this->accounts->findByCode('5030', $tenantId) ?: $purchaseExpense;

            if (!$accountsPayable) {
                Log::warning('PostPurchaseBillJournal: missing Accounts Payable (2010) account, skipping auto-post', [
                    'bill_id' => $bill->id,
                    'tenant_id' => $tenantId,
                ]);
                $this->failures->record($tenantId, BillPosted::class, $bill, 'Missing Accounts Payable (2010) account.');
                return;
            }

            $goodsSubtotal = 0.0;
            $assetBuckets = []; // chart_of_account_id => subtotal
            $expenseBuckets = []; // chart_of_account_id => subtotal

            foreach ($bill->items as $item) {
                $lineSubtotal = (float) $item->quantity * (float) $item->unit_rate;
                $grnItem = $item->grnItem;
                $lineType = $grnItem?->line_type;

                if ($lineType === PurchaseOrderItem::LINE_TYPE_ASSET) {
                    $account = $grnItem->chartOfAccount
                        ?? $grnItem->assetCategory?->chartOfAccount
                        ?? $this->accounts->findByCode('1500', $tenantId);
                    $accountId = $account?->id;
                    if ($accountId) {
                        $assetBuckets[$accountId] = ($assetBuckets[$accountId] ?? 0) + $lineSubtotal;
                    }
                } elseif ($lineType === PurchaseOrderItem::LINE_TYPE_EXPENSE) {
                    $account = $grnItem->chartOfAccount ?? $purchaseExpense;
                    $accountId = $account?->id;
                    if ($accountId) {
                        $expenseBuckets[$accountId] = ($expenseBuckets[$accountId] ?? 0) + $lineSubtotal;
                    }
                } elseif ($lineType === PurchaseOrderItem::LINE_TYPE_STOCK) {
                    $goodsSubtotal += $lineSubtotal;
                } else {
                    // Legacy fallback: no linked GRN line / no line_type recorded — preserve
                    // the original goods-vs-service split so pre-existing bills post unchanged.
                    $isService = ($item->product && $item->product->item_type === 'Service') || (empty($item->product_id) && empty($item->goods_receipt_note_item_id));
                    if ($isService) {
                        $serviceAccount = $this->resolveServiceHeadAccount($bill, $tenantId, $freightExpense, $purchaseExpense);
                        $accountId = $serviceAccount?->id ?: ($freightExpense?->id ?: $purchaseExpense?->id);
                        if ($accountId) {
                            $expenseBuckets[$accountId] = ($expenseBuckets[$accountId] ?? 0) + $lineSubtotal;
                        }
                    } else {
                        $goodsSubtotal += $lineSubtotal;
                    }
                }
            }

            // Calculate item base value (subtotal minus discount)
            $netItemsValue = max(0.01, (float)$bill->subtotal - (float)$bill->discount_amount);
            $totalLinesSubtotal = $goodsSubtotal + array_sum($assetBuckets) + array_sum($expenseBuckets);
            if ($totalLinesSubtotal <= 0) {
                $goodsSubtotal = $netItemsValue;
            } else if ($bill->discount_amount > 0) {
                $goodsSubtotal = max(0.01, $goodsSubtotal - (float)$bill->discount_amount);
            }

            $lines = [];

            // Freight Terms Logic: If Freight is billed on invoice (to_be_billed / prepaid) AND allocation rule is capitalize (by_amount / by_quantity), capitalize Freight directly into Inventory Asset!
            $isFreightCapitalized = in_array($bill->freight_terms, ['to_be_billed', 'prepaid'])
                && !in_array($bill->freight_allocation_method, ['none', 'direct_expense']);
            $freightAmt = (float)$bill->freight_amount;

            $totalInventoryDebit = $goodsSubtotal + ($isFreightCapitalized ? $freightAmt : 0);

            // 1. Inventory / Stock Asset (Debit) - Landed Stock Value (Items + Freight)
            if ($totalInventoryDebit > 0 && $inventory) {
                $lines[] = [
                    'chart_of_account_id' => $inventory->id,
                    'debit'               => round($totalInventoryDebit, 2),
                    'credit'              => 0,
                    'description'         => $isFreightCapitalized && $freightAmt > 0
                        ? "Bill {$bill->bill_number} - Stock Purchase & Capitalized Freight Landed Cost"
                        : "Bill {$bill->bill_number} - Stock Purchase (Base Item Value)",
                ];
            }

            // 2. Fixed Asset purchases (Debit), one line per resolved account
            foreach ($assetBuckets as $accountId => $amount) {
                if ($amount > 0) {
                    $lines[] = [
                        'chart_of_account_id' => $accountId,
                        'debit'               => round($amount, 2),
                        'credit'              => 0,
                        'description'         => "Bill {$bill->bill_number} - Fixed Asset Purchase",
                    ];
                }
            }

            // 3. Service / Expense Purchase (Debit), one line per resolved account
            foreach ($expenseBuckets as $accountId => $amount) {
                if ($amount > 0) {
                    $lines[] = [
                        'chart_of_account_id' => $accountId,
                        'debit'               => round($amount, 2),
                        'credit'              => 0,
                        'description'         => "Bill {$bill->bill_number} - Service/Expense Charges",
                    ];
                }
            }

            // 4. Freight Charges Expense (Debit) - Only if NOT capitalized to inventory asset
            if (!$isFreightCapitalized && $freightAmt > 0 && $freightExpense) {
                $lines[] = [
                    'chart_of_account_id' => $freightExpense->id,
                    'debit'               => round($freightAmt, 2),
                    'credit'              => 0,
                    'description'         => "Bill {$bill->bill_number} - Freight & Transport Charges",
                ];
            }

            // 5. GST Tax Credits & Liabilities (RCM vs Forward Charge)
            $isRcm = in_array($bill->gst_type, ['rcm', 'rcm_cgst_sgst', 'rcm_igst']);

            if ($isRcm) {
                $isRcmIgst = ($bill->gst_type === 'rcm_igst') || ((float)$bill->igst_amount > 0);

                if ($isRcmIgst) {
                    $taxAmt = (float)$bill->igst_amount > 0 ? (float)$bill->igst_amount : (float)$bill->tax_amount;
                    if ($taxAmt > 0) {
                        // Debit: RCM Input IGST (Asset 1633)
                        $rcmAsset = $rcmInputIgst ?: ($inputIgst ?: $inputGst);
                        if ($rcmAsset) {
                            $lines[] = [
                                'chart_of_account_id' => $rcmAsset->id,
                                'debit'               => round($taxAmt, 2),
                                'credit'              => 0,
                                'description'         => "RCM Input IGST Credit on Bill {$bill->bill_number}",
                            ];
                        }
                        // Credit: RCM IGST Payable (Liability 2085)
                        $rcmLiab = $rcmIgstPayable ?: ($outputIgst ?: $dutiesOutput);
                        if ($rcmLiab) {
                            $lines[] = [
                                'chart_of_account_id' => $rcmLiab->id,
                                'debit'               => 0,
                                'credit'              => round($taxAmt, 2),
                                'description'         => "RCM IGST Payable on Bill {$bill->bill_number}",
                            ];
                        }
                    }
                } else {
                    // Intra-State RCM (CGST + SGST)
                    $cgstAmt = (float)$bill->cgst_amount > 0 ? (float)$bill->cgst_amount : round((float)$bill->tax_amount / 2, 2);
                    $sgstAmt = (float)$bill->sgst_amount > 0 ? (float)$bill->sgst_amount : round((float)$bill->tax_amount - $cgstAmt, 2);

                    if ($cgstAmt > 0) {
                        // Debit: RCM Input CGST (Asset 1631)
                        $cgstAsset = $rcmInputCgst ?: ($inputCgst ?: $inputGst);
                        if ($cgstAsset) {
                            $lines[] = [
                                'chart_of_account_id' => $cgstAsset->id,
                                'debit'               => round($cgstAmt, 2),
                                'credit'              => 0,
                                'description'         => "RCM Input CGST Credit on Bill {$bill->bill_number}",
                            ];
                        }
                        // Credit: RCM CGST Payable (Liability 2086)
                        $cgstLiab = $rcmCgstPayable ?: ($outputCgst ?: $dutiesOutput);
                        if ($cgstLiab) {
                            $lines[] = [
                                'chart_of_account_id' => $cgstLiab->id,
                                'debit'               => 0,
                                'credit'              => round($cgstAmt, 2),
                                'description'         => "RCM CGST Payable on Bill {$bill->bill_number}",
                            ];
                        }
                    }

                    if ($sgstAmt > 0) {
                        // Debit: RCM Input SGST (Asset 1632)
                        $sgstAsset = $rcmInputSgst ?: ($inputSgst ?: $inputGst);
                        if ($sgstAsset) {
                            $lines[] = [
                                'chart_of_account_id' => $sgstAsset->id,
                                'debit'               => round($sgstAmt, 2),
                                'credit'              => 0,
                                'description'         => "RCM Input SGST Credit on Bill {$bill->bill_number}",
                            ];
                        }
                        // Credit: RCM SGST Payable (Liability 2087)
                        $sgstLiab = $rcmSgstPayable ?: ($outputSgst ?: $dutiesOutput);
                        if ($sgstLiab) {
                            $lines[] = [
                                'chart_of_account_id' => $sgstLiab->id,
                                'debit'               => 0,
                                'credit'              => round($sgstAmt, 2),
                                'description'         => "RCM SGST Payable on Bill {$bill->bill_number}",
                            ];
                        }
                    }
                }
            } else {
                // Forward Charge Mechanism (FCM) Input GST Tax Credits (Debit)
                $hasTaxLines = false;
                if ((float)$bill->igst_amount > 0 && $inputIgst) {
                    $lines[] = [
                        'chart_of_account_id' => $inputIgst->id,
                        'debit'               => round((float)$bill->igst_amount, 2),
                        'credit'              => 0,
                        'description'         => "Input IGST on Bill {$bill->bill_number}",
                    ];
                    $hasTaxLines = true;
                } else {
                    if ((float)$bill->cgst_amount > 0 && $inputCgst) {
                        $lines[] = [
                            'chart_of_account_id' => $inputCgst->id,
                            'debit'               => round((float)$bill->cgst_amount, 2),
                            'credit'              => 0,
                            'description'         => "Input CGST on Bill {$bill->bill_number}",
                        ];
                        $hasTaxLines = true;
                    }
                    if ((float)$bill->sgst_amount > 0 && $inputSgst) {
                        $lines[] = [
                            'chart_of_account_id' => $inputSgst->id,
                            'debit'               => round((float)$bill->sgst_amount, 2),
                            'credit'              => 0,
                            'description'         => "Input SGST on Bill {$bill->bill_number}",
                        ];
                        $hasTaxLines = true;
                    }
                }

                // Fallback for tax amount if cgst/igst fields were empty on bill model
                if (!$hasTaxLines && (float)$bill->tax_amount > 0) {
                    if ($inputCgst && $inputSgst && $inputCgst->id !== $inputGst->id) {
                        $halfTax = round((float)$bill->tax_amount / 2, 2);
                        $lines[] = [
                            'chart_of_account_id' => $inputCgst->id,
                            'debit'               => $halfTax,
                            'credit'              => 0,
                            'description'         => "Input CGST on Bill {$bill->bill_number}",
                        ];
                        $lines[] = [
                            'chart_of_account_id' => $inputSgst->id,
                            'debit'               => round((float)$bill->tax_amount - $halfTax, 2),
                            'credit'              => 0,
                            'description'         => "Input SGST on Bill {$bill->bill_number}",
                        ];
                    } else if ($inputGst) {
                        $lines[] = [
                            'chart_of_account_id' => $inputGst->id,
                            'debit'               => round((float)$bill->tax_amount, 2),
                            'credit'              => 0,
                            'description'         => "Input GST Tax Credit on Bill {$bill->bill_number}",
                        ];
                    }
                }
            }

            // 6. Accounts Payable / Vendor Account (Credit)
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
            $this->failures->record($bill->tenant_id, BillPosted::class, $bill, $e->getMessage());
        }
    }

    /**
     * Resolves the specific Chart of Account based on the Service Head selected in bill notes / product type.
     */
    private function resolveServiceHeadAccount($bill, int $tenantId, ?ChartOfAccount $defaultFreight, ?ChartOfAccount $defaultExpense): ?ChartOfAccount
    {
        $notes = $bill->notes ?? '';

        if (stripos($notes, 'Customs Duty') !== false) {
            return $this->accounts->findByCode('5020', $tenantId)
                ?? $this->accounts->findByCode('5030', $tenantId)
                ?? $defaultExpense;
        }

        if (stripos($notes, 'Loading & Unloading') !== false) {
            return $this->accounts->findByCode('5031', $tenantId)
                ?? $this->accounts->findByCode('5030', $tenantId)
                ?? $defaultExpense;
        }

        if (stripos($notes, 'Insurance') !== false) {
            return $this->accounts->findByCode('5540', $tenantId)
                ?? $defaultExpense;
        }

        if (stripos($notes, 'Other Service') !== false) {
            return $this->accounts->findByCode('5900', $tenantId)
                ?? $defaultExpense;
        }

        if (stripos($notes, 'Freight') !== false || stripos($notes, 'Transport') !== false || stripos($notes, 'Handling') !== false) {
            return $this->accounts->findByCode('5030', $tenantId)
                ?? $defaultExpense;
        }

        return $defaultFreight ?: $defaultExpense;
    }
}
