<?php

namespace App\Domains\Purchase\Listeners;

use App\Domains\Purchase\Events\BillPosted;
use App\Domains\Purchase\Events\GoodsReceiptNoteApproved;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorBillItem;
use App\Domains\Purchase\Repositories\VendorBillRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoCreateVendorBillOnGrnApproved
{
    public function __construct(
        private readonly VendorBillRepository $billRepo
    ) {}

    public function handle(GoodsReceiptNoteApproved $event): void
    {
        $grn = $event->grn;
        $tenantId = $grn->tenant_id ?: (tenant_id() ?? 1);

        // Check if bill already exists for this GRN
        $existing = VendorBill::where('goods_receipt_note_id', $grn->id)
            ->where('status', '!=', 'Cancelled')
            ->first();

        if ($existing) {
            return;
        }

        try {
            DB::transaction(function () use ($grn, $tenantId) {
                $billNumber = $this->billRepo->getNextBillNumber($tenantId);
                $subtotal = 0.0;
                $taxAmount = 0.0;

                $itemsData = [];
                foreach ($grn->items as $item) {
                    $qty = (float) ($item->accepted_qty > 0 ? $item->accepted_qty : ($item->received_qty > 0 ? $item->received_qty : 0));
                    $price = (float) ($item->unit_rate > 0 ? $item->unit_rate : 0);
                    $lineSubtotal = $qty * $price;

                    $subtotal += $lineSubtotal;

                    $itemsData[] = [
                        'goods_receipt_note_item_id' => $item->id,
                        'product_id'                 => $item->product_id,
                        'quantity'                   => $qty,
                        'unit_rate'                  => $price,
                        'tax_percentage'             => 0,
                        'total_amount'               => $lineSubtotal,
                    ];
                }

                $grandTotal = $subtotal + $taxAmount;

                $bill = $this->billRepo->create([
                    'tenant_id'             => $tenantId,
                    'bill_number'           => $billNumber,
                    'vendor_invoice_number' => 'AUTO-' . $grn->grn_number,
                    'goods_receipt_note_id' => $grn->id,
                    'purchase_order_id'     => $grn->purchase_order_id,
                    'vendor_id'             => $grn->vendor_id,
                    'bill_date'             => $grn->received_date ?: now()->toDateString(),
                    'due_date'              => $grn->received_date ?: now()->toDateString(),
                    'status'                => 'Unpaid',
                    'subtotal'              => $subtotal,
                    'tax_amount'            => $taxAmount,
                    'grand_total'           => $grandTotal,
                    'paid_amount'           => 0,
                    'due_amount'            => round($grandTotal, 2),
                    'notes'                 => "Auto-generated Bill from GRN {$grn->grn_number}",
                    'created_by'            => auth()->id() ?: 1,
                ]);

                foreach ($itemsData as $row) {
                    VendorBillItem::create([
                        'tenant_id'                  => $tenantId,
                        'vendor_bill_id'             => $bill->id,
                        'product_id'                 => $row['product_id'],
                        'goods_receipt_note_item_id' => $row['goods_receipt_note_item_id'],
                        'quantity'                   => $row['quantity'],
                        'unit_rate'                  => $row['unit_rate'],
                        'tax_percentage'             => $row['tax_percentage'],
                        'total_amount'               => $row['total_amount'],
                    ]);
                }

                event(new BillPosted($bill));
            });
        } catch (\Throwable $e) {
            Log::error('AutoCreateVendorBillOnGrnApproved failed', [
                'grn_id' => $grn->id,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
