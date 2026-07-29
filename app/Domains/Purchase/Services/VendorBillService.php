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

            $subtotal = 0;
            $taxAmount = 0;

            foreach ($validated['items'] as $item) {
                $qty = (float)$item['quantity'];
                $price = (float)($item['unit_price'] ?? $item['unit_rate'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? $item['tax_percentage'] ?? 0);

                $lineSubtotal = $qty * $price;
                $lineTax = $lineSubtotal * ($taxRate / 100);

                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;
            }

            $grandTotal = $subtotal + $taxAmount;

            // Bill is always created as Unpaid — advance is applied separately
            // after the bill is posted (Odoo/Zoho style)
            $bill = $this->billRepo->create([
                'tenant_id'             => $tenantId,
                'bill_number'           => $billNumber,
                'vendor_invoice_number' => $validated['vendor_invoice_number'] ?? $validated['vendor_bill_number'] ?? null,
                'goods_receipt_note_id' => $grn?->id,
                'purchase_order_id'     => $po?->id,
                'vendor_id'             => $vendorId,
                'bill_date'             => $validated['bill_date'],
                'due_date'              => $validated['due_date'],
                'status'                => 'Unpaid',
                'subtotal'              => $subtotal,
                'tax_amount'            => $taxAmount,
                'grand_total'           => $grandTotal,
                'paid_amount'           => 0,
                'due_amount'            => round($grandTotal, 2),
                'notes'                 => $validated['notes'] ?? null,
                'created_by'            => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $item) {
                $qty = (float)$item['quantity'];
                $price = (float)($item['unit_price'] ?? $item['unit_rate'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? $item['tax_percentage'] ?? 0);

                $lineSubtotal = $qty * $price;
                $lineTax = $lineSubtotal * ($taxRate / 100);

                $poItem = $po ? $po->items->firstWhere('id', $item['purchase_order_item_id'] ?? null) : null;
                $productId = $item['product_id'] ?? $poItem?->product_id;

                VendorBillItem::create([
                    'tenant_id'                  => $tenantId,
                    'vendor_bill_id'             => $bill->id,
                    'product_id'                 => $productId,
                    'goods_receipt_note_item_id' => $item['goods_receipt_note_item_id'] ?? null,
                    'quantity'                   => $qty,
                    'unit_rate'                  => $price,
                    'tax_percentage'             => $taxRate,
                    'total_amount'               => $lineSubtotal + $lineTax,
                ]);
            }

            return $bill;
        });
    }

    public function getAvailableVendorAdvance(int $vendorId, int $tenantId): float
    {
        // Total advance paid to vendor (direct advance payments)
        $directAdvances = VendorPayment::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendorId)
            ->where('payment_type', 'Advance')
            ->sum('amount');

        // Total PO-linked advance payments
        $poAdvances = PurchaseAdvancePayment::where('tenant_id', $tenantId)
            ->where('vendor_id', $vendorId)
            ->sum('amount');

        // Only deduct advance amounts actually allocated to bills
        // (NOT total paid_amount — that includes direct payments too)
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
            $payableAccount = $this->accountRepo->findByCode('2100', $tenantId)
                ?? $this->accountRepo->getByType('liability')->first();

            $advanceAccount = $this->accountRepo->findByCode('1400', $tenantId)
                ?? $this->accountRepo->findByCode('2100', $tenantId)
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
